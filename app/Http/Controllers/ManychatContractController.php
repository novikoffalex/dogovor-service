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
            
            // Запускаем PDF конвертацию в фоне
            GenerateContractJob::dispatch($data, $docxRel, $filename)->onQueue('pdf-conversion');
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'temp_path' => $tmpDocx
            ]);
            
            // Пробуем конвертировать в PDF через Zamzar
            try {
                $zamzarApiKey = config('services.zamzar.api_key');
                if ($zamzarApiKey) {
                    $pdfPath = storage_path('app/temp_'.$filename.'.pdf');
                    
                    // Создаем задачу конвертации в Zamzar
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/jobs');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_USERPWD, $zamzarApiKey . ':');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'source_file' => $tmpDocx,
                        'target_format' => 'pdf'
                    ]));
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    
                    if ($httpCode === 200) {
                        $job = json_decode($response, true);
                        $jobId = $job['id'];
                        
                        Log::info('Zamzar job created', ['job_id' => $jobId]);
                        
                        // Ждем завершения конвертации (максимум 60 секунд)
                        $maxWaitTime = 60;
                        $waitTime = 0;
                        
                        while ($waitTime < $maxWaitTime) {
                            sleep(3);
                            $waitTime += 3;
                            
                            curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/jobs/' . $jobId);
                            curl_setopt($ch, CURLOPT_POST, 0);
                            
                            $statusResponse = curl_exec($ch);
                            $status = json_decode($statusResponse, true);
                            
                            if ($status['status'] === 'successful') {
                                // Скачиваем готовый PDF
                                $fileId = $status['target_files'][0];
                                
                                curl_setopt($ch, CURLOPT_URL, 'https://api.zamzar.com/v1/files/' . $fileId . '/content');
                                curl_setopt($ch, CURLOPT_POST, 0);
                                
                                $pdfContent = curl_exec($ch);
                                file_put_contents($pdfPath, $pdfContent);
                                
                                curl_close($ch);
                                
                                if (file_exists($pdfPath)) {
                                    @unlink($tmpDocx);
                                    
                                    Log::info('PDF generated successfully via Zamzar', [
                                        'filename' => $filename,
                                        'size' => filesize($pdfPath)
                                    ]);
                                    
                                    return response()->download($pdfPath, $filename.'.pdf', [
                                        'Content-Type' => 'application/pdf'
                                    ])->deleteFileAfterSend(true);
                                }
                            } elseif ($status['status'] === 'failed') {
                                Log::error('Zamzar conversion failed', ['status' => $status]);
                                break;
                            }
                        }
                        
                        curl_close($ch);
                        Log::warning('Zamzar conversion timeout, returning DOCX');
                    } else {
                        Log::error('Zamzar job creation failed', ['response' => $response]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Zamzar PDF conversion failed', [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Если PDF конвертация не удалась, возвращаем DOCX
            return response()->download($tmpDocx, $filename.'.docx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ])->deleteFileAfterSend(true);
            
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
}