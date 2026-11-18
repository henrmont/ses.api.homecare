<?php

use App\Http\Controllers\PatientController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', Auth::class])
    ->prefix('homecare/users')
    ->name('users.')
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/me', 'getMe')->name('me');
        // Listagem
        Route::get('/', 'getUsers')->name('index');
        Route::get('roles', 'getRoles')->name('roles.index');

        // CRUD principal
        Route::post('/', 'createUser')->name('store');
        Route::put('{user}', 'updateUser')->name('update');
        Route::delete('{user}', 'deleteUser')->name('destroy');

        // Ações de estado e relacionamentos
        Route::patch('{user}/lock', 'lockUser')->name('lock');
        Route::patch('{user}/validate', 'validateUser')->name('validate');
        Route::patch('{user}/roles', 'rolesUser')->name('roles.update');

        // Validações assíncronas (se declaradas no Controller)
        Route::get('exists-email/{email}/{currentEmail?}', 'emailUserExists')->name('exists.email');
        Route::get('exists-cns/{cns}/{currentCns?}', 'cnsUserExists')->name('exists.cns');
    });

Route::middleware(['api', Auth::class])
    ->prefix('homecare/roles')
    ->name('roles.')
    ->controller(RoleController::class)
    ->group(function () {
        // Listagens
        Route::get('/', 'getRoles')->name('index');
        Route::get('permissions', 'getPermissions')->name('permissions.index');

        // CRUD principal
        Route::post('/', 'createRole')->name('store');
        Route::put('{role}', 'updateRole')->name('update');
        Route::delete('{role}', 'deleteRole')->name('destroy');
    });

Route::middleware(['api', Auth::class])
    ->prefix('homecare/patients')
    ->name('patients.')
    ->controller(PatientController::class)
    ->group(function () {
        // ==========================================
        // 1. RECURSO PRINCIPAL: PACIENTES (PATIENT CARES)
        // ==========================================
        Route::get('/', 'getPatients')->name('index');
        Route::get('archived', 'getArchivePatients')->name('archived');
        Route::post('/', 'createPatient')->name('store');
        Route::post('{patient_care}', 'updatePatient')->name('update'); // POST com suporte a multipart/form-data

        // Ações de Estado e Transições
        Route::patch('{patient_care}/archive', 'archivePatient')->name('archive');
        Route::patch('{patient_care}/move-from-archive', 'movePatientFromArchive')->name('move-from-archive');
        Route::patch('{patient_care}/move-from-others', 'movePatientFromOthers')->name('move-from-others');
        Route::patch('{patient_care}/validate', 'validatePatient')->name('validate');
        Route::patch('{patient_care}/finish-back', 'finishBackPatient')->name('finish-back');
    });