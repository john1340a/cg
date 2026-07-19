<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Connexion PDO PostgreSQL (singleton).
 *
 * Toutes les requêtes de l'application passent par cette instance
 * unique, configurée en mode exceptions et fetch associatif.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    /**
     * Retourne l'instance PDO partagée (la crée au premier appel).
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '5432');
        $name = Env::get('DB_NAME', 'mineralogique');
        $user = Env::get('DB_USER', 'postgres');
        $pass = Env::get('DB_PASS', '');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // S'assurer que les échanges se font en UTF-8
            self::$pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            // Ne pas exposer les identifiants ; message générique côté client.
            throw new RuntimeException(
                'Connexion à la base de données impossible : ' . $e->getMessage(),
                (int) $e->getCode()
            );
        }

        return self::$pdo;
    }
}
