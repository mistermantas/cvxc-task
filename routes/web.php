<?php

use App\Http\Controllers\CustomerImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CustomerImportController::class, 'index'])->name('home');
Route::post('/imports/preview', [CustomerImportController::class, 'preview'])->name('imports.preview');
Route::post('/imports', [CustomerImportController::class, 'store'])->name('imports.store');
Route::post('/imports/cancel', [CustomerImportController::class, 'cancel'])->name('imports.cancel');
