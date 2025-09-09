<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'passport_series'  => 'required|string|max:10',
            'passport_number'  => 'required|string|max:20',
            'inn'              => 'required|string|max:20',
            'client_address'   => 'required|string|max:500',
            'bank_name'        => 'required|string|max:255',
            'bank_account'     => 'required|string|max:64',
            'bank_bik'         => 'required|string|max:20',
            'bank_swift'       => 'nullable|string|max:20',
        ]);

        $tpl = new TemplateProcessor(resource_path('contracts/Exchange_dogovor.docx'));
        foreach ($data as $k => $v) {
            $tpl->setValue($k, $v);
        }

        $rel = 'contracts/contract_'.now()->format('Ymd_His').'.docx';
        $tmp = storage_path('app/'.$rel);
        @mkdir(dirname($tmp), 0775, true);
        $tpl->saveAs($tmp);

        Storage::disk('public')->put($rel, file_get_contents($tmp));
        return response()->json(['contract_url' => Storage::disk('public')->url($rel)]);
    }
}
