<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        \Illuminate\Support\Facades\Log::info('LOGIN DEBUG credentials', $credentials);

        \Illuminate\Support\Facades\Log::info('LOGIN DEBUG db', [
            'connection' => config('database.default'),
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
            'total_users' => \App\Models\User::count(),
        ]);

        $user = User::where('email', $credentials['email'])->first();

        \Illuminate\Support\Facades\Log::info('LOGIN DEBUG result', [
            'user_found' => $user ? $user->email : null,
            'hash_check' => $user ? Hash::check($credentials['password'], $user->password) : null,
        ]);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
