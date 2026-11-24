<?php

use App\Http\Controllers\PanelActivationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', fn() => response('ok', 200))->name('healthz');

Route::get('/admin/activate', [PanelActivationController::class, 'show'])->name('panel.activation');
Route::post('/admin/activate', [PanelActivationController::class, 'store'])
    ->middleware('throttle:5,1')->name('panel.activation.store');
