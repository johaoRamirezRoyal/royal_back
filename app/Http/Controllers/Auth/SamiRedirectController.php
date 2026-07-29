<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sami\SamiSsoService;
use Illuminate\Http\Request;

class SamiRedirectController extends Controller
{
    public function __construct(private SamiSsoService $samiSso) {}

    public function redirect(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $sessionId = $this->samiSso->obtenerSesion($user->id_user);

        if (!$sessionId) {
            return redirect()->away(config('services.sami.base_url') . config('services.sami.login_path'));
        }

        $redirectPath = $request->query('redirect', config('services.sami.home_path'));

        $host = parse_url(config('services.sami.base_url'), PHP_URL_HOST);

        // Solo usar dominio explícito si tiene al menos un punto (evita .localhost)
        $cookieDomain = null;
        if ($host && str_contains($host, '.')) {
            $cookieDomain = '.' . substr($host, strpos($host, '.') + 1);
        }

        return redirect()
            ->away(config('services.sami.base_url') . $redirectPath)
            ->withCookie(
                config('services.sami.cookie_name'),
                $sessionId,
                (int) config('services.sami.cache_ttl'),
                '/',
                $cookieDomain,
                true,
                true,
                false,
                'None'
            );
    }
}
