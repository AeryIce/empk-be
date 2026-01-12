<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\SchoolController;

Route::get('/foundations', [FoundationController::class, 'index']);
Route::get('/foundations/{slug}', [FoundationController::class, 'show']);

Route::get('/schools', [SchoolController::class, 'index']);
Route::get('/schools/{slug}', [SchoolController::class, 'show']);

// Publikasi (Berita / Event)
Route::get('/berita', [\App\Http\Controllers\Api\NewsPostController::class, 'index']);
Route::get('/berita/{slug}', [\App\Http\Controllers\Api\NewsPostController::class, 'show']);
