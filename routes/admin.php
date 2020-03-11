<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModulesController;

Route::get('/', [ModulesController::class, 'index'])->name('admin.modules.livex');
