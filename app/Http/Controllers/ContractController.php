<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Jobs\GenerateContractJob;
use Mpdf\Mpdf;
use Aws\S3\S3Client;

class ContractController extends Controller
{
    private function getS3Client()
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => 'https://367be3a2035528943240074d0096e0cd.r2.cloudflare.com',
            'credentials' => [
                'key' => '039d5235dee1cf741dd74bbb3bba9932',
                'secret' => '9e6dd7c05d36e69a7e6a13b833ef5bf8cf28351',
            ],
            'use_path_style_endpoint' => true,
        ]);
    }

    public function showForm()
    {
        return view('contract-form');
    }

    public function showSimpleForm()
    {
        return view('contract-simple');
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
            
            // Сохраняем файл в S3 storage для Laravel Cloud
            $docxPath = 'contracts/'.$filename.'.docx';
            $tmpDocx = storage_path('app/temp/'.$filename.'.docx');
            @mkdir(dirname($tmpDocx), 0775, true);
            $tpl->saveAs($tmpDocx);
            
            // Генерируем PDF синхронно
            $pdfPath = $this->generatePdf($tmpDocx, $filename, $data);
            
            // Пытаемся загрузить файлы в S3, если не получается - используем локальное хранение
            $pdfS3Path = 'contracts/'.$filename.'.pdf';
            $contractUrl = url('api/contract/download/'.$filename.'.pdf');
            
            try {
                $s3Client = $this->getS3Client();
                $bucket = 'fls-9fd6221b-0ca4-45b4-8af8-343326c54146';
                
                // Загружаем DOCX
                $s3Client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $docxPath,
                    'Body' => file_get_contents($tmpDocx),
                    'ContentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]);
                
                // Загружаем PDF
                $s3Client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $pdfS3Path,
                    'Body' => file_get_contents($pdfPath),
                    'ContentType' => 'application/pdf'
                ]);
                
                // Если S3 работает, используем прямую ссылку
                $contractUrl = 'https://fls-9fd6221b-0ca4-45b4-8af8-343326c54146.laravel.cloud/' . $pdfS3Path;
                
                Log::info('Files uploaded to S3 successfully');
                
            } catch (\Exception $e) {
                // Fallback: сохраняем локально
                $localPdfPath = storage_path('app/contracts/' . $filename . '.pdf');
                @mkdir(dirname($localPdfPath), 0775, true);
                copy($pdfPath, $localPdfPath);
                
                Log::warning('S3 upload failed, using local storage', [
                    'error' => $e->getMessage()
                ]);
            }
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'docx_path' => $docxPath,
                'pdf_path' => $pdfPath
            ]);
            
            
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
        // Сначала пытаемся получить файл из S3
        try {
            $s3Client = $this->getS3Client();
            $bucket = 'fls-9fd6221b-0ca4-45b4-8af8-343326c54146';
            $s3Path = 'contracts/' . $filename;
            
            $result = $s3Client->getObject([
                'Bucket' => $bucket,
                'Key' => $s3Path,
            ]);
            
            return response($result['Body'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            // Fallback: проверяем локальное хранение
            $localPath = storage_path('app/contracts/' . $filename);
            
            if (file_exists($localPath)) {
                return response()->download($localPath, $filename, [
                    'Content-Type' => 'application/pdf'
                ]);
            }
            
            Log::error('File not found in S3 or local storage', [
                'filename' => $filename,
                's3_error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'File not found'], 404);
        }
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

    private function generatePdf($docxPath, $filename, $data = [])
    {
        // Используем mPDF для генерации PDF
        $pdfPath = storage_path('app/contracts/' . $filename . '.pdf');
        
        // Создаем директории если не существуют
        $contractsDir = dirname($pdfPath);
        $tempDir = storage_path('app/temp');
        
        if (!is_dir($contractsDir)) {
            if (!mkdir($contractsDir, 0775, true)) {
                Log::error('Failed to create contracts directory', ['path' => $contractsDir]);
                throw new \Exception('Не удалось создать директорию для PDF файлов');
            }
        }
        
        if (!is_dir($tempDir)) {
            if (!mkdir($tempDir, 0775, true)) {
                Log::error('Failed to create temp directory', ['path' => $tempDir]);
                throw new \Exception('Не удалось создать временную директорию');
            }
        }
        
        // Проверяем, что можем записать в директорию
        if (!is_writable($contractsDir)) {
            Log::error('Contracts directory is not writable', ['path' => $contractsDir]);
            throw new \Exception('Нет прав на запись в директорию PDF файлов');
        }
        
        try {
            // Создаем HTML контент для PDF
            $html = $this->generateHtmlFromDocx($docxPath, $data);
            
            // Настройки mPDF для кириллицы
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
                'margin_header' => 9,
                'margin_footer' => 9,
                'tempDir' => sys_get_temp_dir() // Используем системную временную директорию
            ]);
            
            // Устанавливаем шрифт для кириллицы
            $mpdf->SetFont('DejaVuSans');
            
            // Загружаем HTML
            $mpdf->WriteHTML($html);
            
            // Сохраняем PDF
            $mpdf->Output($pdfPath, 'F');
            
            Log::info('PDF generated successfully with mPDF', [
                'pdf_path' => $pdfPath,
                'filename' => $filename
            ]);
            
            return $pdfPath;
            
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Ошибка генерации PDF: ' . $e->getMessage());
        }
    }
    
    private function generateHtmlFromDocx($docxPath, $data = [])
    {
        // HTML шаблон для договора с улучшенным форматированием
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Договор</title>
            <style>
                body { 
                    font-family: DejaVu Sans, Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    font-size: 12px;
                    line-height: 1.4;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    font-size: 16px;
                    font-weight: bold;
                }
                .section { 
                    margin-bottom: 20px; 
                }
                .section h2 {
                    font-size: 14px;
                    font-weight: bold;
                    margin-bottom: 15px;
                    text-decoration: underline;
                }
                .columns {
                    display: table;
                    width: 100%;
                }
                .column {
                    display: table-cell;
                    width: 48%;
                    vertical-align: top;
                    padding-right: 20px;
                }
                .column:last-child {
                    padding-right: 0;
                }
                .field { 
                    margin-bottom: 8px; 
                    font-size: 11px;
                }
                .label { 
                    font-weight: bold; 
                    display: inline-block;
                    min-width: 80px;
                }
                .value {
                    display: inline-block;
                }
                h3 {
                    font-size: 13px;
                    font-weight: bold;
                    margin-bottom: 10px;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ДОГОВОР</h1>
            </div>
            
            <div class="section">
                <h2>12. Адреса, реквизиты и подписи сторон</h2>
                
                <div class="columns">
                    <div class="column">
                        <h3>Клиент:</h3>
                        <div class="field">
                            <span class="label">ФИО:</span>
                            <span class="value">' . htmlspecialchars($data['client_full_name'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">Паспорт РФ:</span>
                            <span class="value">' . htmlspecialchars($data['passport_full'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">ИНН:</span>
                            <span class="value">' . htmlspecialchars($data['inn'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">Адрес:</span>
                            <span class="value">' . htmlspecialchars($data['client_address'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">Банк:</span>
                            <span class="value">' . htmlspecialchars($data['bank_name'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">P/c:</span>
                            <span class="value">' . htmlspecialchars($data['bank_account'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">БИК:</span>
                            <span class="value">' . htmlspecialchars($data['bank_bik'] ?? '') . '</span>
                        </div>
                        <div class="field">
                            <span class="label">SWIFT:</span>
                            <span class="value">' . htmlspecialchars($data['bank_swift'] ?? '') . '</span>
                        </div>
                    </div>
                    
                    <div class="column">
                        <h3>Оператор:</h3>
                        <div class="field">
                            <span class="label">Название:</span>
                            <span class="value">ОсОО "ВТП-Технолоджи"</span>
                        </div>
                        <div class="field">
                            <span class="label">Рег. номер:</span>
                            <span class="value">305867-3301-000</span>
                        </div>
                        <div class="field">
                            <span class="label">ИНН:</span>
                            <span class="value">01007202410391</span>
                        </div>
                        <div class="field">
                            <span class="label">ОКПО:</span>
                            <span class="value">33112978</span>
                        </div>
                        <div class="field">
                            <span class="label">Юр. адрес:</span>
                            <span class="value">Кыргызская Республика, Бишкек, Первомайский район, пр. Чынгыз Айтматова, 4, Блок И., 54</span>
                        </div>
                        <div class="field">
                            <span class="label">Факт. адрес:</span>
                            <span class="value">Кыргызская Республика, Бишкек, Первомайский район, пр. Чынгыз Айтматова, 4, Блок И., 54</span>
                        </div>
                        <div class="field">
                            <span class="label">Банк:</span>
                            <span class="value">ОАО «ФинансКредитБанк»</span>
                        </div>
                        <div class="field">
                            <span class="label">P/c:</span>
                            <span class="value">1340000090402674</span>
                        </div>
                        <div class="field">
                            <span class="label">БИК:</span>
                            <span class="value">134001</span>
                        </div>
                        <div class="field">
                            <span class="label">SWIFT:</span>
                            <span class="value">FIKBKG22</span>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
