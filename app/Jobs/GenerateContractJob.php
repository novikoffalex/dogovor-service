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
            
            // Конвертируем в PDF через Zamzar API
            try {
                $apiKey = '4bb76644955076ff4def01f10b50e2ad7c0e4b00';
                
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
                    
                    // Ждем завершения конвертации (до 60 секунд)
                    $maxWaitTime = 60;
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
                            
                            // Сохраняем PDF
                            Storage::put($pdfRel, $pdfContent, ['visibility' => 'public']);
                            @unlink($tmpDocx);
                            
                            Log::info('PDF generated successfully', ['url' => Storage::url($pdfRel)]);
                            return;
                        }
                        
                        if ($status['status'] === 'failed') {
                            throw new \Exception('Zamzar conversion failed');
                        }
                    }
                    
                    throw new \Exception('Zamzar conversion timeout');
                } else {
                    throw new \Exception('Failed to create Zamzar job: ' . $response);
                }
                
            } catch (\Exception $e) {
                Log::error('Zamzar conversion failed, saving DOCX', ['message' => $e->getMessage()]);
                
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