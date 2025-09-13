<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManychatContractController;

Route::post('/manychat/contract', [ManychatContractController::class, 'generate']);
Route::get('/manychat/contract/pdf/{filename}', [ManychatContractController::class, 'getPdf']);
Route::get('/manychat/contract/docx/{filename}', [ManychatContractController::class, 'getDocx']);
Route::get('/test-pdf', [ManychatContractController::class, 'testPdf']);
Route::get('/ping', function() { return 'pong'; });