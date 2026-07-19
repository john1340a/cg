-- ============================================================
--  Migration 003 — Table de limitation de débit (rate limiting)
--  Utilisée pour brider les tentatives de connexion et d'inscription
--  par IP + action, sur une fenêtre glissante.
-- ============================================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id         SERIAL PRIMARY KEY,
    cle        TEXT        NOT NULL,   -- ex : « login:203.0.113.5 »
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_cle ON rate_limits (cle, created_at);
