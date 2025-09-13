<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\GenerateContractJob;
use Zamzar\Zamzar;

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

        // Временно отключаем проверку кеша для отладки
        // $clientHash = md5($data['client_full_name'] . $data['inn'] . $data['passport_full']);
        // $existingContractKey = "contract_exists_{$clientHash}";

        // Генерируем номер договора если не передан
        if (empty($data['contract_number'])) {
            $today = now()->format('Ymd');
            
            // Используем файловый счетчик как fallback
            $counterFile = storage_path('app/contract_counter.txt');
            $counter = 1;
            
            if (file_exists($counterFile)) {
                $counter = (int)file_get_contents($counterFile) + 1;
            }
            
            // Если счетчик превысил 1000, сбрасываем на 1
            if ($counter > 1000) {
                $counter = 1;
            }
            
            // Сохраняем счетчик в файл
            @mkdir(dirname($counterFile), 0775, true);
            file_put_contents($counterFile, $counter);
            
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
            
            // Сохраняем файл в storage для скачивания
            $docxPath = 'contracts/'.$filename.'.docx';
            $tmpDocx = storage_path('app/'.$docxPath);
            @mkdir(dirname($tmpDocx), 0775, true);
            $tpl->saveAs($tmpDocx);
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'docx_path' => $docxPath
            ]);
            
            // Запускаем PDF конвертацию через Zamzar в фоне
            $this->convertToPdfAsync($tmpDocx, $filename);
            
            // Возвращаем JSON с ссылкой на DOCX файл (PDF будет готов позже)
            $contractUrl = url('api/contract/download/'.$filename.'.docx');
            
            // Временно отключаем кеширование для отладки
            // $contractData = [
            //     'contract_url' => $contractUrl,
            //     'filename' => $filename.'.docx',
            //     'contract_number' => $data['contract_number'],
            //     'created_at' => now()->toISOString()
            // ];
            // Cache::put($existingContractKey, $contractData, now()->addDays(30));
            
            return response()->json([
                'success' => true,
                'contract_url' => $contractUrl,
                'filename' => $filename.'.docx',
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
        $docxPath = storage_path('app/contracts/' . $filename);
        
        if (file_exists($docxPath)) {
            return response()->download($docxPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
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
            
            // Создаем уникальное имя файла
            $filename = 'signed_' . $contractNumber . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Сохраняем файл
            $path = $file->storeAs('signed-contracts', $filename, 'public');
            
            Log::info('Signed contract uploaded successfully', [
                'filename' => $filename,
                'contract_number' => $contractNumber,
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
                'error' => 'upload_failed',
                'message' => 'Ошибка при загрузке файла: ' . $e->getMessage()
            ], 500);
        }
    }

    private function convertToPdfAsync($docxPath, $filename)
    {
        try {
            // Используем тестовый API ключ Zamzar
            $zamzar = new Zamzar('test_key_123456789');
            
            // Загружаем файл в Zamzar
            $sourceFile = $zamzar->files->upload([
                'name' => $filename . '.docx',
                'content' => file_get_contents($docxPath)
            ]);
            
            Log::info('File uploaded to Zamzar', [
                'file_id' => $sourceFile->getId(),
                'filename' => $filename
            ]);
            
            // Запускаем конвертацию
            $job = $zamzar->jobs->create([
                'source_file' => $sourceFile->getId(),
                'target_format' => 'pdf'
            ]);
            
            Log::info('Zamzar conversion job started', [
                'job_id' => $job->getId(),
                'filename' => $filename
            ]);
            
            // Сохраняем информацию о задаче
            $jobData = [
                'job_id' => $job->getId(),
                'source_file_id' => $sourceFile->getId(),
                'filename' => $filename,
                'status' => 'processing',
                'created_at' => now()->toISOString()
            ];
            
            // Сохраняем в файл вместо кеша
            $jobFile = storage_path('app/zamzar_jobs.json');
            $jobs = [];
            if (file_exists($jobFile)) {
                $jobs = json_decode(file_get_contents($jobFile), true) ?: [];
            }
            $jobs[$filename] = $jobData;
            file_put_contents($jobFile, json_encode($jobs, JSON_PRETTY_PRINT));
            
        } catch (\Exception $e) {
            Log::error('Zamzar conversion failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function zamzarWebhook(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('Zamzar webhook received', $data);
            
            $jobId = $data['id'] ?? null;
            $status = $data['status'] ?? null;
            
            if (!$jobId || !$status) {
                return response()->json(['error' => 'Invalid webhook data'], 400);
            }
            
            // Находим задачу по job_id
            $jobFile = storage_path('app/zamzar_jobs.json');
            $jobs = [];
            if (file_exists($jobFile)) {
                $jobs = json_decode(file_get_contents($jobFile), true) ?: [];
            }
            
            $jobKey = null;
            foreach ($jobs as $key => $job) {
                if ($job['job_id'] == $jobId) {
                    $jobKey = $key;
                    break;
                }
            }
            
            if (!$jobKey) {
                Log::warning('Zamzar job not found', ['job_id' => $jobId]);
                return response()->json(['error' => 'Job not found'], 404);
            }
            
            if ($status === 'successful') {
                // Скачиваем PDF файл
                $zamzar = new Zamzar('test_key_123456789');
                $job = $zamzar->jobs->get($jobId);
                $targetFile = $job->getTargetFiles()[0];
                
                // Скачиваем PDF
                $pdfContent = $zamzar->files->download($targetFile->getId());
                
                // Сохраняем PDF локально
                $pdfPath = storage_path('app/contracts/' . $jobs[$jobKey]['filename'] . '.pdf');
                @mkdir(dirname($pdfPath), 0775, true);
                file_put_contents($pdfPath, $pdfContent);
                
                // Обновляем статус
                $jobs[$jobKey]['status'] = 'completed';
                $jobs[$jobKey]['pdf_path'] = $pdfPath;
                $jobs[$jobKey]['completed_at'] = now()->toISOString();
                
                file_put_contents($jobFile, json_encode($jobs, JSON_PRETTY_PRINT));
                
                Log::info('PDF conversion completed', [
                    'filename' => $jobs[$jobKey]['filename'],
                    'pdf_path' => $pdfPath
                ]);
                
            } elseif ($status === 'failed') {
                // Обновляем статус на failed
                $jobs[$jobKey]['status'] = 'failed';
                $jobs[$jobKey]['failed_at'] = now()->toISOString();
                
                file_put_contents($jobFile, json_encode($jobs, JSON_PRETTY_PRINT));
                
                Log::error('PDF conversion failed', [
                    'filename' => $jobs[$jobKey]['filename'],
                    'job_id' => $jobId
                ]);
            }
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('Zamzar webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}