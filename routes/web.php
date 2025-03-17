<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('adminlte::page');
})->middleware('auth');

Route::middleware('auth')->group(function () {

    Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
    Route::get('usuarios/{user}', [UserController::class, 'edit'])->name('users.edit');
    Route::put('usuarios/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categorias/crear', [CategoryController::class, 'create'])->name('categories.create');
});