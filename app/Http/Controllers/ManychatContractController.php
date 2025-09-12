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
        
        // Генерируем DOCX
        $this->generateDocxOnly($data, $docxRel);
        
        // Отправляем задачу в очередь для PDF конвертации (если нужно)
        try {
            GenerateContractJob::dispatch($data);
            Log::info('PDF conversion queued', ['filename' => $filename]);
        } catch (\Exception $e) {
            Log::warning('PDF conversion queue failed', ['error' => $e->getMessage()]);
        }
        
        Log::info('Contract generated', [
            'filename' => $filename,
            'url' => Storage::url($docxRel)
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
}