<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès aux paramètres clé/valeur de la table « settings ».
 */
final class SettingModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Récupère une valeur (avec repli).
     */
    public function get(string $cle, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valeur FROM settings WHERE cle = :c');
        $stmt->execute([':c' => $cle]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string) $v : $default;
    }

    /**
     * Définit / met à jour une valeur (upsert).
     */
    public function set(string $cle, string $valeur): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (cle, valeur) VALUES (:c, :v)
             ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur, updated_at = now()'
        );
        $stmt->execute([':c' => $cle, ':v' => $valeur]);
    }

    /**
     * Tous les paramètres sous forme clé => valeur.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT cle, valeur FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['cle']] = $r['valeur'];
        }
        return $out;
    }
}
