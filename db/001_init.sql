-- ============================================================
--  Migration 001 — Schéma initial
--  Application : Calendrier cartographique des bourses aux minéraux
--  SGBD : PostgreSQL 13+ / PostGIS 3+
--
--  Ce script est idempotent (IF NOT EXISTS) et peut être rejoué.
-- ============================================================

-- --- Extensions ---
CREATE EXTENSION IF NOT EXISTS postgis;    -- géométries + fonctions spatiales
CREATE EXTENSION IF NOT EXISTS citext;     -- emails insensibles à la casse
CREATE EXTENSION IF NOT EXISTS pgcrypto;   -- gen_random_bytes() pour les jetons

-- ============================================================
--  Table : users (organisateurs + admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id             SERIAL PRIMARY KEY,
    nom            TEXT        NOT NULL,
    prenom         TEXT        NOT NULL,
    email          CITEXT      NOT NULL UNIQUE,
    password_hash  TEXT        NOT NULL,
    role           TEXT        NOT NULL DEFAULT 'user'
                               CHECK (role IN ('user', 'admin')),
    est_abonne     BOOLEAN     NOT NULL DEFAULT FALSE,
    est_actif      BOOLEAN     NOT NULL DEFAULT TRUE,
    token_reset    TEXT,
    token_expire   TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ============================================================
--  Table : subscribers_whitelist (emails abonnés à la revue)
--  Importée par l'admin (CSV). Sert à décider est_abonne à
--  l'inscription et à accorder la 1re annonce gratuite.
-- ============================================================
CREATE TABLE IF NOT EXISTS subscribers_whitelist (
    id          SERIAL PRIMARY KEY,
    email       CITEXT      NOT NULL UNIQUE,
    ajoute_par  INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ============================================================
--  Table : events (annonces de bourses)
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
    id             SERIAL PRIMARY KEY,
    owner_id       INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,

    -- Identité de l'événement
    intitule       TEXT        NOT NULL,
    edition_num    TEXT,                       -- « n° d'édition » (texte libre)

    -- Dates
    date_debut     DATE        NOT NULL,
    date_fin       DATE        NOT NULL,

    -- Type d'événement (les deux possibles)
    type_echanges  BOOLEAN     NOT NULL DEFAULT FALSE,  -- Bourse d'échanges
    type_vente     BOOLEAN     NOT NULL DEFAULT FALSE,  -- Bourse de vente

    -- Catégories (multi)
    cat_mineraux    BOOLEAN    NOT NULL DEFAULT FALSE,
    cat_fossiles    BOOLEAN    NOT NULL DEFAULT FALSE,
    cat_gemmes      BOOLEAN    NOT NULL DEFAULT FALSE,  -- gemmes / bijoux
    cat_esoterisme  BOOLEAN    NOT NULL DEFAULT FALSE,  -- ésotérisme / lithothérapie

    -- Localisation
    adresse        TEXT        NOT NULL,
    geom           geometry(Point, 4326),             -- point géocodé (BAN)

    -- Informations pratiques
    tarif          TEXT,                              -- tarif d'entrée (texte libre)
    contact_email  TEXT        NOT NULL,              -- email affiché publiquement
    site_web       TEXT,
    affiche_path   TEXT,                              -- chemin du fichier image

    -- Cycle de vie
    statut         TEXT        NOT NULL DEFAULT 'brouillon'
                               CHECK (statut IN (
                                   'brouillon',
                                   'en_attente_paiement',
                                   'en_attente_validation',
                                   'publie',
                                   'rejete'
                               )),
    motif_rejet    TEXT,
    est_gratuite   BOOLEAN     NOT NULL DEFAULT FALSE,  -- 1re annonce d'un abonné

    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_dates CHECK (date_fin >= date_debut)
);

-- Index utiles à la carte publique et à la modération
CREATE INDEX IF NOT EXISTS idx_events_statut     ON events (statut);
CREATE INDEX IF NOT EXISTS idx_events_date_debut ON events (date_debut);
CREATE INDEX IF NOT EXISTS idx_events_owner      ON events (owner_id);
CREATE INDEX IF NOT EXISTS idx_events_geom       ON events USING GIST (geom);

-- ============================================================
--  Table : payments_log (suivi simple des paiements)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments_log (
    id          SERIAL PRIMARY KEY,
    event_id    INTEGER     NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    montant     NUMERIC(6,2) NOT NULL DEFAULT 10.00,
    statut      TEXT        NOT NULL DEFAULT 'attendu'
                            CHECK (statut IN ('attendu', 'recu', 'exonere')),
    note        TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_payments_event ON payments_log (event_id);

-- ============================================================
--  Table : settings (paramètres éditables par l'admin, clé/valeur)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    cle         TEXT        PRIMARY KEY,
    valeur      TEXT        NOT NULL DEFAULT '',
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ============================================================
--  Déclencheur : mise à jour automatique de updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_users_updated  ON users;
CREATE TRIGGER trg_users_updated  BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

DROP TRIGGER IF EXISTS trg_events_updated ON events;
CREATE TRIGGER trg_events_updated BEFORE UPDATE ON events
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
