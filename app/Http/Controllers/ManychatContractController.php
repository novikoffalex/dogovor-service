<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\GenerateContractJob;

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
            $cacheKey = "contract_counter_{$today}";
            $counter = Cache::store('database')->increment($cacheKey, 1);
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
            
            // Сохраняем файл
            $tmpDocx = storage_path('app/'.$docxRel);
            @mkdir(dirname($tmpDocx), 0775, true);
            $tpl->saveAs($tmpDocx);
            
            // PDF конвертация будет через webhook
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'temp_path' => $tmpDocx
            ]);
            
            // Запускаем конвертацию в PDF через Zamzar с webhook
            try {
                $zamzarApiKey = config('services.zamzar.api_key');
                if ($zamzarApiKey) {
                    // Создаем задачу конвертации в Zamzar с webhook
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/jobs');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_USERPWD, $zamzarApiKey . ':');
                    
                    // Загружаем файл через multipart/form-data + webhook URL
                    $postFields = [
                        'source_file' => new \CURLFile($tmpDocx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $filename.'.docx'),
                        'target_format' => 'pdf',
                        'webhook_url' => 'https://dogovor-service-main-srtt1t.laravel.cloud/api/zamzar/webhook'
                    ];
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    Log::info('Zamzar API response with webhook', [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                    
                    if ($httpCode === 200 || $httpCode === 201) {
                        $job = json_decode($response, true);
                        $jobId = $job['id'];
                        
                        // Сохраняем mapping job_id -> filename для webhook
                        Cache::put("zamzar_job_{$jobId}", $filename, 3600); // 1 час
                        
                        Log::info('Zamzar job created with webhook', [
                            'job_id' => $jobId,
                            'filename' => $filename
                        ]);
                    } else {
                        Log::error('Zamzar job creation failed', ['response' => $response]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Zamzar PDF conversion failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Возвращаем JSON с DOCX URL и будущим PDF URL
            $docxUrl = url('storage/contracts/' . $filename . '.docx');
            $pdfUrl = url('storage/contracts/' . $filename . '.pdf');
            
            // Сохраняем DOCX в public storage для доступа
            $publicDocxPath = storage_path('app/public/contracts/' . $filename . '.docx');
            @mkdir(dirname($publicDocxPath), 0775, true);
            
            Log::info('Copying DOCX to public storage', [
                'from' => $tmpDocx,
                'to' => $publicDocxPath
            ]);
            
            if (copy($tmpDocx, $publicDocxPath)) {
                Log::info('DOCX copied successfully');
            } else {
                Log::error('Failed to copy DOCX to public storage');
            }
            
            @unlink($tmpDocx);
            
            return response()->json([
                'contract_url' => $docxUrl,
                'pdf_url' => $pdfUrl,
                'message' => 'Contract generated. DOCX available now, PDF will be ready shortly.',
                'filename' => $filename
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


    public function getPdf($filename)
    {
        $pdfPath = storage_path('app/public/contracts/' . $filename . '.pdf');
        
        if (file_exists($pdfPath)) {
            return response()->download($pdfPath, $filename . '.pdf', [
                'Content-Type' => 'application/pdf'
            ]);
        }
        
        return response()->json(['error' => 'PDF not found'], 404);
    }

    public function generatePdfSimple(Request $request)
    {
        Log::info('PDF request received', [
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
            $cacheKey = "contract_counter_{$today}";
            $counter = Cache::store('database')->increment($cacheKey, 1);
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
            
            // Сохраняем DOCX
            $tmpDocx = storage_path('app/temp_'.$filename.'.docx');
            $tpl->saveAs($tmpDocx);
            
            // Конвертируем в PDF через CloudConvert API
            $pdfPath = storage_path('app/temp_'.$filename.'.pdf');
            
            $cloudConvertApiKey = config('services.cloudconvert.api_key');
            if (!$cloudConvertApiKey) {
                throw new \Exception('CloudConvert API key not configured');
            }
            
            // Создаем задачу конвертации
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.cloudconvert.com/v2/jobs');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $cloudConvertApiKey,
                'Content-Type: application/json'
            ]);
            
            $jobData = [
                'tasks' => [
                    [
                        'name' => 'convert-docx',
                        'operation' => 'convert',
                        'input_format' => 'docx',
                        'output_format' => 'pdf',
                        'input' => 'upload'
                    ]
                ]
            ];
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jobData));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                throw new \Exception('CloudConvert job creation failed: ' . $response);
            }
            
            $job = json_decode($response, true);
            $jobId = $job['data']['id'];
            
            Log::info('CloudConvert job created', ['job_id' => $jobId]);
            
            // Загружаем файл
            $uploadUrl = $job['data']['tasks'][0]['result']['form']['url'];
            $uploadFields = $job['data']['tasks'][0]['result']['form']['parameters'];
            
            $postFields = $uploadFields;
            $postFields['file'] = new \CURLFile($tmpDocx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $filename.'.docx');
            
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, []);
            
            $uploadResponse = curl_exec($ch);
            $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($uploadCode !== 200 && $uploadCode !== 204) {
                throw new \Exception('File upload failed: ' . $uploadResponse);
            }
            
            Log::info('File uploaded to CloudConvert');
            
            // Ждем завершения конвертации
            $maxWaitTime = 60; // 60 секунд максимум
            $waitTime = 0;
            
            while ($waitTime < $maxWaitTime) {
                sleep(2);
                $waitTime += 2;
                
                curl_setopt($ch, CURLOPT_URL, 'https://api.cloudconvert.com/v2/jobs/' . $jobId);
                curl_setopt($ch, CURLOPT_POST, 0);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $cloudConvertApiKey,
                    'Content-Type: application/json'
                ]);
                
                $statusResponse = curl_exec($ch);
                $status = json_decode($statusResponse, true);
                
                if ($status['data']['status'] === 'finished') {
                    // Скачиваем готовый PDF
                    $downloadUrl = $status['data']['tasks'][0]['result']['files'][0]['url'];
                    
                    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, []);
                    curl_setopt($ch, CURLOPT_POST, 0);
                    
                    $pdfContent = curl_exec($ch);
                    file_put_contents($pdfPath, $pdfContent);
                    
                    curl_close($ch);
                    
                    if (file_exists($pdfPath)) {
                        @unlink($tmpDocx);
                        
                        Log::info('PDF generated successfully via CloudConvert', [
                            'filename' => $filename,
                            'size' => filesize($pdfPath)
                        ]);
                        
                        return response()->download($pdfPath, $filename.'.pdf', [
                            'Content-Type' => 'application/pdf'
                        ])->deleteFileAfterSend(true);
                    }
                } elseif ($status['data']['status'] === 'error') {
                    throw new \Exception('CloudConvert conversion failed: ' . json_encode($status));
                }
            }
            
            curl_close($ch);
            throw new \Exception('CloudConvert conversion timeout');
            
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'pdf_generation_failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function testPdf()
    {
        return response()->json(['message' => 'PDF endpoint works!', 'timestamp' => now()->format('Y-m-d H:i:s')]);
    }

    public function testPandoc()
    {
        // Проверяем, есть ли Pandoc
        $pandocVersion = shell_exec('pandoc --version 2>&1');
        $whichPandoc = shell_exec('which pandoc 2>&1');
        
        return response()->json([
            'pandoc_version' => $pandocVersion,
            'pandoc_path' => $whichPandoc,
            'message' => 'Pandoc test endpoint',
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
    }

    public function zamzarWebhook(Request $request)
    {
        Log::info('Zamzar webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        $data = $request->all();
        
        // Обрабатываем challenge для верификации webhook
        if (isset($data['type']) && $data['type'] === 'webhook.challenge') {
            $token = $data['data']['token'] ?? null;
            
            Log::info('Zamzar webhook challenge received', ['token' => $token]);
            
            if ($token) {
                return response()->json(['token' => $token]);
            }
        }
        
        // Обрабатываем успешную конвертацию
        if ($data['status'] === 'successful') {
            $jobId = $data['id'];
            $fileId = $data['target_files'][0]['id'] ?? null;
            
            if ($fileId) {
                // Скачиваем готовый PDF
                $zamzarApiKey = config('services.zamzar.api_key');
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/files/' . $fileId . '/content');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_USERPWD, $zamzarApiKey . ':');
                
                $pdfContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $pdfContent) {
                    // Извлекаем имя файла из job_id (сохраняем mapping в кеше)
                    $filename = Cache::get("zamzar_job_{$jobId}");
                    
                    if ($filename) {
                        $pdfPath = storage_path('app/public/contracts/' . $filename . '.pdf');
                        
                        // Создаем директорию если не существует
                        @mkdir(dirname($pdfPath), 0775, true);
                        
                        file_put_contents($pdfPath, $pdfContent);
                        
                        Log::info('PDF saved via webhook', [
                            'job_id' => $jobId,
                            'filename' => $filename,
                            'size' => strlen($pdfContent)
                        ]);
                        
                        // Очищаем кеш
                        Cache::forget("zamzar_job_{$jobId}");
                    }
                }
            }
        }
        
        return response()->json(['status' => 'received']);
    }
}