<?php

declare(strict_types=1);

use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// Wejście z panelu IdoSell: webhook `launch` (SDK) zwraca podpisany URL do tej trasy.
Route::get('/', [ShopController::class, 'index'])->middleware('signed')->name('app.panel');

// „Pokaż sklepy”: formularz wysyła POST na czasowo podpisany URL; podpis weryfikuje kontroler.
Route::post('/', [ShopController::class, 'fetchShops'])->name('app.shops.fetch');
