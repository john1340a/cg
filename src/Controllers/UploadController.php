<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ImageService;

/**
 * Sert les affiches stockées hors racine web, via un endpoint contrôlé.
 * (Les fichiers ne sont jamais accessibles en direct.)
 */
final class UploadController
{
    /**
     * GET /api/affiche/{fichier}
     */
    public function serve(Request $request, array $params): void
    {
        $name = basename((string) $params['fichier']); // anti-traversal
        $path = (new ImageService())->path($name);

        if ($path === null) {
            Response::error('Image introuvable.', 404);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        // N'autoriser que des images
        if (!str_starts_with($mime, 'image/')) {
            Response::error('Type non autorisé.', 415);
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
