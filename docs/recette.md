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

   > Les **catégories** proposées incluent bien : Minéraux, **Microminéraux**,
   > Fossiles, Gemmes/bijoux, Ésotérisme/lithothérapie.

## C. Saisie d'une annonce (organisateur NON abonné)
11. [ ] S'inscrire avec un email hors whitelist.
12. [ ] Créer + **Soumettre** une annonce → statut **En attente de paiement**.
13. [ ] Un email d'**instructions de paiement** (10 €) est généré, avec le
        **bouton « Payer en ligne »** (lien WooCommerce, email pré-rempli).
14. [ ] Sur le tableau de bord, l'annonce affiche le bouton **« Payer 10 € »**
        pointant vers la fiche produit WooCommerce (`?email=…`).

## C bis. Compte exempté de paiement
15. [ ] **Admin → Utilisateurs** : activer **« Gratuité illimitée »** sur un compte.
16. [ ] Avec ce compte, créer + **Soumettre** **plusieurs** annonces → toutes
        passent en **En attente de validation** (aucune étape de paiement).
17. [ ] Retirer la gratuité : la soumission suivante repasse **En attente de paiement**.

## D. Modération admin
18. [ ] **Admin → Modération**, filtrer « En attente de paiement ».
19. [ ] Sur l'annonce non abonnée : **Marquer paiement reçu** → passe en attente de validation.
20. [ ] Filtrer « En attente de validation » : **Valider et publier** une annonce.
21. [ ] L'organisateur reçoit un email « annonce publiée ».
22. [ ] Tester **Rejeter** (avec motif) sur une autre annonce → email de rejet avec motif ;
        l'annonce repasse modifiable côté organisateur.
23. [ ] **Exporter les annonces (.txt)** : le bouton télécharge un fichier texte
        contenant toutes les annonces (format « revue », catégories incluses).

## E. Carte publique
24. [ ] Ouvrir `/carte.html` : la vue s'ouvre **cadrée sur la France** (même s'il
        existe des événements à l'étranger) ; seules les annonces **publiées** apparaissent.
        → *Critère : une annonce non validée n'apparaît jamais sur la carte.*
25. [ ] Les marqueurs sont des **pins colorés par mois** ; la **légende** est visible.
26. [ ] **Popup** : titre « Nème … », en-tête « dates, Ville (dépt) » **sans doublon
        d'adresse**, tarif, type, catégories, email de contact, site web, miniature
        d'affiche (cliquable pour agrandir).
27. [ ] **Filtres** (mois, période, **catégorie dont Microminéraux**, type) : la carte
        + la liste se mettent à jour **sans rechargement de page**. → *Critère.*
28. [ ] Les événements passés sont masqués par défaut ; la case « passés » les affiche.
29. [ ] Changer de **fond de carte** (Plan / Clair / Satellite).

## F. Intégration iframe (WordPress)
30. [ ] `/embed.html` renvoie l'en-tête `Content-Security-Policy: frame-ancestors …`.
31. [ ] Dans une page WordPress de test, l'iframe `embed.html` **s'affiche** (desktop),
        avec le bouton **« Publier ma bourse »**.
32. [ ] Idem en **mobile** (largeur ~375 px) : carte + filtres repliables lisibles.
        → *Critère.*
33. [ ] Depuis un domaine **non autorisé**, l'iframe est **bloquée** (CSP) — comportement attendu.

## G. Comptes & sécurité
34. [ ] **Mot de passe oublié** : demande → email avec lien → `reset.html` → nouveau mot de passe.
35. [ ] **Admin → Utilisateurs** : désactiver un compte → cet utilisateur ne peut plus se connecter.
36. [ ] Toute mutation sans jeton CSRF valide échoue (419).
37. [ ] Tentatives de connexion répétées → limitation (429) après le seuil.

## H. Interface
38. [ ] Toute l'interface est **en français**. → *Critère.*
39. [ ] Affichage responsive correct sur mobile (formulaire, tableau de bord, carte).
