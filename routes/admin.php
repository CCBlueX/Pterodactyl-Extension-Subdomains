<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Subdomains\Http\Controllers\AdminController;
use Subdomains\Http\Controllers\DomainController;
use Subdomains\Http\Controllers\RecordController;
use Subdomains\Http\Controllers\TemplateController;

Route::get('/', [AdminController::class, 'index']);
Route::put('/eggs', [AdminController::class, 'updateEggs']);
Route::put('/nodes/{id}', [AdminController::class, 'updateNode'])->whereNumber('id');

Route::post('/domains', [DomainController::class, 'store']);
Route::post('/domains/test', [DomainController::class, 'test']);
Route::patch('/domains/{id}', [DomainController::class, 'update'])->whereNumber('id');
Route::delete('/domains/{id}', [DomainController::class, 'destroy'])->whereNumber('id');

Route::put('/templates/{key}', [TemplateController::class, 'update'])->whereIn('key', ['default', 'minecraft']);
Route::delete('/templates/{key}', [TemplateController::class, 'reset'])->whereIn('key', ['default', 'minecraft']);
Route::get('/eggs/{id}/template', [TemplateController::class, 'egg'])->whereNumber('id');
Route::put('/eggs/{id}/template', [TemplateController::class, 'updateEgg'])->whereNumber('id');
Route::delete('/eggs/{id}/template', [TemplateController::class, 'resetEgg'])->whereNumber('id');

Route::get('/records', [RecordController::class, 'index']);
Route::delete('/records/{id}', [RecordController::class, 'destroy'])->whereNumber('id');

Route::get('/servers/{id}', [AdminController::class, 'server'])->whereNumber('id');
Route::put('/servers/{id}/limit', [AdminController::class, 'updateLimit'])->whereNumber('id');
Route::post('/servers/{id}/sync', [AdminController::class, 'sync'])->whereNumber('id');
