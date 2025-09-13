<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManychatContractController;
use App\Http\Controllers\ContractController;

Route::get('/', function () {
    return view('welcome');
});

// Contract form landing page
Route::get('/contract', [ContractController::class, 'showForm'])->name('contract.form');

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

// Webhook route for Zamzar
Route::post('/api/zamzar/webhook', [ManychatContractController::class, 'zamzarWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('api');

// File download routes
Route::get('/api/manychat/contract/pdf/{filename}', [ManychatContractController::class, 'getPdf']);
Route::get('/api/manychat/contract/docx/{filename}', [ManychatContractController::class, 'getDocx']);
