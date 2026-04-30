<?php

use App\Http\Controllers\UserController;
use App\Http\Middleware\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', Auth::class])
    ->prefix('user')
    ->controller(UserController::class)
    ->group(function () {
        Route::get('get-users', 'getUsers');
        Route::get('get-roles', 'getRoles');
        Route::post('create-user', 'createUser');
        Route::patch('lock-user/{user}', 'lockUser');
        Route::patch('validate-user/{user}', 'validateUser');
        Route::patch('update-user/{user}', 'updateUser');
        Route::delete('delete-user/{user}', 'deleteUser');
        Route::patch('roles-user/{user}', 'rolesUser');
    });
