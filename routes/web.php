<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('adminlte::page');
})->middleware('auth');