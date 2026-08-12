-- ============================================================
--  Migration 004 — Lien de paiement en ligne (WooCommerce)
--  Ajoute le paramètre « lien_paiement » : URL de la fiche produit
--  WooCommerce vers laquelle l'organisateur est redirigé pour régler
--  les 10 €. Modifiable ensuite par l'admin (Back-office → Paramètres).
--  ON CONFLICT DO NOTHING : ne réécrase pas une valeur déjà saisie.
-- ============================================================

INSERT INTO settings (cle, valeur) VALUES
    ('lien_paiement',
     'https://mineralogique.com/produit/publication-devenement-sur-la-carte/')
ON CONFLICT (cle) DO NOTHING;
