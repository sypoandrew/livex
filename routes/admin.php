<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModuleController;

Route::get('livex', [ModuleController::class, 'index'])->name('admin.modules.livex');
Route::post('livex', [ModuleController::class, 'update'])->name('admin.modules.livex');
Route::get('livex/heartbeat', [ModuleController::class, 'heartbeat'])->name('admin.modules.livex.heartbeat');
Route::get('livex/search_market', [ModuleController::class, 'search_market'])->name('admin.modules.livex.search_market');
