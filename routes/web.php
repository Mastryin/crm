<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // Check if application is installed
    $lockFile = storage_path('installed');

    if (file_exists($lockFile)) {
        // Redirect to admin panel if installed
        return redirect('/admin');
    } else {
        // Redirect to installer if not installed
        return redirect('/install.php');
    }
});
