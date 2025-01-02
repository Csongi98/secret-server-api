<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecretController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/secret', [SecretController::class, 'addSecret']);
Route::get('/secret/{hash}', [SecretController::class, 'getSecretByHash']);

Route::get('/secrets', [SecretController::class, 'getPaginatedSecrets']);
Route::get('/secrets/search', [SecretController::class, 'searchSecrets']);
Route::get('/secrets/export', [SecretController::class, 'exportSecrets']);
Route::get('/secrets/{id}/logs', [SecretController::class, 'getAuditLogs']);