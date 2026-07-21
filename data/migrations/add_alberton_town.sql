-- =====================================================================
--  Voeg die opsienerskap / dorp ALBERTON en sy gemeentes by
-- =====================================================================
--  Provinsie : Gauteng, Suid-Afrika
--  Gemeentes : Brackendowns, Generaal Albertspark, Verwoerdpark,
--              Mulbarton, Comptonville
--
--  Wat hierdie skrip doen:
--    1. Skep die dorp 'Alberton' in `towns` (onder Gauteng).
--    2. Skep die 5 gemeentes in `congregations` (gekoppel aan Alberton).
--    3. Skep die kamer-rye in `rooms` sodat die app se outomatiese
--       lidmaatskap iets het om by aan te sluit:
--         - 1x 'opsienerskap' kamer vir die dorp
--         - 1x 'sondagskool'  kamer vir die dorp
--         - 1x 'jeug'         kamer vir die dorp
--         - 1x 'gemeente'     kamer per gemeente (5 stuks)
--
--  BELANGRIK: Die outomatiese lidmaatskap-logika (ouderdom / huwelik-
--  status / dorp / gemeente in permissions.php + rooms.php) besluit net
--  WIE by 'n BESTAANDE kamer aansluit -- dit SKEP nie die kamer-rye self
--  nie. Daarom moet hierdie rye in die databasis wees.
--
--  Veilig om meer as een keer te loop: elke INSERT word beskerm met
--  'n bestaanskontrole, so herhaling maak nie duplikate nie.
--
--  Loop dit teen die webwerf se databasis, bv.:
--    mysql -u <gebruiker> -p <databasis> < data/migrations/add_alberton_town.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Vind die Gauteng-provinsie (bestaan reeds vir die ander dorpe)
-- ---------------------------------------------------------------------
SET @province_id = (SELECT id FROM provinces WHERE name = 'Gauteng' LIMIT 1);

-- ---------------------------------------------------------------------
-- 2. Skep die dorp / opsienerskap Alberton
-- ---------------------------------------------------------------------
INSERT INTO towns (name, province_id)
SELECT 'Alberton', @province_id FROM DUAL
WHERE @province_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM towns WHERE name = 'Alberton' AND province_id = @province_id) x);

SET @town_id = (SELECT id FROM towns WHERE name = 'Alberton' AND province_id = @province_id LIMIT 1);

-- ---------------------------------------------------------------------
-- 3. Skep die gemeentes (elk gekoppel aan Alberton)
-- ---------------------------------------------------------------------
INSERT INTO congregations (name, town_id)
SELECT 'Brackendowns', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM congregations WHERE name = 'Brackendowns' AND town_id = @town_id) x);

INSERT INTO congregations (name, town_id)
SELECT 'Generaal Albertspark', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM congregations WHERE name = 'Generaal Albertspark' AND town_id = @town_id) x);

INSERT INTO congregations (name, town_id)
SELECT 'Verwoerdpark', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM congregations WHERE name = 'Verwoerdpark' AND town_id = @town_id) x);

INSERT INTO congregations (name, town_id)
SELECT 'Mulbarton', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM congregations WHERE name = 'Mulbarton' AND town_id = @town_id) x);

INSERT INTO congregations (name, town_id)
SELECT 'Comptonville', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM congregations WHERE name = 'Comptonville' AND town_id = @town_id) x);

-- ---------------------------------------------------------------------
-- 4. Dorp-vlak kamers: opsienerskap, sondagskool, jeug
--    (die naam word op die dorpnaam gestel; die app wys dit met die
--     regte tipe-etiket voor -- sien formatRoomDisplayName in rooms.php)
-- ---------------------------------------------------------------------
INSERT INTO rooms (name, type, town_id)
SELECT 'Alberton', 'opsienerskap', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'opsienerskap' AND town_id = @town_id) x);

INSERT INTO rooms (name, type, town_id)
SELECT 'Alberton', 'sondagskool', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'sondagskool' AND town_id = @town_id) x);

INSERT INTO rooms (name, type, town_id)
SELECT 'Alberton', 'jeug', @town_id FROM DUAL
WHERE @town_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'jeug' AND town_id = @town_id) x);

-- ---------------------------------------------------------------------
-- 5. Gemeente-kamers: een per gemeente (gekoppel via gemeente_id)
-- ---------------------------------------------------------------------
SET @cong_brackendowns = (SELECT id FROM congregations WHERE name = 'Brackendowns'         AND town_id = @town_id LIMIT 1);
SET @cong_albertspark  = (SELECT id FROM congregations WHERE name = 'Generaal Albertspark' AND town_id = @town_id LIMIT 1);
SET @cong_verwoerdpark = (SELECT id FROM congregations WHERE name = 'Verwoerdpark'         AND town_id = @town_id LIMIT 1);
SET @cong_mulbarton    = (SELECT id FROM congregations WHERE name = 'Mulbarton'            AND town_id = @town_id LIMIT 1);
SET @cong_comptonville = (SELECT id FROM congregations WHERE name = 'Comptonville'         AND town_id = @town_id LIMIT 1);

INSERT INTO rooms (name, type, gemeente_id, town_id)
SELECT 'Brackendowns', 'gemeente', @cong_brackendowns, @town_id FROM DUAL
WHERE @cong_brackendowns IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = @cong_brackendowns) x);

INSERT INTO rooms (name, type, gemeente_id, town_id)
SELECT 'Generaal Albertspark', 'gemeente', @cong_albertspark, @town_id FROM DUAL
WHERE @cong_albertspark IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = @cong_albertspark) x);

INSERT INTO rooms (name, type, gemeente_id, town_id)
SELECT 'Verwoerdpark', 'gemeente', @cong_verwoerdpark, @town_id FROM DUAL
WHERE @cong_verwoerdpark IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = @cong_verwoerdpark) x);

INSERT INTO rooms (name, type, gemeente_id, town_id)
SELECT 'Mulbarton', 'gemeente', @cong_mulbarton, @town_id FROM DUAL
WHERE @cong_mulbarton IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = @cong_mulbarton) x);

INSERT INTO rooms (name, type, gemeente_id, town_id)
SELECT 'Comptonville', 'gemeente', @cong_comptonville, @town_id FROM DUAL
WHERE @cong_comptonville IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = @cong_comptonville) x);

-- ---------------------------------------------------------------------
-- 6. Verifikasie (opsioneel) -- wys wat geskep is
-- ---------------------------------------------------------------------
-- SELECT * FROM towns          WHERE id = @town_id;
-- SELECT * FROM congregations  WHERE town_id = @town_id ORDER BY name;
-- SELECT id, name, type, town_id, gemeente_id FROM rooms
--   WHERE town_id = @town_id
--      OR gemeente_id IN (@cong_brackendowns, @cong_albertspark, @cong_verwoerdpark, @cong_mulbarton, @cong_comptonville)
--   ORDER BY type, name;
