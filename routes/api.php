<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ServerController;

Route::get('/ping', function () {
    return 'pong';
});

// Contract generation API without sessions
Route::post('/contract/generate', [ContractController::class, 'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);

Route::get('/contract/download/{filename}', [ContractController::class, 'download']);
Route::get('/contract/download-pdf/{filename}', [ContractController::class, 'downloadPdf']);
Route::get('/contract/download-signed/{filename}', [ContractController::class, 'downloadSigned']);
Route::get('/contract/check-pdf-status/{filename}', [ContractController::class, 'checkPdfStatus']);

// Upload and ManyChat integration
Route::post('/contract/upload-signed', [ContractController::class, 'uploadSigned'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/contract/send-to-manychat', [ContractController::class, 'sendToManychat'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Server management API
Route::prefix('server')->group(function () {
    Route::get('/status', [ServerController::class, 'getServerStatus']);
    Route::post('/setup-counter', [ServerController::class, 'setupContractCounter']);
    Route::post('/execute', [ServerController::class, 'executeCommand']);
});