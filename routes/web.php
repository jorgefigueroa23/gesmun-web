<?php

use App\Http\Controllers\ReporteDjController;
use Illuminate\Support\Facades\Route;

// Siempre inicia en el login (si ya hay sesión, Fortify lo envía al inicio)
Route::redirect('/', '/login')->name('home');

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

    Route::get('/reportes/exportar/{formato}', [ReporteDjController::class, 'exportar'])
        ->name('reportes.exportar');

    Route::livewire('/reportes', 'pages::reportes.index')
        ->name('reportes.index');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
