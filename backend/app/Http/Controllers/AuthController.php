<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request){
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' =>['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:255']
        ]);

        $user = User::create($validated);

        return response()-> json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(Request $request){
        $this->normalizeEmail($request);

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()-> noContent();
    }

    public function me(Request $request){
        return $request->user();
    }

    /**
     * "Test@Example.com " and "test@example.com" should be the same account,
     * so trim spaces and lowercase the email before we use it.
     */
    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
    }

}
