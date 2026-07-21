# Déploiement sur alwaysdata

Mise en production sur un hébergement mutualisé **alwaysdata**
(PHP 8 + PostgreSQL/PostGIS + SMTP). La configuration se fait via les
**variables d'environnement** d'alwaysdata (aucun fichier `.env` à déposer
sur le serveur).

---

## 1. Base de données PostgreSQL + PostGIS

Admin alwaysdata → **Bases de données → PostgreSQL → Ajouter une base** :

- Nom : ex. `<compte>_mineralogique`
- Créer / associer un utilisateur + mot de passe (à noter pour l'étape 4).

PostGIS est activé automatiquement par la migration
(`CREATE EXTENSION IF NOT EXISTS postgis`, ainsi que `citext` et `pgcrypto`).
Si l'activation requiert un privilège particulier, l'activer depuis l'interface
alwaysdata puis relancer la migration.

---

## 2. Envoi des fichiers (SFTP)

Transférer le projet dans un dossier privé, ex. `~/www/bourses-mineraux/`.

Le plus simple : générer une archive **sans fichiers sensibles** et la
décompresser sur le serveur.

```bash
# sur le serveur, après avoir déposé le zip en SFTP
cd ~/www/bourses-mineraux
unzip -o <archive>.zip
rm <archive>.zip
```

L'archive doit contenir **exactement** :

```
public/  src/  db/  scripts/  templates/  storage/  vendor/
composer.json  composer.lock  .env.example
```

À **ne jamais** envoyer : `.env`, exports CSV clients, `.git/`, documents
sources (`.pdf`, `.docx`). Voir le [`.gitignore`](../.gitignore).

Vérifier ensuite que `public/` contient bien l'application **et le `.htaccess`** :

```bash
ls -la public/     # index.php, assets/, admin/, compte/, .htaccess…
```

> Le `.htaccess` est un fichier caché : vérifier sa présence avec `ls -la`
> (et non `ls`). Il est indispensable au routage.

Droits d'écriture sur le stockage (uploads + logs) :

```bash
chmod -R 775 storage
```

---

## 3. Site (document root)

Admin alwaysdata → **Sites → Ajouter un site** :

- Type : **PHP**, version 8.x
- **Racine (document root)** : `www/bourses-mineraux/public`
  ← ⚠️ pointer sur le sous-dossier **`public/`**, pas la racine du projet.
- Associer le nom de domaine (un **sous-domaine dédié** est recommandé, ex.
  `mineralogique.<compte>.alwaysdata.net` ; un **sous-répertoire** type
  `.../mineralogique` casserait les chemins absolus de l'application).
- Activer le **certificat SSL** (HTTPS obligatoire : les cookies de session
  sont `Secure` en production).

Le `public/.htaccess` gère la réécriture vers `index.php`, désactive le
`DirectoryIndex` automatique (pour que la racine `/` passe par le routeur) et
force `embed.html` par PHP (en-tête CSP).

---

## 4. Variables d'environnement (au lieu d'un fichier `.env`)

Admin alwaysdata → le site (ou l'environnement) → **Variables d'environnement**.
`Core\Env` lit ces variables **en priorité** (repli sur `.env` s'il existe).

```ini
APP_ENV                = production
APP_URL                = https://mineralogique.<compte>.alwaysdata.net
DB_HOST                = postgresql-<compte>.alwaysdata.net
DB_PORT                = 5432
DB_NAME                = <compte>_mineralogique
DB_USER                = <utilisateur DB>
DB_PASS                = <mot de passe DB>
MAIL_ENABLED           = true
SMTP_HOST              = smtp-<compte>.alwaysdata.net
SMTP_PORT              = 587
SMTP_SECURITY          = tls
SMTP_USER              = contact@mineralogique.fr
SMTP_PASS              = <mot de passe boîte mail>
MAIL_FROM              = contact@mineralogique.fr
MAIL_FROM_NAME         = Bourses aux Minéraux
IFRAME_ALLOWED_ORIGINS = https://mineralogique.com https://www.mineralogique.com
```

- `APP_URL` : URL publique réelle de l'application (sert aux liens des emails).
- `IFRAME_ALLOWED_ORIGINS` : domaine(s) **du site WordPress** autorisé(s) à
  intégrer l'iframe (pas le domaine de l'app). Peut aussi être défini dans
  **Back-office → Paramètres** (prioritaire sur la variable d'environnement).
- Tant qu'aucune boîte mail n'est prête, mettre `MAIL_ENABLED = false` : les
  emails sont écrits dans `storage/logs/mails/` au lieu d'être expédiés.

---

## 5. Dépendances & initialisation (SSH)

```bash
cd ~/www/bourses-mineraux

# Dépendances (si vendor/ n'a pas été envoyé)
composer install --no-dev --optimize-autoloader

# Migrations : extensions PostGIS, tables, index
php db/migrate.php

# Compte administrateur initial
php scripts/create_admin.php admin@mineralogique.fr <MotDePasseFort> Admin MINERALOGIQUE
```

La base de production démarre **vide** : la liste des abonnés doit être
(ré)importée via **Back-office → Abonnés → Import CSV** une fois le site en
ligne.

---

## 6. Emails (SMTP)

1. Créer une **boîte email** alwaysdata (ex. `contact@mineralogique.fr`).
2. Renseigner `SMTP_*` et `MAIL_FROM` avec ces identifiants (étape 4).
3. Vérifier l'envoi : déclencher un « mot de passe oublié » et confirmer la
   réception.

---

## 7. Intégration dans WordPress

Insérer un **bloc HTML personnalisé** dans une page WordPress :

```html
<div style="position:relative;width:100%;height:640px;">
  <iframe src="https://mineralogique.<compte>.alwaysdata.net/embed.html"
          title="Carte des bourses aux minéraux"
          style="width:100%;height:100%;border:0;border-radius:8px;"
          loading="lazy"></iframe>
</div>
```

L'en-tête `Content-Security-Policy: frame-ancestors …` autorise **uniquement**
le(s) domaine(s) configuré(s). Vérifier que le domaine WordPress exact
(avec/sans `www`, en `https`) figure dans `IFRAME_ALLOWED_ORIGINS`, sinon
l'iframe est bloquée par le navigateur.

---

## 8. Cron (optionnel)

Pour purger périodiquement la table de limitation de débit (`rate_limits`),
planifier via alwaysdata → **Tâches planifiées** :

```bash
php -r "require 'vendor/autoload.php'; App\Core\Env::load(); App\Core\RateLimiter::purge(86400);"
```

---

## 9. Vérifications post-déploiement

- [ ] `https://<domaine>/` affiche l'accueil (route `/` servie par le routeur).
- [ ] `https://<domaine>/carte.html` charge la carte et les événements publiés.
- [ ] `https://<domaine>/api/csrf` renvoie un jeton JSON (routage OK).
- [ ] Inscription + email de confirmation reçu (ou écrit dans les logs).
- [ ] `https://<domaine>/embed.html` renvoie l'en-tête
      `Content-Security-Policy: frame-ancestors …`.
- [ ] L'iframe s'affiche dans la page WordPress de test (desktop + mobile).
- [ ] Le back-office `/admin/` est accessible avec le compte admin.

## Dépannage courant

| Symptôme | Cause probable | Correctif |
|----------|----------------|-----------|
| `404 — Page introuvable` sur `/` mais `/index.php` et `/api/csrf` marchent | `DirectoryIndex` de l'hôte sert `index.php` sans routage | `.htaccess` à jour (`DirectoryIndex disabled` + route racine) et route `/` présente |
| Tout en 404 y compris `/index.php` | document root ne pointe pas sur `public/` | corriger la Racine du site → `.../public` |
| CSS/JS/carte cassés | app servie sous un sous-répertoire | utiliser un sous-domaine dédié |
| Emails non reçus | SMTP mal configuré | vérifier `SMTP_*`, `MAIL_FROM` = boîte alwaysdata réelle |
| Iframe blanche/bloquée | domaine WordPress non autorisé | l'ajouter à `IFRAME_ALLOWED_ORIGINS` |

Voir aussi la **checklist de recette** : [`recette.md`](recette.md).
