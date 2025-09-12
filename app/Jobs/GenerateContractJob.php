<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;

class GenerateContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        try {
            Log::info('Starting contract generation job', $this->data);
            
            // Генерируем DOCX из шаблона
            $tpl = new TemplateProcessor(resource_path('contracts/contract.docx'));
            
            // Ограничиваем длину полей
            foreach ($this->data as $k => $v) {
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

            $safeName = Str::slug($this->data['client_full_name'], '_');
            if ($safeName === '') {
                $safeName = 'contract';
            }
            $filename = $safeName.'_'.$this->data['contract_number'];
            $docxRel = 'contracts/'.$filename.'.docx';
            $pdfRel = 'contracts/'.$filename.'.pdf';
            
            $tmpDocx = storage_path('app/'.$docxRel);
            $tmpPdf = storage_path('app/'.$pdfRel);
            @mkdir(dirname($tmpDocx), 0775, true);
            
            // Сохраняем DOCX
            $tpl->saveAs($tmpDocx);
            
            // Конвертируем в PDF через CloudConvert API
            try {
                $apiKey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiZjA3NzgwYmZiZDczMmJkMzJkMzMwYWM0MWFjNTQyMjlmYWQyZjI4Y2FjZjFhYTI1MDliMTAwMzdiYmU0YjdkMzBmMDBmNTIzMDAyN2NkMWUiLCJpYXQiOjE3NTc1NzU1NDEuMzUxMTExLCJuYmYiOjE3NTc1NzU1NDEuMzUxMTEyLCJleHAiOjQ5MTMyNDkxNDEuMzQ1NjY3LCJzdWIiOiI3Mjg4NDE5MiIsInNjb3BlcyI6W119.HsjN-uJG6vBr0DIhI1hukgtJS0yxnjMlhMO6SdKuKLYUHLEITInscRRwSwU_L0s4TFRvx0Nqjs25BV96NeRJM8iuxdSCxWUU5No20EDjs8PHyzSOOjLGNxyzOx30wyuI_viA03jhLiwoaaKIY-ls52kQ3ExPvzA7NiuMNxDcJUlUEPysG6cZGfXphu9tOCwbrd3Ar7S-AzPKI4MRfyLmPYRZvYuqg_sUAQ2zoQk6ukexBIHGXD6VD73ZZ8Gwige6ZEtbplJ5ky8ddn9JEiIxqom4fzzqVC45yg3nZtFs76lfYjx-lKCf3KT1aedywpif_FvooyuKywxns7sNEBzCU8E13LpdCNHUKQ40C3zKxbi9n6VX39cxV42eNWUhL97iBChInBcZbRaquYwPUJi5HVoDQK9SCmpyIAfGokiGO-rV-_TiAuh6fXCl4HJ_9gT4buLt2fReSMLzh0_PrtdQPlR2JXKeIotVU6Hy_WedgefF0eoIkAGk14klm-uwY7yfwqCoJlWD2nCJP454qGqCGAd7rac3zHDZxJJWuURAHs0FQlrue02ik7EKtqElXoU_TGV7d_nfy5l7wAFH-Vm2PbtI3cbldIyc4yLabjxMzrSyrE_nOuQMG9Zem1iiCp4lEzOmZjvD54wVoaBlDCI6DYLfW8wTYeTCW9AM7y2JpeE';
                
                // Создаем задачу в CloudConvert
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.cloudconvert.com/v2/jobs');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ]);
                
                $jobData = [
                    'tasks' => [
                        'upload-my-file' => [
                            'operation' => 'import/upload'
                        ],
                        'convert-my-file' => [
                            'operation' => 'convert',
                            'input' => 'upload-my-file',
                            'output_format' => 'pdf'
                        ],
                        'export-my-file' => [
                            'operation' => 'export/url'
                        ]
                    ]
                ];
                
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jobData));
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 || $httpCode === 201) {
                    $jobResponse = json_decode($response, true);
                    $jobId = $jobResponse['data']['id'];
                    $uploadUrl = $jobResponse['data']['tasks'][0]['result']['upload_url'];
                    
                    // Загружаем файл
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_PUT, true);
                    curl_setopt($ch, CURLOPT_INFILE, fopen($tmpDocx, 'r'));
                    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($tmpDocx));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ]);
                    
                    $uploadResponse = curl_exec($ch);
                    $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($uploadHttpCode !== 200) {
                        throw new \Exception('Failed to upload file to CloudConvert: ' . $uploadResponse);
                    }
                    
                    // Ждем завершения конвертации (до 60 секунд)
                    $maxWaitTime = 60;
                    $waitTime = 0;
                    
                    while ($waitTime < $maxWaitTime) {
                        sleep(3);
                        $waitTime += 3;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://api.cloudconvert.com/v2/jobs/{$jobId}");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $apiKey
                        ]);
                        
                        $statusResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $status = json_decode($statusResponse, true);
                        
                        if ($status['data']['status'] === 'finished') {
                            // Скачиваем PDF
                            $downloadUrl = $status['data']['tasks'][2]['result']['files'][0]['url'];
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Authorization: Bearer ' . $apiKey
                            ]);
                            
                            $pdfContent = curl_exec($ch);
                            curl_close($ch);
                            
                            if ($pdfContent) {
                                // Сохраняем PDF
                                Storage::disk('public')->put($pdfRel, $pdfContent);
                                @unlink($tmpDocx);
                                
                                Log::info('PDF generated successfully via CloudConvert', ['url' => Storage::url($pdfRel)]);
                                return;
                            }
                        }
                        
                        if ($status['data']['status'] === 'error') {
                            Log::error('CloudConvert conversion failed', ['error' => $status['data']]);
                            throw new \Exception('CloudConvert conversion failed');
                        }
                    }
                    
                    throw new \Exception('CloudConvert conversion timeout');
                } else {
                    throw new \Exception('Failed to create CloudConvert job: ' . $response);
                }
                
            } catch (\Exception $e) {
                Log::error('CloudConvert conversion failed, saving DOCX', ['message' => $e->getMessage()]);
                
                // Если конвертация не удалась, сохраняем DOCX
                Storage::put($docxRel, file_get_contents($tmpDocx), ['visibility' => 'public']);
                @unlink($tmpDocx);
            }
            
        } catch (\Throwable $e) {
            Log::error('Contract generation job failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}