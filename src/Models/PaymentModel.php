<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès à la table « payments_log » (suivi simple des paiements).
 */
final class PaymentModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Crée une ligne de paiement pour une annonce.
     *
     * @param string $statut  attendu | recu | exonere
     */
    public function create(int $eventId, float $montant, string $statut, ?string $note = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments_log (event_id, montant, statut, note)
             VALUES (:e, :m, :s, :n) RETURNING id'
        );
        $stmt->execute([':e' => $eventId, ':m' => $montant, ':s' => $statut, ':n' => $note]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Met à jour le dernier paiement d'une annonce (ou en crée un).
     */
    public function markReceived(int $eventId, ?string $note = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payments_log SET statut = \'recu\', note = COALESCE(:n, note)
             WHERE event_id = :e'
        );
        $stmt->execute([':n' => $note, ':e' => $eventId]);
        if ($stmt->rowCount() === 0) {
            $this->create($eventId, 10.0, 'recu', $note);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findByEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payments_log WHERE event_id = :e ORDER BY created_at DESC'
        );
        $stmt->execute([':e' => $eventId]);
        return $stmt->fetchAll();
    }
}
