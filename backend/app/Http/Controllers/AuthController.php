<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

    public function login(Request $request){
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if(! $user || ! Hash::check($validated['password'], $user->password)){
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.']
            ]);
        }

        return response()->json([
                'user' => $user,
                'token' => $user->createToken('api')->plainTextToken,
            ]);
    }
}
