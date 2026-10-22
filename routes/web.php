<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Versión anterior:
// Route::livewire('/usuarios', 'pages::usuarios.index')
//     ->name('usuarios.index');

Route::middleware(['auth'])->group(function () {
    Route::livewire('/usuarios', 'pages::usuarios.index')
        ->name('usuarios.index');

    Route::livewire('/declaraciones', 'pages::declaraciones.index')
        ->name('declaraciones.index');

    Route::livewire('/sesiones', 'pages::sesiones.index')
        ->name('sesiones.index');

    Route::livewire('/reportes', 'pages::reportes.index')
        ->name('reportes.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
