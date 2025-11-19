<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Central App Routes (app.myapp.com ou www.myapp.com)
|--------------------------------------------------------------------------
|
| Rotas da aplicação central (landing page, registro de tenants, pricing).
| Estas rotas NÃO devem ter tenant context inicializado.
|
*/

// Landing page
Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Pricing page
Route::get('/pricing', function () {
    return Inertia::render('pricing');
})->name('pricing');

// Login é gerenciado pelo Laravel Fortify (não precisa definir aqui)

// Register tenant + user (será implementado em RegisterController)
// POST /register será adicionado quando criar o controller
