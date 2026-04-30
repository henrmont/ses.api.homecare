<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function getUsers()
    {
        $this->authorize('homecare/usuário listar');
        $users = User::query()
            ->homecare()
            ->with(['modules' => function($q) {
                $q->where('name','homecare');
            },
            'roles','professional'])
            ->whereNot('email','admin@homecare.com')
            ->orderBy('id','desc')
            ->get();
        return response()->json($users, 200);
    }

    public function getRoles()
    {
        $this->authorize('homecare/usuário listar');
        $roles = Role::query()
            ->with('permissions')
            ->where('name','LIKE','homecare%')
            ->get();
        return response()->json($roles, 200);
    }

    public function createUser(Request $request, UserService $userService)
    {
        $this->authorize('homecare/usuário criar');
        return $userService->createUser($request);
    }

    public function lockUser(User $user, UserService $userService)
    {
        $this->authorize('tfd/usuário travar');
        return $userService->lockUser($user);
    }

    public function validateUser(User $user, UserService $userService)
    {
        $this->authorize('tfd/usuário validar');
        return $userService->validateUser($user);
    }

    public function updateUser(User $user, Request $request, UserService $userService)
    {
        $this->authorize('tfd/usuário atualizar');
        return $userService->updateUser($user, $request);
    }

    public function deleteUser(User $user, UserService $userService)
    {
        $this->authorize('tfd/usuário deletar');
        return $userService->deleteUser($user);
    }

    public function rolesUser(User $user, Request $request, UserService $userService)
    {
        $this->authorize('tfd/usuário atualizar');
        return $userService->rolesUser($user, $request);
    }

    // validators
    public function emailUserExists($email, $data)
    {
        $this->authorize('homecare/usuário listar');
        $exists = User::query()
            ->homecare()
            ->where('email', $email);
        if ($data)
            $exists->whereNot('email', $data);
        $exists = $exists->exists();
        return response()->json($exists, 200);
    }

    public function cnsUserExists($cns, $data)
    {
        $this->authorize('homecare/usuário listar');
        $exists = Professional::query()
            ->where('cns', $cns);
        if ($data)
            $exists->whereNot('cns', $data);
        $exists = $exists->exists();
        return response()->json($exists, 200);
    }
}
