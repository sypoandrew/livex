<?php

use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\ModulesController;

Route::get('/', [ModulesController::class, 'livex'])->name('admin.modules.livex');
