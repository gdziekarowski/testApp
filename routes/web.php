<?php

declare(strict_types=1);

use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'index'])->middleware('signed')->name('app.panel');
Route::post('/', [ShopController::class, 'fetchShops'])->name('app.shops.fetch');
