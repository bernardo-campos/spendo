<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
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

    Route::get('etiquetas', [TagController::class, 'index'])->name('tags.index');
    Route::post('etiquetas', [TagController::class, 'store'])->name('tags.store');
    Route::get('etiquetas/crear', [TagController::class, 'create'])->name('tags.create');
});