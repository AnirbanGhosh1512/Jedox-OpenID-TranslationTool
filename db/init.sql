-- Translation Tool Database Schema

CREATE TABLE IF NOT EXISTS sids (
    id          SERIAL PRIMARY KEY,
    sid         VARCHAR(255) NOT NULL UNIQUE,
    default_text TEXT NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS translations (
    id          SERIAL PRIMARY KEY,
    sid_id      INTEGER NOT NULL REFERENCES sids(id) ON DELETE CASCADE,
    lang_id     VARCHAR(20) NOT NULL,
    text        TEXT NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (sid_id, lang_id)
);

-- Seed some sample data
INSERT INTO sids (sid, default_text) VALUES
    ('app.title',       'Translation Tool'),
    ('app.welcome',     'Welcome to the Translation Tool'),
    ('btn.save',        'Save'),
    ('btn.cancel',      'Cancel'),
    ('btn.delete',      'Delete'),
    ('lbl.language',    'Language'),
    ('msg.nodata',      'No data available')
ON CONFLICT DO NOTHING;

INSERT INTO translations (sid_id, lang_id, text)
SELECT s.id, 'de-DE', t.text
FROM (VALUES
    ('app.title',    'Übersetzungswerkzeug'),
    ('app.welcome',  'Willkommen beim Übersetzungswerkzeug'),
    ('btn.save',     'Speichern'),
    ('btn.cancel',   'Abbrechen'),
    ('btn.delete',   'Löschen'),
    ('lbl.language', 'Sprache'),
    ('msg.nodata',   'Keine Daten verfügbar')
) AS t(sid, text)
JOIN sids s ON s.sid = t.sid
ON CONFLICT DO NOTHING;

INSERT INTO translations (sid_id, lang_id, text)
SELECT s.id, 'fr-FR', t.text
FROM (VALUES
    ('app.title',    'Outil de traduction'),
    ('app.welcome',  'Bienvenue dans l''outil de traduction'),
    ('btn.save',     'Enregistrer'),
    ('btn.cancel',   'Annuler'),
    ('btn.delete',   'Supprimer'),
    ('lbl.language', 'Langue'),
    ('msg.nodata',   'Aucune donnée disponible')
) AS t(sid, text)
JOIN sids s ON s.sid = t.sid
ON CONFLICT DO NOTHING;