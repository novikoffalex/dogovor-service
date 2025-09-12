<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManychatContractController;

Route::post('/manychat/contract', [ManychatContractController::class, 'generate']);
Route::post('/manychat/contract-pdf', [ManychatContractController::class, 'generatePdf']);
Route::get('/ping', fn() => 'pong');