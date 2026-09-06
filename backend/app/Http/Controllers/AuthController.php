<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request){
        $validated = $request->validate([
            'name' =>['required', 'string'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8']
        ]);

        $user = User::create($validated);

        return response()-> json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);


    }
}
