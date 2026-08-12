<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuthService;
use RuntimeException;

/**
 * Points d'entrée HTTP de l'authentification.
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    /**
     * POST /api/auth/register
     */
    public function register(Request $request): void
    {
        Csrf::requireValid($request);

        // Rate limiting par IP
        $key = 'register:' . $request->ip();
        $max = Env::int('REGISTER_MAX_ATTEMPTS', 5);
        if (RateLimiter::tooMany($key, $max, 3600)) {
            Response::error('Trop de tentatives. Réessayez plus tard.', 429);
        }
        RateLimiter::hit($key);

        $data = $request->json();
        $v = new Validator($data);
        $v->required('nom')->maxLength('nom', 100)
          ->required('prenom')->maxLength('prenom', 100)
          ->required('email')->email('email')
          ->required('password')->minLength('password', 8,
              'Le mot de passe doit contenir au moins 8 caractères.');

        if ($v->fails()) {
            Response::error('Données invalides.', 422, ['champs' => $v->errors()]);
        }

        try {
            $result = $this->auth->register(
                trim((string) $data['nom']),
                trim((string) $data['prenom']),
                strtolower(trim((string) $data['email'])),
                (string) $data['password']
            );
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }

        // Connexion automatique après inscription
        Auth::login($result['id']);
        Response::ok([
            'ok'         => true,
            'est_abonne' => $result['est_abonne'],
            'message'    => $result['est_abonne']
                ? 'Compte créé. En tant qu\'abonné, votre première annonce est gratuite.'
                : 'Compte créé avec succès.',
        ], 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): void
    {
        Csrf::requireValid($request);

        $key = 'login:' . $request->ip();
        $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        if (RateLimiter::tooMany($key, $max, 900)) {
            Response::error('Trop de tentatives de connexion. Réessayez dans 15 minutes.', 429);
        }

        $data = $request->json();
        $email    = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::error('Email et mot de passe requis.', 422);
        }

        $user = $this->auth->attempt($email, $password);
        if ($user === null) {
            RateLimiter::hit($key); // on ne compte que les échecs
            Response::error('Identifiants incorrects.', 401);
        }

        Auth::login((int) $user['id']);
        Response::ok([
            'ok'    => true,
            'user'  => self::publicUser($user),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): void
    {
        Csrf::requireValid($request);
        Auth::logout();
        Response::ok(['ok' => true]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): void
    {
        $user = Auth::user();
        Response::ok([
            'authenticated' => $user !== null,
            'user'          => $user ? self::publicUser($user) : null,
        ]);
    }

    /**
     * POST /api/auth/forgot
     */
    public function forgot(Request $request): void
    {
        Csrf::requireValid($request);

        $key = 'forgot:' . $request->ip();
        if (RateLimiter::tooMany($key, 5, 3600)) {
            Response::error('Trop de demandes. Réessayez plus tard.', 429);
        }
        RateLimiter::hit($key);

        $email = strtolower(trim((string) ($request->json()['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Adresse email invalide.', 422);
        }

        $this->auth->sendResetLink($email);
        // Réponse identique que l'email existe ou non (anti-énumération)
        Response::ok([
            'ok'      => true,
            'message' => 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * POST /api/auth/reset
     */
    public function reset(Request $request): void
    {
        Csrf::requireValid($request);

        $data     = $request->json();
        $token    = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($token === '') {
            Response::error('Jeton manquant.', 422);
        }
        if (strlen($password) < 8) {
            Response::error('Le mot de passe doit contenir au moins 8 caractères.', 422);
        }

        try {
            $this->auth->resetPassword($token, $password);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 400);
        }

        Response::ok(['ok' => true, 'message' => 'Mot de passe mis à jour. Vous pouvez vous connecter.']);
    }

    /**
     * Projette un utilisateur vers sa représentation publique (sans hash).
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private static function publicUser(array $user): array
    {
        return [
            'id'         => (int) $user['id'],
            'nom'        => $user['nom'],
            'prenom'     => $user['prenom'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'est_abonne' => (bool) $user['est_abonne'],
            'paiement_exempte' => (bool) ($user['paiement_exempte'] ?? false),
        ];
    }
}
