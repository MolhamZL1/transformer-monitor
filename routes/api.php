<?php

use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::post('/telemetry', [TelemetryController::class, 'store']);