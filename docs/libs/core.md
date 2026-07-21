# Lib — Core (socle technique)

Technologies fondamentales de l'application et couche `src/Core/` (le
« mini-framework » maison). Cette page documente les briques transverses :
routage, base de données, session, sécurité.

---

## Technologies

### PHP 8.2 (natif)

- **Rôle** : langage serveur, API REST JSON, rendu des gabarits d'emails.
- **Pourquoi** : le cahier des charges impose PHP natif structuré (pas de
  Laravel/Symfony). Léger, sans build, idéal pour l'hébergement mutualisé
  alwaysdata. Autoload PSR-4 via Composer (namespace `App\`).
- **Extensions requises** : `pdo_pgsql`, `gd`, `fileinfo`, `json`.
- Doc : <https://www.php.net/docs.php>

### PostgreSQL 16 + PostGIS 3.4

- **Rôle** : stockage relationnel + géométries spatiales (`Point`/4326).
- **Pourquoi** : PostGIS fournit `ST_MakePoint`, `ST_X/ST_Y`, `ST_AsGeoJSON`,
  index `GIST` — indispensables à la carte. Supporté par alwaysdata, données
  hébergées en France.
- Doc : <https://postgis.net/documentation/>

### PHPMailer 6.12

- **Rôle** : envoi des emails transactionnels via SMTP alwaysdata.
- **Pourquoi** : seule dépendance Composer du projet ; gère STARTTLS/SSL,
  l'UTF-8 et le HTML sans réinventer un client SMTP.
- Doc : <https://github.com/PHPMailer/PHPMailer>

---

## Composants `src/Core/`

| Fichier | Rôle |
|---------|------|
| `App.php` | amorçage : chargement `.env`, session, gestion centralisée des erreurs, dispatch |
| `Env.php` | config (get / bool / int) : variables d'environnement prioritaires, repli sur `.env` |
| `Database.php` | connexion **PDO singleton** (mode exceptions, fetch associatif, UTF-8) |
| `Router.php` | routeur à segments dynamiques (`/api/events/{id}`), 405 si méthode invalide |
| `Request.php` | requête HTTP (méthode, chemin, query, corps JSON, fichiers, IP) + surcharge `_method` |
| `Response.php` | réponses JSON normalisées (`ok` / `error`), redirections |
| `Session.php` | session durcie : cookie `HttpOnly`, `SameSite=Lax`, `Secure` en prod |
| `Csrf.php` | jeton CSRF synchronisé (en-tête `X-CSRF-Token`), 419 si invalide |
| `Auth.php` | contexte d'authentification + gardes `requireUser()` / `requireAdmin()` |
| `RateLimiter.php` | limitation de débit persistée (login, inscription, mot de passe oublié) |
| `Validator.php` | validation d'entrées cumulative (required, email, date, url, longueurs) |

### Détails notables

**Surcharge de méthode HTTP** (`Request.php`) : PHP ne peuple `$_POST`/`$_FILES`
que pour les vrais `POST`. Les mises à jour multipart (édition d'annonce avec
affiche) sont donc envoyées en `POST` avec un champ `_method=PUT`, réinterprété
côté serveur.

**Attribution de la carte** : gérée côté frontend, voir
[`frontend.md`](frontend.md).

**Servir les fichiers statiques en dev** : `public/index.php` détecte le serveur
intégré PHP (`cli-server`) et sert les fichiers réels + l'index de répertoire,
répliquant le comportement d'Apache (`DirectoryIndex`). `embed.html` est
volontairement routé par PHP même s'il existe (pour l'en-tête CSP).

**Routage de la racine et `.htaccess`** : en production (Apache), le
`public/.htaccess` désactive le `DirectoryIndex` automatique et force la racine
`/` à passer par `index.php`. Les pages d'index (`/`, `/compte`, `/admin`) sont
alors servies par le routeur via `PageController` (voir
[`backend.md`](backend.md)). Cette approche évite la dépendance au comportement
`DirectoryIndex` de l'hébergeur, qui, sur alwaysdata, exécute `index.php` avant
`index.html` à la racine.

---

## Dépendances

Une seule dépendance de production (voir [`composer.json`](../../composer.json)) :

| Paquet | Version | Usage |
|--------|---------|-------|
| `phpmailer/phpmailer` | `^6.9` (installé 6.12.0) | emails transactionnels SMTP |

Prérequis plateforme déclarés : `php >=8.1`, `ext-pdo`, `ext-pdo_pgsql`,
`ext-gd`, `ext-json`, `ext-fileinfo`.

---

## Configuration

`Core\Env` lit la configuration selon un **ordre de priorité** :

1. les **variables d'environnement réelles** (`getenv()` / `$_SERVER` / `$_ENV`),
   par exemple définies dans l'interface **alwaysdata** (production) ;
2. à défaut, le fichier **`.env`** non versionné (modèle :
   [`.env.example`](../../.env.example)), pratique en développement local.

Ainsi, en production, aucun fichier `.env` n'est nécessaire : les secrets sont
gérés côté hébergeur (variables d'environnement), ce qui évite de déposer des
identifiants sur le disque.

Clés principales :

| Clé | Rôle |
|-----|------|
| `APP_ENV` | `local` / `production` (active HTTPS, masque les erreurs) |
| `APP_URL` | URL publique de base (utilisée dans les liens des emails) |
| `DB_*` | connexion PostgreSQL |
| `MAIL_ENABLED`, `SMTP_*`, `MAIL_FROM*` | envoi d'emails (fichier si désactivé) |
| `IFRAME_ALLOWED_ORIGINS` | domaine(s) autorisé(s) à intégrer l'iframe |
| `UPLOAD_MAX_BYTES`, `*_MAX_ATTEMPTS` | limites upload et rate limiting |

---

## Commandes

```bash
# Installer les dépendances (+ autoloader PSR-4)
composer install

# Appliquer les migrations (extensions, tables, index)
php db/migrate.php

# Créer / réinitialiser le compte admin
php scripts/create_admin.php <email> <motdepasse> [prenom] [nom]

# Lancer en local (serveur intégré)
php -S 127.0.0.1:8000 -t public public/index.php
```
