-- Daily thought in every language (Gedagte van die Dag).
--
-- daily_thoughts gets the language a thought is written in (lang) and a
-- fingerprint of its text (source_hash). daily_thought_translations holds
-- one translation per other app language; a translation only counts while
-- its source_hash matches the thought's, so changing a thought never shows
-- an outdated translation.
--
-- The app also creates these itself on first use (includes/daily_thoughts.php
-- dt_ensure_schema), so running this by hand is optional. Existing thoughts
-- are taken to be Afrikaans; they are translated the first time an elder
-- presses "Translate missing languages", or just before a thought is sent.
--
-- Back up first. Run once (MySQL / MariaDB); a column that already exists
-- makes its ALTER fail, which is harmless.

ALTER TABLE daily_thoughts ADD COLUMN lang VARCHAR(5) NOT NULL DEFAULT 'af' AFTER author;
ALTER TABLE daily_thoughts ADD COLUMN source_hash CHAR(64) DEFAULT NULL AFTER lang;

CREATE TABLE IF NOT EXISTS daily_thought_translations (
    thought_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    source_hash CHAR(64) NOT NULL,
    is_manual TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thought_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
