<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Request;
use App\Models\SettingModel;

/**
 * Sert la carte destinée à l'intégration iframe (WordPress), en
 * émettant l'en-tête Content-Security-Policy « frame-ancestors »
 * configurable qui autorise UNIQUEMENT le(s) domaine(s) du client.
 *
 * Choix technique : frame-ancestors (CSP niveau 2) plutôt que
 * X-Frame-Options, car il permet d'autoriser un domaine précis et
 * plusieurs origines, là où X-Frame-Options ne gère qu'une seule.
 */
final class EmbedController
{
    /**
     * GET /embed  et  GET /embed.html
     */
    public function page(Request $request): void
    {
        $origines = $this->originesAutorisees();

        // frame-ancestors : liste d'origines, ou 'self' si rien de configuré.
        $ancestors = $origines !== '' ? $origines : "'self'";
        header("Content-Security-Policy: frame-ancestors {$ancestors};");

        // Ne pas émettre X-Frame-Options (il entrerait en conflit / le
        // limiterait à une seule origine). frame-ancestors le remplace.
        header_remove('X-Frame-Options');

        header('Content-Type: text/html; charset=utf-8');
        readfile(dirname(__DIR__, 2) . '/public/embed.html');
        exit;
    }

    /**
     * Construit la liste d'origines autorisées à intégrer l'iframe.
     * Priorité : paramètre admin « iframe_domain », sinon variable
     * d'environnement IFRAME_ALLOWED_ORIGINS.
     */
    private function originesAutorisees(): string
    {
        $depuisAdmin = (new SettingModel())->get('iframe_domain', '');
        $depuisEnv   = Env::get('IFRAME_ALLOWED_ORIGINS', '');

        $brut = trim((string) ($depuisAdmin !== '' ? $depuisAdmin : $depuisEnv));
        if ($brut === '') {
            return '';
        }

        // Autorise plusieurs domaines séparés par espaces / virgules.
        $parts = preg_split('/[\s,]+/', $brut) ?: [];
        $valides = [];
        foreach ($parts as $p) {
            $p = trim($p);
            // N'accepter que des origines http(s) bien formées.
            if ($p !== '' && preg_match('#^https?://[^\s/]+$#', $p)) {
                $valides[] = $p;
            }
        }
        return implode(' ', $valides);
    }
}
