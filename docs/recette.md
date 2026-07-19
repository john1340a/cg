# Checklist de recette — parcours complet

Cette checklist couvre le parcours de bout en bout et les critères d'acceptation
du cahier des charges. À dérouler après déploiement (ou en local).

## Préparation
- [ ] Migrations appliquées (`php db/migrate.php`).
- [ ] Compte admin créé (`php scripts/create_admin.php …`).
- [ ] Application accessible (accueil `/`, carte `/carte.html`).

## A. Abonnés (première annonce gratuite)
1. [ ] **Admin → Abonnés** : importer un CSV contenant `abonne@test.fr` (ou l'ajouter manuellement).
2. [ ] Se déconnecter, **s'inscrire** avec `abonne@test.fr`.
3. [ ] Le message d'inscription indique « première annonce gratuite ».
4. [ ] Un email de confirmation est généré (reçu, ou dans `storage/logs/mails/` en local).

## B. Saisie d'une annonce (organisateur abonné)
5. [ ] **Espace organisateur → Nouvelle annonce**.
6. [ ] Remplir tous les champs du modèle papier (intitulé, n° édition, dates du/au,
       type échanges/vente, catégories, adresse, tarif, email de contact, site, affiche).
7. [ ] Cliquer **Localiser** : l'adresse est géocodée (BAN), le marqueur apparaît sur la mini-carte.
8. [ ] Déplacer le marqueur pour ajuster la position (drag) — les coordonnées se mettent à jour.
9. [ ] **Enregistrer** : l'annonce apparaît en **Brouillon** dans le tableau de bord.
10. [ ] **Soumettre** : l'annonce passe en **En attente de validation**, marquée gratuite
        (pas d'étape de paiement). → *Critère : un abonné obtient sa 1re annonce sans paiement.*

## C. Saisie d'une annonce (organisateur NON abonné)
11. [ ] S'inscrire avec un email hors whitelist.
12. [ ] Créer + **Soumettre** une annonce → statut **En attente de paiement**.
13. [ ] Un email d'**instructions de paiement** (10 €) est généré.

## D. Modération admin
14. [ ] **Admin → Modération**, filtrer « En attente de paiement ».
15. [ ] Sur l'annonce non abonnée : **Marquer paiement reçu** → passe en attente de validation.
16. [ ] Filtrer « En attente de validation » : **Valider et publier** une annonce.
17. [ ] L'organisateur reçoit un email « annonce publiée ».
18. [ ] Tester **Rejeter** (avec motif) sur une autre annonce → email de rejet avec motif ;
        l'annonce repasse modifiable côté organisateur.

## E. Carte publique
19. [ ] Ouvrir `/carte.html` : seules les annonces **publiées** apparaissent.
        → *Critère : une annonce non validée n'apparaît jamais sur la carte.*
20. [ ] Les marqueurs sont **colorés par mois** ; la **légende** est visible.
21. [ ] **Popup** : intitulé, dates, adresse, tarif, type, catégories, email de contact,
        site web, miniature d'affiche (cliquable pour agrandir).
22. [ ] **Filtres** (mois, période, catégorie, type) : la carte + la liste se mettent à jour
        **sans rechargement de page**. → *Critère.*
23. [ ] Les événements passés sont masqués par défaut ; la case « passés » les affiche.
24. [ ] Changer de **fond de carte** (Plan / Clair / Satellite).

## F. Intégration iframe (WordPress)
25. [ ] `/embed.html` renvoie l'en-tête `Content-Security-Policy: frame-ancestors …`.
26. [ ] Dans une page WordPress de test, l'iframe `embed.html` **s'affiche** (desktop).
27. [ ] Idem en **mobile** (largeur ~375 px) : carte + filtres repliables lisibles.
        → *Critère.*
28. [ ] Depuis un domaine **non autorisé**, l'iframe est **bloquée** (CSP) — comportement attendu.

## G. Comptes & sécurité
29. [ ] **Mot de passe oublié** : demande → email avec lien → `reset.html` → nouveau mot de passe.
30. [ ] **Admin → Utilisateurs** : désactiver un compte → cet utilisateur ne peut plus se connecter.
31. [ ] Toute mutation sans jeton CSVR valide échoue (419).
32. [ ] Tentatives de connexion répétées → limitation (429) après le seuil.

## H. Interface
33. [ ] Toute l'interface est **en français**. → *Critère.*
34. [ ] Affichage responsive correct sur mobile (formulaire, tableau de bord, carte).
