<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManychatContractController;
use App\Http\Controllers\ContractController;

Route::get('/', function () {
    return view('welcome');
});

// Static form without sessions
Route::get('/form', function () {
    return response()->view('contract-minimal')->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);
});

// Ultra simple form
Route::get('/ultra', function () {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Договор</title></head><body><h1>Формирование договора</h1><form id="f"><input name="client_full_name" placeholder="ФИО" value="Тест" required><br><br><input name="passport_full" placeholder="Паспорт" value="1234 567890" required><br><br><input name="inn" placeholder="ИНН" value="123456789012" required><br><br><input name="client_address" placeholder="Адрес" value="Москва" required><br><br><input name="bank_name" placeholder="Банк" value="Сбербанк" required><br><br><input name="bank_account" placeholder="Счет" value="12345678901234567890" required><br><br><input name="bank_bik" placeholder="БИК" value="123456789" required><br><br><button type="submit">Сформировать</button></form><div id="r"></div><script>document.getElementById("f").onsubmit=async function(e){e.preventDefault();const d=Object.fromEntries(new FormData(this));try{const r=await fetch("/api/contract/generate",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)});const j=await r.json();if(j.success)document.getElementById("r").innerHTML="<a href="+j.contract_url+" target=_blank>Скачать</a>";else document.getElementById("r").innerHTML="Ошибка";}catch(e){document.getElementById("r").innerHTML="Ошибка сети";}};</script></body></html>';
});

// Contract form landing page
Route::get('/contract', [ContractController::class, 'showForm'])->name('contract.form');
Route::get('/contract-minimal', [ContractController::class, 'showMinimalForm'])->name('contract.minimal');
Route::get('/contract-simple', [ContractController::class, 'showSimpleForm'])->name('contract.simple');

// Contract generation API
Route::post('/api/contract/generate', [ContractController::class, 'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/api/contract/download/{filename}', [ContractController::class, 'download']);
Route::post('/api/contract/upload-signed', [ContractController::class, 'uploadSigned'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Diagnostics and fallback if API routes aren't loading
Route::get('/api/ping', fn () => 'pong from web');

// Fallback POST route without CSRF to mirror API endpoint
Route::post('/api/manychat/contract', [ManychatContractController::class, 'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('api');


// File download routes
Route::get('/api/manychat/contract/pdf/{filename}', [ManychatContractController::class, 'getPdf']);
Route::get('/api/manychat/contract/docx/{filename}', [ManychatContractController::class, 'getDocx']);

// Zamzar webhook
Route::post('/api/zamzar/webhook', [ContractController::class, 'zamzarWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
