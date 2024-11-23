<?php

use Illuminate\Support\Facades\Route;

Route::get(
    '/', function () {
        return view('index');
    }
);

Route::get(
    '/lookup', function () {
        return view('lookup');
    }
);
