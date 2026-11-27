<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Loaded by bootstrap/app.php with the "web" middleware group (session,
| CSRF protection, etc.).
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::group(['middleware' => ['auth']], function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::get('iamadmin', [HomeController::class, 'adminTest'])->middleware('role.admin');
    Route::get('iamuser', [HomeController::class, 'userTest'])->middleware('role.user');
});
