<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Subdomains\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index']);
Route::post('/domains', [AdminController::class, 'storeDomain']);
Route::patch('/domains/{id}', [AdminController::class, 'updateDomain'])->whereNumber('id');
Route::delete('/domains/{id}', [AdminController::class, 'destroyDomain'])->whereNumber('id');
Route::put('/nodes/{id}', [AdminController::class, 'updateNode'])->whereNumber('id');
Route::get('/servers/{id}', [AdminController::class, 'server'])->whereNumber('id');
Route::put('/servers/{id}/limit', [AdminController::class, 'updateLimit'])->whereNumber('id');
Route::post('/servers/{id}/sync', [AdminController::class, 'sync'])->whereNumber('id');
