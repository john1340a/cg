<?php
declare(strict_types=1);

/**
 * Création (ou mise à jour) du compte administrateur initial.
 *
 * Usage (CLI) :
 *     php scripts/create_admin.php <email> <motdepasse> [prenom] [nom]
 *
 * Si l'email existe déjà, le compte est promu admin et son mot de
 * passe est réinitialisé à la valeur fournie.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

$email  = $argv[1] ?? null;
$pass   = $argv[2] ?? null;
$prenom = $argv[3] ?? 'Admin';
$nom    = $argv[4] ?? 'MINERALOGIQUE';

if (!$email || !$pass) {
    fwrite(STDERR, "Usage : php scripts/create_admin.php <email> <motdepasse> [prenom] [nom]\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email invalide : $email\n");
    exit(1);
}
if (strlen($pass) < 8) {
    fwrite(STDERR, "Le mot de passe doit contenir au moins 8 caractères.\n");
    exit(1);
}

$pdo  = Database::pdo();
$hash = password_hash($pass, PASSWORD_BCRYPT);

$sql = '
    INSERT INTO users (nom, prenom, email, password_hash, role, est_actif)
    VALUES (:nom, :prenom, :email, :hash, \'admin\', TRUE)
    ON CONFLICT (email) DO UPDATE
        SET role = \'admin\',
            password_hash = EXCLUDED.password_hash,
            est_actif = TRUE
    RETURNING id
';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nom'    => $nom,
    ':prenom' => $prenom,
    ':email'  => $email,
    ':hash'   => $hash,
]);
$id = $stmt->fetchColumn();

echo "Compte admin prêt (id=$id) : $email\n";
