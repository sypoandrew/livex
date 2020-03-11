<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModulesController;

Route::get('livex', [ModulesController::class, 'index'])->name('admin.modules.livex');
Route::post('livex', [ModulesController::class, 'update'])->name('admin.modules.livex');
