<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            data: [
                'user'         => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
            message: 'Registration successful.',
            statusCode: 201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse('Invalid email or password.', 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse(
            data: [
                'user'         => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
            message: 'Login successful.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(message: 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->successResponse(
            data: [
                'id'                    => $user->id,
                'name'                  => $user->name,
                'email'                 => $user->email,
                'daily_limit'           => 5,
                'generations_today'     => $user->dailyGenerationCount(),
                'remaining_today'       => max(0, 5 - $user->dailyGenerationCount()),
                'total_websites'        => $user->websites()->count(),
            ],
            message: 'Authenticated user details.'
        );
    }
}
