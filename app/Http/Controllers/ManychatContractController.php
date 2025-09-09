<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;

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
            'inn'              => 'required|string|max:20',
            'client_address'   => 'required|string|max:500',
            'bank_name'        => 'required|string|max:255',
            'bank_account'     => 'required|string|max:64',
            'bank_bik'         => 'required|string|max:20',
            'bank_swift'       => 'nullable|string|max:20',
        ]);

        // If only one combined passport field is provided, try to split it
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

        // Final guard: require that after parsing we have separate fields
        if (empty($data['passport_series']) || empty($data['passport_number'])) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'passport_series' => ['The passport series field is required.'],
                    'passport_number' => ['The passport number field is required.'],
                ],
            ], 422);
        }

        try {
            $tpl = new TemplateProcessor(resource_path('contracts/Exchange_dogovor.docx'));
            foreach ($data as $k => $v) {
                $tpl->setValue($k, $v);
            }

            $rel = 'contracts/contract_'.now()->format('Ymd_His').'.docx';
            $tmp = storage_path('app/'.$rel);
            @mkdir(dirname($tmp), 0775, true);
            $tpl->saveAs($tmp);

            Storage::put($rel, file_get_contents($tmp), ['visibility' => 'public']);
            return response()->json(['contract_url' => Storage::url($rel)]);
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
