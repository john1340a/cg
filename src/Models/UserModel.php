<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès aux données de la table « users ».
 * Toutes les requêtes sont préparées (protection injection SQL).
 */
final class UserModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée un utilisateur et retourne son id.
     */
    public function create(
        string $nom,
        string $prenom,
        string $email,
        string $passwordHash,
        bool $estAbonne,
        string $role = 'user'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (nom, prenom, email, password_hash, est_abonne, role)
             VALUES (:nom, :prenom, :email, :hash, :abonne, :role)
             RETURNING id'
        );
        $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':hash'   => $passwordHash,
            ':abonne' => $estAbonne ? 1 : 0,
            ':role'   => $role,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Enregistre un jeton de réinitialisation avec expiration.
     */
    public function setResetToken(int $userId, string $token, string $expiresAtIso): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET token_reset = :t, token_expire = :e WHERE id = :id'
        );
        $stmt->execute([':t' => $token, ':e' => $expiresAtIso, ':id' => $userId]);
    }

    /**
     * Retrouve un utilisateur par jeton de reset encore valide.
     *
     * @return array<string,mixed>|null
     */
    public function findByValidResetToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users
             WHERE token_reset = :t AND token_expire > now()'
        );
        $stmt->execute([':t' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Change le mot de passe et invalide le jeton de reset.
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :h, token_reset = NULL, token_expire = NULL
             WHERE id = :id'
        );
        $stmt->execute([':h' => $passwordHash, ':id' => $userId]);
    }

    /**
     * Met à jour le flag est_abonne (ex. après import whitelist).
     */
    public function setAbonne(string $email, bool $abonne): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET est_abonne = :a WHERE email = :e'
        );
        $stmt->execute([':a' => $abonne ? 1 : 0, ':e' => $email]);
    }

    /**
     * Compte les annonces déjà créées par un utilisateur
     * (sert à déterminer la « première annonce » gratuite).
     */
    public function countEvents(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM events WHERE owner_id = :id'
        );
        $stmt->execute([':id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Liste des utilisateurs (back-office).
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, nom, prenom, email, role, est_abonne, est_actif,
                    paiement_exempte, created_at
             FROM users ORDER BY created_at DESC'
        )->fetchAll();
    }

    public function setActif(int $userId, bool $actif): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET est_actif = :a WHERE id = :id');
        $stmt->execute([':a' => $actif ? 1 : 0, ':id' => $userId]);
    }

    /**
     * Active/désactive l'exemption de paiement d'un compte : quand elle
     * est active, toutes les annonces de l'organisateur sont gratuites
     * (ex. organisateur payant déjà une pub pleine page).
     */
    public function setPaiementExempte(int $userId, bool $exempte): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET paiement_exempte = :e WHERE id = :id');
        $stmt->execute([':e' => $exempte ? 1 : 0, ':id' => $userId]);
    }
}
