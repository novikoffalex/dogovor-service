<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\GenerateContractJob;

class ContractController extends Controller
{
    public function showForm()
    {
        return view('contract-form');
    }

    public function generate(Request $request)
    {
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

        // Проверяем, не существует ли уже договор для этого клиента
        $clientHash = md5($data['client_full_name'] . $data['inn'] . $data['passport_full']);
        $existingContractKey = "contract_exists_{$clientHash}";
        
        if (Cache::has($existingContractKey)) {
            $existingData = Cache::get($existingContractKey);
            Log::info('Returning existing contract for client', [
                'client_hash' => $clientHash,
                'contract_number' => $existingData['contract_number']
            ]);
            
            return response()->json([
                'success' => true,
                'contract_url' => $existingData['contract_url'],
                'filename' => $existingData['filename'],
                'contract_number' => $existingData['contract_number'],
                'cached' => true
            ]);
        }

        // Генерируем номер договора если не передан
        if (empty($data['contract_number'])) {
            $today = now()->format('Ymd');
            $cacheKey = "contract_counter_global";
            
            // Используем Laravel KV Store для глобального счетчика
            $counter = Cache::increment($cacheKey, 1);
            
            // Если счетчик превысил 1000, сбрасываем на 1
            if ($counter > 1000) {
                Cache::put($cacheKey, 1);
                $counter = 1;
            }
            
            $data['contract_number'] = $today . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        }

        // Генерируем дату
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $data['contract_date'] = '«' . now()->format('d') . '» ' . $months[now()->month] . ' ' . now()->format('Y') . ' г.';

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
            
            $data[$k] = $cleanValue;
        }

        try {
            // Генерируем DOCX
            $tpl = new TemplateProcessor(resource_path('contracts/contract.docx'));
            
            foreach ($data as $key => $value) {
                $tpl->setValue($key, $value);
            }

            $safeName = Str::slug($data['client_full_name'], '_');
            if ($safeName === '') {
                $safeName = 'contract';
            }
            $filename = $safeName.'_'.$data['contract_number'];
            $docxRel = 'contracts/'.$filename.'.docx';
            
            // Сохраняем файл в public storage для скачивания
            $publicPath = 'contracts/'.$filename.'.docx';
            $tmpDocx = storage_path('app/public/'.$publicPath);
            @mkdir(dirname($tmpDocx), 0775, true);
            $tpl->saveAs($tmpDocx);
            
            // Генерируем PDF синхронно
            $pdfPath = $this->generatePdf($tmpDocx, $filename);
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'public_path' => $publicPath,
                'pdf_path' => $pdfPath
            ]);
            
            // Возвращаем JSON с ссылкой на PDF файл
            $contractUrl = url('api/contract/download/'.$filename.'.pdf');
            
            // Сохраняем информацию о договоре в кеш для защиты от повторных запросов
            $contractData = [
                'contract_url' => $contractUrl,
                'filename' => $filename.'.pdf',
                'contract_number' => $data['contract_number'],
                'created_at' => now()->toISOString()
            ];
            Cache::put($existingContractKey, $contractData, now()->addDays(30)); // Кеш на 30 дней
            
            return response()->json([
                'success' => true,
                'contract_url' => $contractUrl,
                'filename' => $filename.'.pdf',
                'contract_number' => $data['contract_number']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Contract generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'contract_generation_failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function download($filename)
    {
        $pdfPath = storage_path('app/public/contracts/' . $filename);
        
        if (file_exists($pdfPath)) {
            return response()->download($pdfPath, $filename, [
                'Content-Type' => 'application/pdf'
            ]);
        }
        
        return response()->json(['error' => 'File not found'], 404);
    }

    public function uploadSigned(Request $request)
    {
        $request->validate([
            'signed_contract' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
            'contract_number' => 'required|string'
        ]);

        try {
            $file = $request->file('signed_contract');
            $contractNumber = $request->input('contract_number');
            
            // Сохраняем файл
            $filename = 'signed_' . $contractNumber . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('signed-contracts', $filename, 'public');
            
            Log::info('Signed contract uploaded', [
                'contract_number' => $contractNumber,
                'filename' => $filename,
                'path' => $path
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Подписанный договор успешно загружен',
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            Log::error('Signed contract upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке файла: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generatePdf($docxPath, $filename)
    {
        // Используем Pandoc для конвертации DOCX в PDF
        $pdfPath = storage_path('app/public/contracts/' . $filename . '.pdf');
        
        $command = "pandoc '{$docxPath}' -o '{$pdfPath}' --pdf-engine=wkhtmltopdf";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error('PDF generation failed', [
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode
            ]);
            throw new \Exception('Ошибка генерации PDF: ' . implode(' ', $output));
        }
        
        return $pdfPath;
    }
}
