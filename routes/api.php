<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->group(function () {

        require __DIR__.'/Api/client.php';
        require __DIR__.'/Api/admin.php';

    });
