-- =============================================================
-- Load games.csv into the emulator_site database (MySQL 8)
--
-- Run emulator_site.sql first, so the database and tables exist.
--
-- games.csv columns: title, release_year, console, publisher, genres
--   genres holds zero or more genre names joined by ";"
--   e.g. "Action;Adventure". It is blank when we have no genre
--   for that game (about 1 in 4 games, mostly Wii U eShop titles).
--
-- Why a "staging" table? The CSV has console, publisher and genre
-- as NAMES ("Wii", "Capcom", "Racing"), but our tables store ID
-- NUMBERS (console_id, publisher_id, genre_id). So we load the CSV
-- into a plain holding table first, then let SQL look up the IDs.
--
-- Safe to run more than once: nothing gets duplicated.
-- =============================================================

USE emulator_site;

-- 1. A holding table shaped exactly like the CSV.
--    release_year is text here because a few games have it blank.
DROP TABLE IF EXISTS games_import;
CREATE TABLE games_import (
  title         VARCHAR(200) NOT NULL,
  release_year  VARCHAR(4)   NULL,
  console       VARCHAR(100) NOT NULL,
  publisher     VARCHAR(100) NULL,
  genres        VARCHAR(200) NULL
);

-- 2. Load the CSV into the holding table.
--
--    Option A (MySQL Workbench, easiest): skip this LOAD DATA
--    statement (select it and delete it, or put -- in front of
--    each line). Run step 1 first, then in the left sidebar
--    refresh, right-click games_import > Table Data Import Wizard >
--    pick games.csv > "Use existing table" > Next until done.
--    Then run steps 3-7.
--
--    Option B (command line): change the path below to where
--    games.csv is on your computer, then run this whole file with
--      mysql --local-infile=1 -u root -p < import_games.sql
--    (If MySQL says "local data is disabled", run this once as root:
--      SET GLOBAL local_infile = 1;  )
LOAD DATA LOCAL INFILE '/path/to/games.csv'
INTO TABLE games_import
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES                       -- skip the header row
(title, release_year, console, publisher, genres);

-- 3. Add any publishers we don't have yet.
--    INSERT IGNORE skips names already in the table (name is UNIQUE),
--    so 'Nintendo' and 'Sega' from the sample data are not duplicated.
INSERT IGNORE INTO publishers (name)
SELECT DISTINCT publisher
FROM games_import
WHERE publisher IS NOT NULL AND publisher <> '';

-- 4. Copy the games over, swapping names for IDs.
--    JOIN consoles   -> finds console_id from the console name
--    LEFT JOIN publishers -> finds publisher_id (NULL if none)
--    NOT EXISTS      -> skips games already in the table (like the
--                       7 sample games), so running this twice
--                       doesn't create duplicates.
INSERT INTO games (title, release_year, console_id, publisher_id)
SELECT gi.title,
       NULLIF(gi.release_year, ''),   -- blank year becomes NULL
       c.console_id,
       p.publisher_id
FROM games_import gi
JOIN consoles c        ON c.name = gi.console
LEFT JOIN publishers p ON p.name = gi.publisher
WHERE NOT EXISTS (
  SELECT 1 FROM games g
  WHERE g.title = gi.title AND g.console_id = c.console_id
);

-- 5. Split the genres column into one row per genre.
--    "Action;Adventure" becomes two rows: Action, Adventure.
--
--    How: the little numbers list (1 to 6) says "give me the 1st
--    genre, the 2nd genre, ..." and SUBSTRING_INDEX cuts out that
--    piece. The ON line stops at however many genres the game has
--    (count of ";" + 1). No game has more than 6.
DROP TABLE IF EXISTS game_genres_import;
CREATE TABLE game_genres_import AS
SELECT gi.title,
       gi.console,
       TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(gi.genres, ';', n.n), ';', -1)) AS genre
FROM games_import gi
JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3
      UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) n
  ON n.n <= 1 + LENGTH(gi.genres) - LENGTH(REPLACE(gi.genres, ';', ''))
WHERE gi.genres IS NOT NULL AND gi.genres <> '';

-- 6a. Add any genres we don't have yet (same trick as publishers).
INSERT IGNORE INTO genres (name)
SELECT DISTINCT genre
FROM game_genres_import;

-- 6b. Link each game to its genres in the junction table.
--     Look up game_id (by title + console) and genre_id (by name).
--     INSERT IGNORE skips pairs that are already there.
INSERT IGNORE INTO game_genres (game_id, genre_id)
SELECT g.game_id, ge.genre_id
FROM game_genres_import ggi
JOIN consoles c ON c.name = ggi.console
JOIN games g    ON g.title = ggi.title AND g.console_id = c.console_id
JOIN genres ge  ON ge.name = ggi.genre;

-- 7. Clean up the holding tables.
DROP TABLE game_genres_import;
DROP TABLE games_import;

-- Check it worked: how many games per console?
SELECT c.name AS console, COUNT(*) AS games
FROM games g
JOIN consoles c ON c.console_id = g.console_id
GROUP BY c.name
ORDER BY c.name;

-- ...and how many games in each genre?
SELECT ge.name AS genre, COUNT(*) AS games
FROM genres ge
JOIN game_genres gg ON gg.genre_id = ge.genre_id
GROUP BY ge.name
ORDER BY games DESC;
