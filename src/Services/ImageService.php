<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

/**
 * Traitement des affiches uploadées :
 *  - contrôle du type MIME réel (pas seulement l'extension) ;
 *  - contrôle de la taille ;
 *  - renommage aléatoire ;
 *  - redimensionnement (max 1200 px de large) via GD ;
 *  - stockage hors racine web (storage/uploads/affiches/).
 *
 * Les fichiers sont ensuite servis par un endpoint PHP contrôlé
 * (UploadController::serve), jamais en accès direct.
 */
final class ImageService
{
    private const MAX_WIDTH  = 1200;
    private const ALLOWED    = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage/uploads/affiches';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Valide et enregistre un fichier uploadé.
     *
     * @param array<string,mixed> $file  Entrée de $_FILES
     * @return string  Nom de fichier stocké (à persister en base)
     * @throws RuntimeException en cas d'entrée invalide.
     */
    public function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Échec de l\'upload du fichier.');
        }

        $maxBytes = Env::int('UPLOAD_MAX_BYTES', 5_242_880);
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Image trop volumineuse (max ' . round($maxBytes / 1048576) . ' Mo).');
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            throw new RuntimeException('Fichier temporaire introuvable.');
        }

        // MIME réel via finfo (et non l'extension déclarée)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Format non autorisé. Utilisez JPG, PNG ou WEBP.');
        }
        $ext = self::ALLOWED[$mime];

        // Charge l'image selon son type réel
        $src = $this->loadImage($tmp, $mime);
        if ($src === null) {
            throw new RuntimeException('Image illisible ou corrompue.');
        }

        // Redimensionnement proportionnel si trop large
        $src = $this->resize($src);

        // Nom aléatoire imprévisible
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $this->storageDir . '/' . $name;

        $this->saveImage($src, $dest, $ext);
        imagedestroy($src);

        return $name;
    }

    /**
     * Supprime une affiche du stockage (best effort).
     */
    public function delete(?string $name): void
    {
        if (!$name) {
            return;
        }
        // Sécurité : n'accepter qu'un nom de fichier simple
        $name = basename($name);
        $path = $this->storageDir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Chemin absolu d'une affiche (pour la servir), ou null.
     */
    public function path(string $name): ?string
    {
        $name = basename($name);
        $path = $this->storageDir . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * @return \GdImage|null
     */
    private function loadImage(string $tmp, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            default      => false,
        };
        return $img instanceof \GdImage ? $img : null;
    }

    private function resize(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w <= self::MAX_WIDTH) {
            return $src;
        }
        $ratio = self::MAX_WIDTH / $w;
        $nw = self::MAX_WIDTH;
        $nh = (int) round($h * $ratio);

        $dst = imagecreatetruecolor($nw, $nh);
        // Préserver la transparence (PNG/WEBP)
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        return $dst;
    }

    private function saveImage(\GdImage $img, string $dest, string $ext): void
    {
        $ok = match ($ext) {
            'jpg'  => imagejpeg($img, $dest, 85),
            'png'  => imagepng($img, $dest, 6),
            'webp' => imagewebp($img, $dest, 85),
            default => false,
        };
        if (!$ok) {
            throw new RuntimeException('Impossible d\'enregistrer l\'image.');
        }
    }
}
