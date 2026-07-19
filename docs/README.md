# Documentation

Documentation technique de la plateforme **Bourses aux Minéraux**.

## Sommaire

### Architecture
- [`architecture/overview.md`](architecture/overview.md) — vue d'ensemble :
  structure, patterns MVC, machine à états d'une annonce, flux de données,
  routes, modèle de données.

### Composants (`libs/`)
- [`libs/core.md`](libs/core.md) — socle technique (PHP, PostgreSQL/PostGIS,
  PHPMailer) et couche `src/Core/` (routeur, DB, session, sécurité).
- [`libs/backend.md`](libs/backend.md) — contrôleurs, services (géocodage,
  images, emails), modèles, sécurité.
- [`libs/frontend.md`](libs/frontend.md) — JS vanilla, MapLibre, direction
  artistique, polices & icônes locales, responsive, embed iframe.

### Exploitation
- [`deploiement.md`](deploiement.md) — mise en production sur alwaysdata
  (base PostgreSQL/PostGIS, document root, SMTP, intégration WordPress).
- [`recette.md`](recette.md) — checklist de recette du parcours complet.

---

Le [`README.md`](../README.md) à la racine donne un aperçu rapide et
l'installation locale.
