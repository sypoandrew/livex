<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModuleController;

Route::get('livex', [ModuleController::class, 'index'])->name('admin.modules.livex');
Route::post('livex', [ModuleController::class, 'update'])->name('admin.modules.livex');
Route::get('livex/heartbeat', [ModuleController::class, 'heartbeat'])->name('admin.modules.livex.heartbeat');
Route::get('livex/placeholder_image', [ModuleController::class, 'placeholder_image'])->name('admin.modules.livex.placeholder_image');
Route::get('livex/replace_default_image', [ModuleController::class, 'replace_default_image'])->name('admin.modules.livex.replace_default_image');
Route::get('livex/search_market', [ModuleController::class, 'search_market'])->name('admin.modules.livex.search_market');
Route::get('livex/download_image_report', [ModuleController::class, 'download_image_report'])->name('admin.modules.livex.download_image_report');
