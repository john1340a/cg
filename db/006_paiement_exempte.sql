-- ============================================================
--  Migration 006 — Exemption de paiement par compte
--  Permet à un organisateur (ex. qui paie déjà une pub pleine page)
--  de publier PLUSIEURS annonces gratuitement, sans supplément.
--  Activable par l'admin dans Back-office → Utilisateurs.
--  Idempotent (IF NOT EXISTS) : peut être rejoué sans risque.
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS paiement_exempte BOOLEAN NOT NULL DEFAULT FALSE;
