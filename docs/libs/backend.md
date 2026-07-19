# Lib — Backend (Controllers, Services, Models)

Couche applicative PHP au-dessus du [core](core.md) : contrôleurs HTTP,
services métier et modèles d'accès aux données. Le géocodage et l'envoi
d'emails sont des services externes documentés ici.

---

## Controllers (`src/Controllers/`)

Point d'entrée HTTP : valident l'entrée, vérifient le CSRF et les droits,
délèguent aux services, formatent la réponse JSON. **Aucune logique métier.**

| Contrôleur | Rôle |
|------------|------|
| `AuthController` | inscription, connexion, déconnexion, mot de passe oublié / reset |
| `EventController` | CRUD des annonces de l'organisateur + endpoints publics (carte) + géocodage |
| `AdminController` | modération, paiements, valider/rejeter, utilisateurs, paramètres, saisie déléguée |
| `SubscriberController` | whitelist abonnés : liste, ajout, suppression, import CSV |
| `UploadController` | sert les affiches stockées hors racine web (contrôle du type) |
| `EmbedController` | sert `embed.html` avec l'en-tête CSP `frame-ancestors` |

---

## Services (`src/Services/`)

Logique métier réutilisable, indépendante du transport HTTP.

### `AuthService`
- **Rôle** : inscription (détection abonné via whitelist), vérification des
  identifiants, jeton de réinitialisation à expiration.
- **Pourquoi** : centralise les règles de compte et l'anti-énumération
  (réponse identique que l'email existe ou non au « mot de passe oublié »).

### `EventService`
- **Rôle** : règle de soumission (gratuité 1re annonce abonné vs paiement 10 €),
  transitions valider/rejeter/paiement, transformation en GeoJSON.
- **Pourquoi** : porte la machine à états des annonces (voir
  [`overview.md`](../architecture/overview.md)) et garantit l'invariant
  « non publié = jamais sur la carte ».

### `GeocodingService`
- **Rôle** : géocodage d'adresses françaises via la **Base Adresse Nationale**
  (`api-adresse.data.gouv.fr`), en proxy serveur.
- **Pourquoi** : appel côté serveur (évite CORS/quota navigateur), permet de
  journaliser, renvoie `lon/lat`, `label` et un `score` de fiabilité.
- Doc API : <https://adresse.data.gouv.fr/api-doc/adresse>

### `ImageService`
- **Rôle** : validation et stockage des affiches (contrôle MIME réel via
  `finfo`, renommage aléatoire, redimensionnement **GD** à 1200 px max,
  stockage dans `storage/uploads/affiches/`).
- **Pourquoi** : sécurité upload (pas de confiance à l'extension déclarée) et
  stockage **hors document root** — les fichiers ne sont jamais servis en direct.

### `MailService`
- **Rôle** : rendu des gabarits HTML (`templates/emails/`) + envoi via PHPMailer.
- **Pourquoi** : en local (`MAIL_ENABLED=false`), les emails sont écrits dans
  `storage/logs/mails/` au lieu d'être expédiés — parcours testable sans SMTP.
- Gabarits : `confirmation_inscription`, `instructions_paiement`,
  `annonce_validee`, `annonce_rejetee`, `reset_mdp`.

---

## Models (`src/Models/`)

Accès aux données en **requêtes PDO préparées** (protection injection SQL).

| Modèle | Table | Notes |
|--------|-------|-------|
| `UserModel` | `users` | recherche par email/id, jetons reset, flag abonné, activation |
| `EventModel` | `events` | écrit/lit la géométrie via `ST_MakePoint` / `ST_X`/`ST_Y`, filtres carte |
| `SubscriberModel` | `subscribers_whitelist` | test d'appartenance, ajout idempotent |
| `PaymentModel` | `payments_log` | création, passage à `recu` |
| `SettingModel` | `settings` | clé/valeur (upsert `ON CONFLICT`) |

**Géométrie** : les points sont écrits en
`ST_SetSRID(ST_MakePoint(:lon,:lat),4326)` et relus en `ST_X(geom) AS lon,
ST_Y(geom) AS lat` pour rester en coordonnées lon/lat côté application.

---

## Sécurité

| Mesure | Mise en œuvre |
|--------|---------------|
| Injection SQL | requêtes PDO préparées partout, `ATTR_EMULATE_PREPARES = false` |
| CSRF | jeton synchronisé sur toutes les mutations (`Core\Csrf`) |
| Sessions | cookie `HttpOnly` + `SameSite=Lax` + `Secure` en production |
| Mots de passe | `password_hash` / `bcrypt` |
| Rate limiting | login, inscription, mot de passe oublié (`Core\RateLimiter`) |
| Uploads | MIME réel (`finfo`), renommage aléatoire, resize GD, hors racine web |
| XSS emails | variables échappées (`htmlspecialchars`) dans les gabarits |
| Iframe | CSP `frame-ancestors` limitée au domaine du client |
| Anti-énumération | « mot de passe oublié » silencieux (réponse constante) |

---

## Exemple : endpoint public GeoJSON

```php
// GET /api/events?mois=9&categorie=fossiles&type=vente&passes=0
public function publicList(Request $request): void
{
    $filters = [
        'mois'           => $request->query('mois'),
        'categorie'      => $request->query('categorie'),
        'type'           => $request->query('type'),
        'inclure_passes' => $request->query('passes') === '1',
    ];
    $rows = $this->events->findPublished($filters);   // WHERE statut='publie' + GIST
    Response::json($this->service->toGeoJson($rows)); // FeatureCollection
}
```
