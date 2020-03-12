<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModuleController;

Route::get('livex', [ModuleController::class, 'index'])->name('admin.modules.livex');
Route::post('livex', [ModuleController::class, 'update'])->name('admin.modules.livex');
