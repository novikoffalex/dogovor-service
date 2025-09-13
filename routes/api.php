<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContractController;

Route::get('/ping', function () {
    return 'pong';
});

// Contract generation API without sessions
Route::post('/contract/generate', [ContractController::class, 'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);

Route::get('/contract/download/{filename}', [ContractController::class, 'download']);