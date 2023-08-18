<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/tokens/create', function (Request $request) {

    $token = $request->user()->createToken($request->token_name);

    return ['token' => $token->plainTextToken];

})->name("api.tokens.create");

Route::post('/send', [\App\Http\Controllers\Controller::class, "send"])->name("send");

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/
