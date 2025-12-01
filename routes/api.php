<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FoundationController;

Route::get('/foundations', [FoundationController::class, 'index']);
Route::get('/foundations/{slug}', [FoundationController::class, 'show']);
