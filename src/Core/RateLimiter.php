<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitation de débit simple, persistée en base.
 *
 * Compte les événements d'une même clé (ex. « login:IP ») sur une
 * fenêtre glissante et bloque au-delà d'un seuil. Convient à un
 * hébergement mutualisé (pas de dépendance à Redis/APCu).
 */
final class RateLimiter
{
    /**
     * Enregistre une tentative pour la clé donnée.
     */
    public static function hit(string $key): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO rate_limits (cle) VALUES (:k)')
            ->execute([':k' => $key]);
    }

    /**
     * Nombre de tentatives sur les $windowSeconds dernières secondes.
     */
    public static function count(string $key, int $windowSeconds): int
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits
             WHERE cle = :k AND created_at > now() - (:w || \' seconds\')::interval'
        );
        $stmt->execute([':k' => $key, ':w' => $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * True si la clé a dépassé le seuil autorisé sur la fenêtre.
     */
    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        return self::count($key, $windowSeconds) >= $max;
    }

    /**
     * Nettoie les enregistrements plus vieux que $olderThanSeconds.
     * (Appelé opportunément pour éviter la croissance de la table.)
     */
    public static function purge(int $olderThanSeconds = 86400): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'DELETE FROM rate_limits
             WHERE created_at < now() - (:s || \' seconds\')::interval'
        )->execute([':s' => $olderThanSeconds]);
    }
}
