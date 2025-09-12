<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Jobs\GenerateContractJob;
use PhpOffice\PhpWord\TemplateProcessor;

class ManychatContractController extends Controller
{
    public function generate(Request $request) 
    {
        Log::info('ManyChat request received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);
        
        if ($request->header('X-Auth-Token') !== config('services.manychat.token')) {
            Log::error('Invalid token', [
                'received_token' => $request->header('X-Auth-Token'),
                'expected_token' => config('services.manychat.token')
            ]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'client_full_name' => 'required|string|max:255',
            'passport_series'  => 'nullable|string|max:10',
            'passport_number'  => 'nullable|string|max:20',
            'passport_full'    => 'nullable|string|max:50',
            'inn'              => 'required|string|max:20',
            'client_address'   => 'required|string|max:255',
            'bank_name'        => 'required|string|max:255',
            'bank_account'     => 'required|string|max:50',
            'bank_bik'         => 'required|string|max:20',
            'bank_swift'       => 'nullable|string|max:20',
            'contract_number'  => 'nullable|string|max:50',
        ]);

        // Парсим passport_full если отдельные поля не заполнены
        if (empty($data['passport_series']) && empty($data['passport_number']) && !empty($data['passport_full'])) {
            if (preg_match('/^(\d{4})\s+(\d{6})$/', $data['passport_full'], $matches)) {
                $data['passport_series'] = $matches[1];
                $data['passport_number'] = $matches[2];
            }
        }

        // Генерируем номер договора если не передан
        if (empty($data['contract_number'])) {
            $today = now()->format('Ymd');
            $key = 'contract_counter_global';
            $cache = Cache::store('database');
            $cache->add($key, 0, now()->addYears(10));
            $current = (int) $cache->increment($key);
            $seq = (($current - 1) % 999) + 1; // Sequence 1-999
            $data['contract_number'] = $today.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        // Генерируем дату договора
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $data['contract_date'] = '«' . now()->format('d') . '» ' . $months[now()->month] . ' ' . now()->format('Y') . ' г.';

        // Генерируем DOCX синхронно для надежности
        $safeName = Str::slug($data['client_full_name'], '_');
        if ($safeName === '') {
            $safeName = 'contract';
        }
        $filename = $safeName.'_'.$data['contract_number'];
        $docxRel = 'contracts/'.$filename.'.docx';
        $pdfRel = 'contracts/'.$filename.'.pdf';
        
        // Генерируем DOCX
        $this->generateDocxOnly($data, $docxRel);
        
        // Проверяем, есть ли уже PDF
        if (Storage::disk('public')->exists($pdfRel)) {
            Log::info('Contract generated with existing PDF', [
                'filename' => $filename,
                'pdf_url' => Storage::url($pdfRel)
            ]);
            return response()->json(['contract_url' => Storage::url($pdfRel)]);
        }
        
        // Отправляем задачу в очередь для PDF конвертации
        try {
            GenerateContractJob::dispatch($data);
            Log::info('PDF conversion queued', ['filename' => $filename]);
        } catch (\Exception $e) {
            Log::warning('PDF conversion queue failed', ['error' => $e->getMessage()]);
        }
        
        Log::info('Contract generated DOCX', [
            'filename' => $filename,
            'docx_url' => Storage::url($docxRel)
        ]);
        
        return response()->json(['contract_url' => Storage::url($docxRel)]);
    }
    
    private function generateDocxOnly($data, $docxRel)
    {
        try {
            // Генерируем DOCX из шаблона
            $tpl = new TemplateProcessor(resource_path('contracts/contract.docx'));
            
            // Ограничиваем длину полей
            foreach ($data as $k => $v) {
                $cleanValue = trim(strip_tags($v));
                
                if ($k === 'client_full_name') {
                    $cleanValue = Str::limit($cleanValue, 50);
                } elseif ($k === 'client_address') {
                    $cleanValue = Str::limit($cleanValue, 100);
                } elseif ($k === 'bank_name') {
                    $cleanValue = Str::limit($cleanValue, 80);
                } elseif ($k === 'bank_account') {
                    $cleanValue = Str::limit($cleanValue, 50);
                } elseif ($k === 'bank_bik') {
                    $cleanValue = Str::limit($cleanValue, 20);
                } elseif ($k === 'bank_swift') {
                    $cleanValue = Str::limit($cleanValue, 20);
                } elseif ($k === 'inn') {
                    $cleanValue = Str::limit($cleanValue, 20);
                } elseif ($k === 'passport_full') {
                    $cleanValue = Str::limit($cleanValue, 30);
                }
                
                $tpl->setValue($k, $cleanValue);
            }
            
            $tmpDocx = storage_path('app/'.$docxRel);
            @mkdir(dirname($tmpDocx), 0775, true);
            
            // Сохраняем DOCX
            $tpl->saveAs($tmpDocx);
            Storage::disk('public')->put($docxRel, file_get_contents($tmpDocx));
            @unlink($tmpDocx);
            
            Log::info('DOCX generated synchronously', ['file' => $docxRel]);
            
        } catch (\Throwable $e) {
            Log::error('Sync DOCX generation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    private function convertToPdf($docxRel, $pdfRel)
    {
        $apiKey = '4bb76644955076ff4def01f10b50e2ad7c0e4b00';
        $tmpDocx = storage_path('app/public/'.$docxRel);
        
        // Загружаем файл на Zamzar
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/jobs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
        
        $postData = [
            'source_file' => new \CURLFile($tmpDocx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'contract.docx'),
            'target_format' => 'pdf'
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $job = json_decode($response, true);
            $jobId = $job['id'];
            
            // Ждем завершения конвертации (до 180 секунд)
            $maxWaitTime = 180;
            $waitTime = 0;
            
            while ($waitTime < $maxWaitTime) {
                sleep(3);
                $waitTime += 3;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/jobs/' . $jobId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
                
                $statusResponse = curl_exec($ch);
                curl_close($ch);
                
                $status = json_decode($statusResponse, true);
                
                if ($status['status'] === 'successful') {
                    // Получаем результат
                    $fileId = $status['target_files'][0]['id'];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/files/' . $fileId . '/content');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
                    
                    $pdfContent = curl_exec($ch);
                    curl_close($ch);
                    
                    if ($pdfContent) {
                        // Сохраняем PDF
                        Storage::disk('public')->put($pdfRel, $pdfContent);
                        return;
                    }
                }
                
                if ($status['status'] === 'failed') {
                    throw new \Exception('Zamzar conversion failed');
                }
            }
            
            throw new \Exception('Zamzar conversion timeout');
        } else {
            throw new \Exception('Failed to create Zamzar job: ' . $response);
        }
    }
    
    public function generatePdf(Request $request)
    {
        // Проверяем токен
        if ($request->header('X-Auth-Token') !== config('services.manychat.token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        
        // Валидируем данные
        $data = $request->validate([
            'client_full_name' => 'required|string|max:255',
            'passport_full' => 'required|string|max:50',
            'inn' => 'required|string|max:20',
            'client_address' => 'required|string|max:500',
            'bank_name' => 'required|string|max:255',
            'bank_account' => 'required|string|max:50',
            'bank_bik' => 'required|string|max:20',
            'bank_swift' => 'required|string|max:20',
        ]);
        
        Log::info('PDF generation request received', $data);
        
        // Парсим паспорт
        if (preg_match('/^(\d{4})\s+(\d{6})$/', $data['passport_full'], $matches)) {
            $data['passport_series'] = $matches[1];
            $data['passport_number'] = $matches[2];
        } else {
            return response()->json(['error' => 'invalid_passport_format'], 400);
        }
        
        // Генерируем номер договора
        $today = now()->format('Ymd');
        $cacheKey = "contract_counter_{$today}";
        $counter = Cache::store('database')->increment($cacheKey, 1);
        $data['contract_number'] = $today . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        // Генерируем дату
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $data['contract_date'] = '«' . now()->format('d') . '» ' . $months[now()->month] . ' ' . now()->format('Y') . ' г.';
        
        // Формируем имена файлов
        $safeName = Str::slug($data['client_full_name'], '_');
        if ($safeName === '') {
            $safeName = 'contract';
        }
        $filename = $safeName.'_'.$data['contract_number'];
        $docxRel = 'contracts/'.$filename.'.docx';
        $pdfRel = 'contracts/'.$filename.'.pdf';
        
        // Ждем 25 секунд для PDF конвертации
        Log::info('Waiting 25 seconds for PDF conversion', ['filename' => $filename]);
        sleep(25);
        
        // Проверяем, есть ли PDF
        if (Storage::disk('public')->exists($pdfRel)) {
            Log::info('PDF ready', [
                'filename' => $filename,
                'pdf_url' => Storage::url($pdfRel)
            ]);
            return response()->json(['contract_url' => Storage::url($pdfRel)]);
        }
        
        // Если PDF нет, генерируем DOCX и конвертируем в PDF
        $this->generateDocxOnly($data, $docxRel);
        
        try {
            $this->convertToPdf($docxRel, $pdfRel);
            Log::info('PDF generated after wait', [
                'filename' => $filename,
                'pdf_url' => Storage::url($pdfRel)
            ]);
            return response()->json(['contract_url' => Storage::url($pdfRel)]);
        } catch (\Exception $e) {
            Log::error('PDF conversion failed after wait', ['error' => $e->getMessage()]);
            return response()->json(['contract_url' => Storage::url($docxRel)]);
        }
    }
}