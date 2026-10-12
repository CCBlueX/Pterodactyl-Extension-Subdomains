<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Subdomains\Http\Controllers\ServerController;

Route::get('/', [ServerController::class, 'index']);
Route::post('/', [ServerController::class, 'store']);
Route::post('/domains/{id}/name', [ServerController::class, 'newName'])->whereNumber('id');
Route::patch('/{id}', [ServerController::class, 'update'])->whereNumber('id');
Route::delete('/{id}', [ServerController::class, 'destroy'])->whereNumber('id');
