<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès à la table « subscribers_whitelist » (emails abonnés).
 */
final class SubscriberModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * True si l'email figure dans la liste des abonnés.
     */
    public function isSubscriber(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM subscribers_whitelist WHERE email = :e LIMIT 1'
        );
        $stmt->execute([':e' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Ajoute un email (ignore les doublons). Retourne true si inséré.
     */
    public function add(string $email, ?int $ajoutePar = null): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscribers_whitelist (email, ajoute_par)
             VALUES (:e, :by)
             ON CONFLICT (email) DO NOTHING'
        );
        $stmt->execute([':e' => $email, ':by' => $ajoutePar]);
        return $stmt->rowCount() > 0;
    }

    public function remove(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM subscribers_whitelist WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, email, created_at FROM subscribers_whitelist ORDER BY email'
        )->fetchAll();
    }

    /**
     * @return array<int,array<string,mixed>>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscribers_whitelist WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
