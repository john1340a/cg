<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protection CSRF par jeton synchronisé (double soumission via en-tête).
 *
 * Le jeton est stocké en session et exposé au frontend via
 * GET /api/csrf. Toute mutation doit renvoyer ce jeton dans
 * l'en-tête « X-CSRF-Token ».
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    /**
     * Retourne le jeton courant, en le générant au besoin.
     */
    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    /**
     * Vérifie le jeton fourni par la requête (en-tête ou champ POST).
     * Termine la requête en 419 si invalide.
     */
    public static function requireValid(Request $request): void
    {
        $expected = Session::get(self::KEY);
        $provided = $request->header('X-CSRF-Token')
            ?? (is_string($request->input('_csrf')) ? $request->input('_csrf') : null);

        if (!is_string($expected) || !is_string($provided)
            || !hash_equals($expected, $provided)) {
            Response::error('Jeton CSRF invalide ou manquant. Rechargez la page.', 419);
        }
    }
}
