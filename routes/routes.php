<?php
use Illuminate\Support\Facades\Route;
use Sypo\Livex\Http\Controllers\OrderPushController;

Route::get('livex/order/push', '\Sypo\Livex\Http\Controllers\OrderPushController@ping')->name('livex.orderpush.ping');
Route::post('livex/order/push', '\Sypo\Livex\Http\Controllers\OrderPushController@post')->name('livex.orderpush.post');
