<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\ModulesController::class, 'livex'])->name('admin.modules.livex');
