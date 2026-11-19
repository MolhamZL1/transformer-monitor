<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data']);
Route::post('/transformers/{transformer}/assign', [DashboardController::class, 'assign']);
Route::get('/transformers/{transformer}/history', [DashboardController::class, 'history']);
Route::delete('/transformers/{transformer}', [DashboardController::class, 'destroy']);