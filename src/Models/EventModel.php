<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès aux données de la table « events ».
 *
 * La géométrie est stockée en Point/4326. On l'écrit via
 * ST_SetSRID(ST_MakePoint(lon,lat),4326) et on la lit via
 * ST_X / ST_Y pour rester en coordonnées lon/lat.
 */
final class EventModel
{
    private PDO $pdo;

    /** Colonnes « métier » communes aux SELECT (hors geom). */
    private const COLS = 'id, owner_id, intitule, edition_num, date_debut, date_fin,
        type_echanges, type_vente, cat_mineraux, cat_fossiles, cat_gemmes, cat_esoterisme,
        adresse, tarif, contact_email, site_web, affiche_path, statut, motif_rejet,
        est_gratuite, created_at, updated_at,
        ST_X(geom) AS lon, ST_Y(geom) AS lat';

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Crée une annonce (statut initial « brouillon »).
     *
     * @param array<string,mixed> $d Données déjà validées.
     * @return int  id créé
     */
    public function create(int $ownerId, array $d): int
    {
        $sql = 'INSERT INTO events (
                    owner_id, intitule, edition_num, date_debut, date_fin,
                    type_echanges, type_vente,
                    cat_mineraux, cat_fossiles, cat_gemmes, cat_esoterisme,
                    adresse, geom, tarif, contact_email, site_web, affiche_path,
                    statut, est_gratuite
                ) VALUES (
                    :owner, :intitule, :edition, :debut, :fin,
                    :t_ech, :t_vente,
                    :c_min, :c_fos, :c_gem, :c_eso,
                    :adresse, ST_SetSRID(ST_MakePoint(:lon, :lat), 4326),
                    :tarif, :contact, :site, :affiche,
                    :statut, :gratuite
                ) RETURNING id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bind($ownerId, $d));
        return (int) $stmt->fetchColumn();
    }

    /**
     * Met à jour une annonce existante.
     *
     * @param array<string,mixed> $d
     */
    public function update(int $id, int $ownerId, array $d): void
    {
        $sql = 'UPDATE events SET
                    intitule = :intitule, edition_num = :edition,
                    date_debut = :debut, date_fin = :fin,
                    type_echanges = :t_ech, type_vente = :t_vente,
                    cat_mineraux = :c_min, cat_fossiles = :c_fos,
                    cat_gemmes = :c_gem, cat_esoterisme = :c_eso,
                    adresse = :adresse,
                    geom = ST_SetSRID(ST_MakePoint(:lon, :lat), 4326),
                    tarif = :tarif, contact_email = :contact,
                    site_web = :site, affiche_path = :affiche
                WHERE id = :id';

        $params = $this->bind($ownerId, $d);
        unset($params[':owner'], $params[':statut'], $params[':gratuite']);
        $params[':id'] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Construit le tableau de binding commun create/update.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function bind(int $ownerId, array $d): array
    {
        return [
            ':owner'    => $ownerId,
            ':intitule' => $d['intitule'],
            ':edition'  => $d['edition_num'] ?? null,
            ':debut'    => $d['date_debut'],
            ':fin'      => $d['date_fin'],
            ':t_ech'    => !empty($d['type_echanges']) ? 1 : 0,
            ':t_vente'  => !empty($d['type_vente']) ? 1 : 0,
            ':c_min'    => !empty($d['cat_mineraux']) ? 1 : 0,
            ':c_fos'    => !empty($d['cat_fossiles']) ? 1 : 0,
            ':c_gem'    => !empty($d['cat_gemmes']) ? 1 : 0,
            ':c_eso'    => !empty($d['cat_esoterisme']) ? 1 : 0,
            ':adresse'  => $d['adresse'],
            ':lon'      => $d['lon'],
            ':lat'      => $d['lat'],
            ':tarif'    => $d['tarif'] ?? null,
            ':contact'  => $d['contact_email'],
            ':site'     => $d['site_web'] ?? null,
            ':affiche'  => $d['affiche_path'] ?? null,
            ':statut'   => $d['statut'] ?? 'brouillon',
            ':gratuite' => !empty($d['est_gratuite']) ? 1 : 0,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLS . ' FROM events WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Annonces d'un propriétaire (tableau de bord).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findByOwner(int $ownerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLS . ' FROM events WHERE owner_id = :o ORDER BY created_at DESC'
        );
        $stmt->execute([':o' => $ownerId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Change le statut (+ motif de rejet éventuel).
     */
    public function setStatut(int $id, string $statut, ?string $motif = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE events SET statut = :s, motif_rejet = :m WHERE id = :id'
        );
        $stmt->execute([':s' => $statut, ':m' => $motif, ':id' => $id]);
    }

    /**
     * Liste filtrée pour l'admin (par statut optionnel).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findForAdmin(?string $statut = null): array
    {
        // e.* couvre toutes les colonnes de la table ; on ajoute les
        // coordonnées dérivées et les infos du propriétaire (préfixées owner_).
        $sql = 'SELECT e.*, ST_X(e.geom) AS lon, ST_Y(e.geom) AS lat,
                       u.email AS owner_email, u.prenom AS owner_prenom, u.nom AS owner_nom
                FROM events e JOIN users u ON u.id = e.owner_id';
        $params = [];
        if ($statut !== null) {
            $sql .= ' WHERE e.statut = :s';
            $params[':s'] = $statut;
        }
        $sql .= ' ORDER BY e.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Événements publiés au format lignes, avec filtres pour la carte.
     * Retourne lon/lat pour construire le GeoJSON côté service.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function findPublished(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLS . ' FROM events WHERE statut = \'publie\' AND geom IS NOT NULL';
        $params = [];

        // Masquer les événements passés sauf demande explicite
        if (empty($filters['inclure_passes'])) {
            $sql .= ' AND date_fin >= CURRENT_DATE';
        }
        if (!empty($filters['debut'])) {
            $sql .= ' AND date_fin >= :debut';
            $params[':debut'] = $filters['debut'];
        }
        if (!empty($filters['fin'])) {
            $sql .= ' AND date_debut <= :fin';
            $params[':fin'] = $filters['fin'];
        }
        if (!empty($filters['mois'])) {
            // mois au format 1..12
            $sql .= ' AND EXTRACT(MONTH FROM date_debut) = :mois';
            $params[':mois'] = (int) $filters['mois'];
        }
        // Catégories (au moins une des catégories cochées)
        $catMap = [
            'mineraux'   => 'cat_mineraux',
            'fossiles'   => 'cat_fossiles',
            'gemmes'     => 'cat_gemmes',
            'esoterisme' => 'cat_esoterisme',
        ];
        if (!empty($filters['categorie']) && isset($catMap[$filters['categorie']])) {
            $sql .= ' AND ' . $catMap[$filters['categorie']] . ' = TRUE';
        }
        // Type
        if (($filters['type'] ?? '') === 'echanges') {
            $sql .= ' AND type_echanges = TRUE';
        } elseif (($filters['type'] ?? '') === 'vente') {
            $sql .= ' AND type_vente = TRUE';
        }

        $sql .= ' ORDER BY date_debut ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
