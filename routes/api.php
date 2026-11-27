<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Loaded by bootstrap/app.php with the "api" prefix and middleware group.
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('requestreset', [AuthController::class, 'sendResetEmail']);
    Route::post('reset', [AuthController::class, 'reset']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});

Route::group(['middleware' => ['auth:api']], function () {
    Route::post('iamadmin', [HomeController::class, 'adminTest'])->middleware('role.admin');
    Route::post('iamuser', [HomeController::class, 'userTest'])->middleware('role.user');
});
