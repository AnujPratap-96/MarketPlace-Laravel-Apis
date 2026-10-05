<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = isset($validated['role']) ? UserRole::from($validated['role']) : UserRole::CUSTOMER;

        $user = DB::transaction(function () use ($validated, $role) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $role,
            ]);

            if ($role === UserRole::VENDOR) {
                Vendor::create([
                    'user_id' => $user->id,
                    'store_name' => $validated['store_name'],
                    'slug' => Str::slug($validated['store_name']) . '-' . Str::random(5),
                    'commission_rate' => 10.00,
                    'balance_in_cents' => 0,
                    'is_verified' => false,
                ]);
            }

            return $user;
        });

        $token = $user->createToken('auth_token', ['role:' . $role->value])->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'vendor' => $user->vendor,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::with('vendor')->where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Revoke old tokens optionally for security
        $user->tokens()->delete();

        $token = $user->createToken('auth_token', ['role:' . $user->role->value])->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'vendor' => $user->vendor,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], Response::HTTP_OK);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('vendor');

        return response()->json([
            'user' => $user,
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], Response::HTTP_OK);
    }
}
