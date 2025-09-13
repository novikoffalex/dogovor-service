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
            
            // Сохраняем файл в public storage для скачивания
            $publicPath = 'contracts/'.$filename.'.docx';
            $tmpDocx = storage_path('app/public/'.$publicPath);
            @mkdir(dirname($tmpDocx), 0775, true);
            $tpl->saveAs($tmpDocx);
            
            // Запускаем PDF конвертацию в фоне
            GenerateContractJob::dispatch($data, $docxRel, $filename)->onQueue('pdf-conversion');
            
            Log::info('Contract generated successfully', [
                'filename' => $filename,
                'public_path' => $publicPath
            ]);
            
            // Возвращаем JSON с ссылкой на файл
            $contractUrl = url('storage/'.$publicPath);
            
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

    public function testPdf()
    {
        return response()->json(['message' => 'PDF endpoint works!', 'timestamp' => now()->format('Y-m-d H:i:s')]);
    }
}