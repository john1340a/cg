-- ============================================================
--  Migration 002 — Paramètres par défaut (settings)
--  Valeurs modifiables ensuite par l'admin dans le back-office.
--  ON CONFLICT DO NOTHING : ne réécrase pas une valeur déjà saisie.
-- ============================================================

INSERT INTO settings (cle, valeur) VALUES
    ('instructions_paiement',
     E'Merci de régler 10 € par annonce.\n\n' ||
     E'Par virement :\n' ||
     E'  IBAN : FR76 0000 0000 0000 0000 0000 000\n' ||
     E'  Titulaire : MINERALOGIQUE\n' ||
     E'  Référence : votre email + intitulé de l''annonce\n\n' ||
     E'Ou par chèque à l''ordre de MINERALOGIQUE, adressé à :\n' ||
     E'  MINERALOGIQUE, 90 Cours de l''Yser, 33800 Bordeaux\n\n' ||
     E'Votre annonce sera publiée après réception du paiement et validation.'),

    ('email_expediteur', 'contact@bourses-mineraux.fr'),

    ('nom_expediteur', 'Bourses aux Minéraux'),

    ('montant_annonce', '10'),

    ('iframe_domain', '')
ON CONFLICT (cle) DO NOTHING;
