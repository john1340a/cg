-- ============================================================
--  Migration 005 — Catégorie « microminéraux »
--  Ajoute une catégorie d'annonce à la table events, en plus de
--  minéraux / fossiles / gemmes / ésotérisme.
--  Idempotent (IF NOT EXISTS) : peut être rejoué sans risque.
-- ============================================================

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS cat_micromineraux BOOLEAN NOT NULL DEFAULT FALSE;
