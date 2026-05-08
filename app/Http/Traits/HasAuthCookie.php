<?php

namespace App\Http\Traits;

use Symfony\Component\HttpFoundation\Cookie;

trait HasAuthCookie
{
    private function makeCookie(string $token, string $name = 'token'): Cookie
    {
        $ttl = 60 * 60 * 24; // 1 día

        return cookie(
            name: $name, // 👈 nombre dinámico
            value: $token,
            minutes: $ttl / 60,
            path: '/',
            domain: null,
            secure: false,
            httpOnly: true,
            sameSite: 'Lax',
        );
    }
}