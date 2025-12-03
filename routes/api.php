<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\SchoolController;

Route::get('/foundations', [FoundationController::class, 'index']);
Route::get('/foundations/{slug}', [FoundationController::class, 'show']);

Route::get('/schools', [SchoolController::class, 'index']);
Route::get('/schools/{slug}', [SchoolController::class, 'show']);