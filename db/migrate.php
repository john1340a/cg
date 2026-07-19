<?php
declare(strict_types=1);

/**
 * Script de migration — exécute dans l'ordre tous les fichiers
 * db/*.sql (hors ce script), en s'appuyant sur une table
 * « schema_migrations » pour ne jouer chaque fichier qu'une fois.
 *
 * Usage (CLI) :
 *     php db/migrate.php
 *
 * Prérequis : base créée et accessible via les variables du .env.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load();

// Sécurité : réservé à la ligne de commande.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

$pdo = Database::pdo();

// Table de suivi des migrations déjà appliquées
$pdo->exec('
    CREATE TABLE IF NOT EXISTS schema_migrations (
        fichier    TEXT PRIMARY KEY,
        applique_le TIMESTAMPTZ NOT NULL DEFAULT now()
    )
');

// Liste triée des fichiers .sql
$files = glob(__DIR__ . '/*.sql');
sort($files, SORT_STRING);

$dejaFait = $pdo->query('SELECT fichier FROM schema_migrations')
                ->fetchAll(PDO::FETCH_COLUMN);

$applied = 0;
foreach ($files as $file) {
    $base = basename($file);
    if (in_array($base, $dejaFait, true)) {
        echo "→ déjà appliqué : $base\n";
        continue;
    }

    echo "→ application de $base ... ";
    $sql = file_get_contents($file);

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (fichier) VALUES (:f)');
        $stmt->execute([':f' => $base]);
        $pdo->commit();
        echo "OK\n";
        $applied++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "ÉCHEC\n";
        fwrite(STDERR, "Erreur sur $base : " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $applied === 0
    ? "Aucune nouvelle migration à appliquer.\n"
    : "$applied migration(s) appliquée(s).\n";
