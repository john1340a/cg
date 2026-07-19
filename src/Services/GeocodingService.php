<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Géocodage d'adresses françaises via la Base Adresse Nationale
 * (API adresse.data.gouv.fr, gratuite, sans clé).
 *
 * L'appel se fait côté serveur (proxy) pour éviter les soucis CORS
 * et pouvoir contrôler / journaliser les requêtes.
 */
final class GeocodingService
{
    private const ENDPOINT = 'https://api-adresse.data.gouv.fr/search/';

    /**
     * Géocode une adresse et renvoie le meilleur résultat.
     *
     * @return array{lon:float,lat:float,label:string,score:float,citycode:?string}|null
     */
    public function geocode(string $adresse): ?array
    {
        $adresse = trim($adresse);
        if ($adresse === '') {
            return null;
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'q'     => $adresse,
            'limit' => 1,
        ]);

        $json = $this->httpGet($url);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['features'])) {
            return null;
        }

        $feature = $data['features'][0];
        $coords  = $feature['geometry']['coordinates'] ?? null;
        $props   = $feature['properties'] ?? [];

        if (!is_array($coords) || count($coords) < 2) {
            return null;
        }

        return [
            'lon'      => (float) $coords[0],
            'lat'      => (float) $coords[1],
            'label'    => (string) ($props['label'] ?? $adresse),
            'score'    => (float) ($props['score'] ?? 0),
            'citycode' => isset($props['citycode']) ? (string) $props['citycode'] : null,
        ];
    }

    /**
     * Requête HTTP GET simple (cURL si dispo, sinon file_get_contents).
     */
    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'BoursesMineraux/1.0 (+geocodage)',
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($res !== false && $code >= 200 && $code < 300) ? (string) $res : null;
        }

        $ctx = stream_context_create(['http' => [
            'timeout'    => 8,
            'user_agent' => 'BoursesMineraux/1.0 (+geocodage)',
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? $res : null;
    }
}
