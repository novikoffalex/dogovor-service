<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpWord\TemplateProcessor;
use CloudConvert\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;

class ManychatContractController extends Controller
{
    public function generate(Request $request) 
    {
        if ($request->header('X-Auth-Token') !== config('services.manychat.token')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'client_full_name' => 'required|string|max:255',
            'passport_series'  => 'nullable|string|max:10',
            'passport_number'  => 'nullable|string|max:20',
            'passport_full'    => 'nullable|string|max:50',
            'contract_number'  => 'nullable|string|max:50',
            'inn'              => 'required|string|max:20',
            'client_address'   => 'required|string|max:500',
            'bank_name'        => 'required|string|max:255',
            'bank_account'     => 'required|string|max:64',
            'bank_bik'         => 'required|string|max:20',
            'bank_swift'       => 'nullable|string|max:20',
        ]);

        if (empty($data['passport_series']) && empty($data['passport_number']) && !empty($data['passport_full'])) {
            $full = trim((string) $data['passport_full']);
            // Normalize separators
            $full = preg_replace('/\s+/u', ' ', $full);
            if (preg_match('/^([A-Za-z0-9]{2,4})\D*([A-Za-z0-9]{3,})$/u', $full, $m)) {
                $data['passport_series'] = $m[1];
                $data['passport_number'] = $m[2];
            } else {
                // Fallback: split by first space if exists
                $parts = preg_split('/\s+/', $full, 2);
                if (count($parts) === 2) {
                    $data['passport_series'] = $parts[0];
                    $data['passport_number'] = $parts[1];
                }
            }
        }

        // Если нет отдельных полей И нет полного поля - ошибка
        if ((empty($data['passport_series']) || empty($data['passport_number'])) && empty($data['passport_full'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'passport_series' => ['The passport series field is required.'],
                    'passport_number' => ['The passport number field is required.'],
                ],
            ], 422);
        }

        if (empty($data['passport_full'])) {
            $data['passport_full'] = trim(($data['passport_series'] ?? '').' '.($data['passport_number'] ?? ''));
        }

        // Generate sequential contract number if not provided: YYYYMMDD-001..999 (never resets)
        if (empty($data['contract_number'])) {
            $today = now()->format('Ymd');
            $key = 'contract_counter_global';
            
            // Use database cache for persistence
            $cache = Cache::store('database');
            $cache->add($key, 0, now()->addYears(10));
            $current = (int) $cache->increment($key);
            
            // keep the sequence within 1..999 (wrap around after 999)
            $seq = (($current - 1) % 999) + 1;
            $data['contract_number'] = $today.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }
        
        // Генерируем дату в нужном формате на русском языке
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        $data['contract_date'] = '«' . now()->format('d') . '» ' . $months[now()->month] . ' ' . now()->format('Y') . ' г.';
        
        // Принудительно добавляем дату, если её нет в шаблоне
        $data['contract_date_force'] = $data['contract_date'];

        try {
            // Генерируем DOCX из шаблона
            $tpl = new TemplateProcessor(resource_path('contracts/contract.docx'));
            // Безопасная подстановка данных без нарушения форматирования
            foreach ($data as $k => $v) {
                // Очищаем данные от лишних символов
                $cleanValue = trim(strip_tags($v));
                // Заменяем только точные совпадения плейсхолдеров
                $tpl->setValue($k, $cleanValue);
            }

            $safeName = Str::slug($data['client_full_name'], '_');
            if ($safeName === '') {
                $safeName = 'contract';
            }
            $filename = $safeName.'_'.$data['contract_number'];
            $docxRel = 'contracts/'.$filename.'.docx';
            $pdfRel = 'contracts/'.$filename.'.pdf';
            
            $tmpDocx = storage_path('app/'.$docxRel);
            $tmpPdf = storage_path('app/'.$pdfRel);
            @mkdir(dirname($tmpDocx), 0775, true);
            
            // Сохраняем DOCX
            $tpl->saveAs($tmpDocx);
            
            // Конвертируем в PDF через CloudConvert
            try {
                Log::info('Starting CloudConvert conversion', [
                    'api_key_exists' => !empty(config('services.cloudconvert.api_key')),
                    'api_key_length' => strlen(config('services.cloudconvert.api_key')),
                ]);
                
                $cloudconvert = new CloudConvert([
                    'api_key' => config('services.cloudconvert.api_key'),
                ]);
                
                $job = (new Job())
                    ->addTask(new Task('import/upload', 'upload-my-file'))
                    ->addTask(new Task('convert', 'convert-my-file', [
                        'input' => 'upload-my-file',
                        'output_format' => 'pdf'
                    ]))
                    ->addTask(new Task('export/url', 'export-my-file', [
                        'input' => 'convert-my-file'
                    ]));
                
                $cloudconvert->jobs()->create($job);
                Log::info('CloudConvert job created');
                
                // Загружаем файл
                $uploadTask = $cloudconvert->tasks()->find('upload-my-file');
                $cloudconvert->tasks()->upload($uploadTask, file_get_contents($tmpDocx));
                Log::info('File uploaded to CloudConvert');
                
                // Ждем завершения конвертации
                $cloudconvert->jobs()->wait($job);
                Log::info('CloudConvert conversion completed');
                
                // Получаем результат
                $exportTask = $cloudconvert->tasks()->find('export-my-file');
                $result = $cloudconvert->tasks()->download($exportTask);
                
                // Сохраняем PDF
                Storage::put($pdfRel, $result, ['visibility' => 'public']);
                @unlink($tmpDocx);
                
                Log::info('PDF saved successfully');
                return response()->json(['contract_url' => Storage::url($pdfRel)]);
                
            } catch (\Exception $e) {
                Log::error('CloudConvert conversion failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Если конвертация не удалась, возвращаем DOCX
                Storage::put($docxRel, file_get_contents($tmpDocx), ['visibility' => 'public']);
                @unlink($tmpDocx);
                return response()->json(['contract_url' => Storage::url($docxRel)]);
            }
        } catch (\Throwable $e) {
            Log::error('Contract generation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'contract_generation_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
