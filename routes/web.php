<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [\App\Http\Controllers\Controller::class, "home"])->name("home");
Route::get('/input', [\App\Http\Controllers\Controller::class, "input"])->name("input");
Route::get('/getToken', [\App\Http\Controllers\TokenController::class, "getToken"])->name("getToken");
Route::get('/getToken2', [\App\Http\Controllers\TokenController::class, "getToken2"])->name("getToken2");
Route::get('/saveToken', [\App\Http\Controllers\TokenController::class, "saveToken"])->name("saveToken");

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
