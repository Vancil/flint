<?php
declare(strict_types=1);

namespace App\Controllers;

use Flint\Request;
use Flint\Response;
use App\Models\User;

class UserController
{
    public function index(): Response
    {
        return Response::json(User::all());
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        return Response::json(User::create($data), 201);
    }

    public function show(int $id): Response
    {
        return Response::json(User::findOrFail($id)->toArray());
    }

    public function destroy(int $id): Response
    {
        User::findOrFail($id)->delete();
        return Response::noContent();
    }
}
