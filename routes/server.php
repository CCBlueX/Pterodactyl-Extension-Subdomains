<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Subdomains\Http\Controllers\ServerController;

Route::get('/', [ServerController::class, 'index']);
Route::post('/', [ServerController::class, 'store']);
Route::delete('/{id}', [ServerController::class, 'destroy'])->whereNumber('id');
