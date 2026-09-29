<?php

declare(strict_types=1);

use App\Http\Controllers\ShopController;
use App\Http\Middleware\LogPanelRequest;
use Illuminate\Support\Facades\Route;

// Strona poza strefą panelu: bez kontekstu sprzedawcy, bez middleware `idosell.panel`.
Route::view('/', 'start')->name('home');

/*
 * Strefa panelu aplikacji (iframe w panelu IdoSell).
 *
 * `idosell.panel` (SDK) wpuszcza tylko z podpisanym URL i aktywną licencją, inaczej 403.
 * Webhook `launch` przekierowuje na `app.panel`; kolejne linki i formularze budujemy
 * przez `idosell_route()`, więc kontekst przechodzi bez cookies. `LogPanelRequest` stoi
 * przed `idosell.panel`, żeby logować również odrzucone wejścia.
 */
Route::middleware([LogPanelRequest::class, 'idosell.panel'])
    ->prefix('panel')
    ->name('app.')
    ->group(function (): void {
        Route::get('/', [ShopController::class, 'index'])->name('panel');
        Route::post('/sklepy', [ShopController::class, 'fetchShops'])->name('shops.fetch');
        Route::get('/instalacja', [ShopController::class, 'installation'])->name('installation');
    });
