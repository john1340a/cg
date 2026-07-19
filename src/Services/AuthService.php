<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\SubscriberModel;
use App\Models\UserModel;
use RuntimeException;

/**
 * Logique métier de l'authentification :
 * inscription (avec détection whitelist abonné), connexion,
 * réinitialisation de mot de passe par jeton.
 */
final class AuthService
{
    private UserModel $users;
    private SubscriberModel $subscribers;
    private MailService $mail;

    public function __construct()
    {
        $this->users       = new UserModel();
        $this->subscribers = new SubscriberModel();
        $this->mail        = new MailService();
    }

    /**
     * Inscrit un nouvel utilisateur.
     * Détermine automatiquement est_abonne via la whitelist.
     *
     * @return array{id:int,est_abonne:bool}
     * @throws RuntimeException si l'email est déjà utilisé.
     */
    public function register(string $nom, string $prenom, string $email, string $password): array
    {
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('Un compte existe déjà avec cet email.');
        }

        $estAbonne = $this->subscribers->isSubscriber($email);
        $hash      = password_hash($password, PASSWORD_BCRYPT);
        $id        = $this->users->create($nom, $prenom, $email, $hash, $estAbonne);

        // Email de confirmation d'inscription (non bloquant).
        $this->mail->send(
            $email,
            'Bienvenue — votre compte est créé',
            'confirmation_inscription',
            ['prenom' => $prenom, 'est_abonne' => $estAbonne, 'app_url' => Env::get('APP_URL', '')]
        );

        return ['id' => $id, 'est_abonne' => $estAbonne];
    }

    /**
     * Vérifie les identifiants. Retourne l'utilisateur ou null.
     *
     * @return array<string,mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$user['est_actif']) {
            // Toujours exécuter un hash pour limiter le timing attack.
            password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000');
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /**
     * Génère un jeton de reset et envoie l'email correspondant.
     * Ne révèle jamais si l'email existe (anti-énumération).
     */
    public function sendResetLink(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return; // silencieux volontairement
        }

        $token   = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable('+1 hour'))->format('c');
        $this->users->setResetToken((int) $user['id'], $token, $expires);

        $link = rtrim(Env::get('APP_URL', ''), '/') . '/reset.html?token=' . $token;

        $this->mail->send(
            $email,
            'Réinitialisation de votre mot de passe',
            'reset_mdp',
            ['prenom' => $user['prenom'], 'lien' => $link]
        );
    }

    /**
     * Applique un nouveau mot de passe à partir d'un jeton valide.
     *
     * @throws RuntimeException si le jeton est invalide ou expiré.
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        $user = $this->users->findByValidResetToken($token);
        if ($user === null) {
            throw new RuntimeException('Lien de réinitialisation invalide ou expiré.');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->users->updatePassword((int) $user['id'], $hash);
    }
}
