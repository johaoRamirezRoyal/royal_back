<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtService
{
    public function generateToken(Authenticatable $user): string
    {
        return JWTAuth::claims(['system' => 'general'])->fromUser($user);
    }

    public function generateAdmissionsToken(Authenticatable $user): string
    {
        return JWTAuth::claims(['system' => 'admissions'])->fromUser($user);
    }

    public function refreshToken(string $token): string
    {
        return JWTAuth::setToken($token)->refresh();
    }

    public function invalidateToken(string $token): void
    {
        JWTAuth::setToken($token)->invalidate();
    }

    public function getPayload(string $token): array
    {
        return JWTAuth::setToken($token)->getPayload()->toArray();
    }

    public function authenticate(): ?Authenticatable
    {
        return JWTAuth::parseToken()->authenticate();
    }
}
