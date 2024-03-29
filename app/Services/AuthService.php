<?php

namespace App\Services;

use App\Http\Resources\Strategies\LoginResponseStrategy;
use App\Http\Resources\Strategies\LogoutResponseStrategy;
use App\Http\Resources\UserResource;
use App\Repositories\Contracts\UserRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function authentication(array $credentials): object
    {
        try {
            if (!$token = $this->userRepository->loginAttempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $user = $this->userRepository->getAuthenticatedUser();
            $user->token = $token;

            return response()->json(new UserResource(new LoginResponseStrategy($user)));
        } catch (\Throwable $th) {
            throw new Exception($th->getMessage());
        }
    }

    public function userLogout(string $header) : object
    {
        try {
            $user = $this->userRepository->getAuthenticatedUser();
            // Extract the token
            $token = null;
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                $token = $matches[1];
            }
            $token = $token ?? JWTAuth::getToken();

            if ($token) {
                JWTAuth::invalidate($token);
            }


            return response()->json(new UserResource(new LogoutResponseStrategy($user)));
        } catch (\Throwable $th) {
            throw new Exception($th->getMessage());
        }
    }

    public function tokenValidator()
    {
        try {
            JWTAuth::parseToken()->authenticate();
            return response()->json([
                'data' => [
                    'user_id'   =>  Auth::user()->id,
                    'status'    => true
                ]
            ]);
        } catch (JWTException $e) {
            // Handle the error (token invalid, token expired, etc.)
            return response()->json([
                'data' => [
                    'status' => false
                ]
            ], 401);
        }
    }
}
