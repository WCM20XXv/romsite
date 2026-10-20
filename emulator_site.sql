-- =============================================================
-- Emulator site database (MySQL 8)
--
-- How to run this file:
--   Option A (MySQL Workbench): File > Open SQL Script, pick this
--            file, then click the lightning-bolt "Execute" button.
--   Option B (command line):
--            mysql -u root -p < emulator_site.sql
--
-- Tables are created in dependency order: a table that points at
-- another table (a FOREIGN KEY) must be created after the table it
-- points at.
-- =============================================================

-- Start fresh: delete the database if it exists, then create it.
-- WARNING: the DROP line wipes all data. Remove it once you have
-- real data you want to keep.
DROP DATABASE IF EXISTS emulator_site;
CREATE DATABASE emulator_site
  CHARACTER SET utf8mb4          -- supports every language + emoji
  COLLATE utf8mb4_unicode_ci;    -- case-insensitive text comparisons
USE emulator_site;


-- -------------------------------------------------------------
-- 1. LOOKUP TABLES (they don't point at anything else)
-- -------------------------------------------------------------

-- Genres: Platformer, RPG, Racing, ...
CREATE TABLE genres (
  genre_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- unique ID, MySQL numbers it for you
  name      VARCHAR(50) NOT NULL UNIQUE              -- no two genres with the same name
);

-- Consoles: GameCube, Wii, Wii U, ...
CREATE TABLE consoles (
  console_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL UNIQUE,
  manufacturer  VARCHAR(100) NOT NULL,               -- Nintendo, Sega, Sony ...
  release_year  SMALLINT UNSIGNED NULL,              -- year is a column, not its own table
  emulator      VARCHAR(100) NULL                    -- which emulator runs it (Dolphin, Cemu)
);

-- Publishers: Nintendo, Capcom, Square, ...
CREATE TABLE publishers (
  publisher_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL UNIQUE
);

-- Users: people who sign up on the site
CREATE TABLE users (
  user_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(30)  NOT NULL UNIQUE,
  email          VARCHAR(255) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,              -- NEVER store the real password
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- -------------------------------------------------------------
-- 2. GAMES (points at consoles and publishers)
-- -------------------------------------------------------------

CREATE TABLE games (
  game_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(200) NOT NULL,
  release_year     SMALLINT UNSIGNED NULL,           -- e.g. 1991
  description      TEXT NULL,
  cover_image_url  VARCHAR(500) NULL,
  console_id       INT UNSIGNED NOT NULL,            -- the console it was made for
  publisher_id     INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- publisher_id must match a row in publishers.
  -- If a publisher is deleted, the game stays but its publisher becomes empty.
  -- console_id must match a row in consoles. RESTRICT means MySQL won't
  -- let you delete a console while games still point at it.
  CONSTRAINT fk_games_console
    FOREIGN KEY (console_id) REFERENCES consoles (console_id)
    ON DELETE RESTRICT,

  CONSTRAINT fk_games_publisher
    FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id)
    ON DELETE SET NULL,

  INDEX idx_games_title (title),                     -- makes searching by title fast
  INDEX idx_games_year (release_year)
);


-- -------------------------------------------------------------
-- 3. JUNCTION TABLE (links two tables in a many-to-many)
-- -------------------------------------------------------------

-- A game can have many genres, and a genre has many games.
CREATE TABLE game_genres (
  game_id   INT UNSIGNED NOT NULL,
  genre_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (game_id, genre_id),                   -- the pair can only appear once
  CONSTRAINT fk_gg_game  FOREIGN KEY (game_id)  REFERENCES games  (game_id)  ON DELETE CASCADE,
  CONSTRAINT fk_gg_genre FOREIGN KEY (genre_id) REFERENCES genres (genre_id) ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- 4. USER ACTIVITY (points at users and games)
-- -------------------------------------------------------------

-- One review per user per game, with a 1-5 star rating.
CREATE TABLE reviews (
  review_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  game_id     INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  body        TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT uq_reviews_user_game UNIQUE (user_id, game_id),
  -- Deleting a user or a game also deletes their reviews.
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_game FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE
);

-- A user's saved/favorite games (another many-to-many).
CREATE TABLE favorites (
  user_id   INT UNSIGNED NOT NULL,
  game_id   INT UNSIGNED NOT NULL,
  added_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_fav_game FOREIGN KEY (game_id) REFERENCES games (game_id) ON DELETE CASCADE
);


-- =============================================================
-- 5. SAMPLE DATA (so you can see it working; delete if you like)
-- =============================================================

INSERT INTO genres (name) VALUES ('Platformer'), ('Adventure'), ('Racing'), ('Fighting'), ('Party');

INSERT INTO consoles (name, manufacturer, release_year, emulator) VALUES
  ('GameCube', 'Nintendo', 2001, 'Dolphin'),
  ('Wii',      'Nintendo', 2006, 'Dolphin'),
  ('Wii U',    'Nintendo', 2012, 'Cemu');

INSERT INTO publishers (name) VALUES ('Nintendo'), ('Sega');

-- console_id: 1 = GameCube, 2 = Wii, 3 = Wii U
INSERT INTO games (title, release_year, console_id, publisher_id) VALUES
  ('Super Mario Sunshine',              2002, 1, 1),
  ('The Legend of Zelda: The Wind Waker', 2002, 1, 1),
  ('Super Smash Bros. Melee',           2001, 1, 1),
  ('Super Mario Galaxy',                2007, 2, 1),
  ('Mario Kart Wii',                    2008, 2, 1),
  ('Mario Kart 8',                      2014, 3, 1),
  ('Sonic Colors',                      2010, 2, 2);

INSERT INTO game_genres (game_id, genre_id) VALUES
  (1, 1), (2, 2), (3, 4), (3, 5), (4, 1), (5, 3), (6, 3), (7, 1);

INSERT INTO users (username, email, password_hash) VALUES
  ('retro_fan', 'retro@example.com', 'placeholder-hash'),
  ('pixelqueen', 'pixel@example.com', 'placeholder-hash');

INSERT INTO reviews (user_id, game_id, rating, body) VALUES
  (1, 2, 5, 'Best Zelda on GameCube.'),
  (2, 4, 4, 'Still holds up.');

INSERT INTO favorites (user_id, game_id) VALUES (1, 2), (2, 4), (2, 5);


-- =============================================================
-- 6. EXAMPLE QUERIES (how the tables work together)
-- =============================================================

-- Every game with its console and genre(s):
SELECT g.title, c.name AS console, ge.name AS genre
FROM games g
JOIN consoles c     ON c.console_id = g.console_id
JOIN game_genres gg ON gg.game_id = g.game_id
JOIN genres ge      ON ge.genre_id = gg.genre_id
ORDER BY c.release_year, g.title;

-- Average star rating per game (games with no reviews show NULL):
SELECT g.title, ROUND(AVG(r.rating), 1) AS avg_rating, COUNT(r.review_id) AS review_count
FROM games g
LEFT JOIN reviews r ON r.game_id = g.game_id
GROUP BY g.game_id, g.title
ORDER BY avg_rating DESC;

-- All Wii games, newest first:
SELECT g.title, g.release_year
FROM games g
JOIN consoles c ON c.console_id = g.console_id
WHERE c.name = 'Wii'
ORDER BY g.release_year DESC;
