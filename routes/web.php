<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManychatContractController;

Route::get('/', function () {
    return view('welcome');
});

// Diagnostics and fallback if API routes aren't loading
Route::get('/api/ping', fn () => 'pong from web');

// Fallback POST route without CSRF to mirror API endpoint
Route::post('/api/manychat/contract', [ManychatContractController::class, 'generate'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('api');
