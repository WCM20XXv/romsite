<?php
require 'database.php';

$jsonData = file_get_contents('data-forretrosite.json');
$data = json_decode($jsonData, true);

if (!$data) {
    die("Error reading data-forretrosite.json");
}

// 1. Import Consoles
$stmtConsole = $pdo->prepare("INSERT IGNORE INTO consoles (name, manufacturer, release_year, emulator) VALUES (?, ?, ?, ?)");
foreach ($data['consoles'] as $c) {
    $stmtConsole->execute([$c['name'], $c['manufacturer'], $c['year'], $c['emulator']]);
}

// 2. Import Publishers
$stmtPub = $pdo->prepare("INSERT IGNORE INTO publishers (name) VALUES (?)");
foreach ($data['publishers'] as $pub) {
    $stmtPub->execute([$pub]);
}

// Fetch lookup maps for IDs
$consolesMap = $pdo->query("SELECT name, console_id FROM consoles")->fetchAll(PDO::FETCH_KEY_PAIR);
$pubsMap     = $pdo->query("SELECT name, publisher_id FROM publishers")->fetchAll(PDO::FETCH_KEY_PAIR);
$genresMap   = $pdo->query("SELECT name, genre_id FROM genres")->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Import Games & Genres
$stmtCheckGame = $pdo->prepare("SELECT game_id FROM games WHERE title = ? AND console_id = ?");
$stmtInsertGame = $pdo->prepare("INSERT INTO games (title, release_year, console_id, publisher_id) VALUES (?, ?, ?, ?)");
$stmtGenre = $pdo->prepare("INSERT IGNORE INTO genres (name) VALUES (?)");
$stmtGameGenre = $pdo->prepare("INSERT IGNORE INTO game_genres (game_id, genre_id) VALUES (?, ?)");

foreach ($data['games'] as $g) {
    $consoleId = $consolesMap[$g['console']] ?? null;
    $pubId = $pubsMap[$g['publisher']] ?? null;

    if (!$consoleId) continue;

    // Avoid duplicating games already in the SQL sample data
    $stmtCheckGame->execute([$g['title'], $consoleId]);
    $gameId = $stmtCheckGame->fetchColumn();

    if (!$gameId) {
        $stmtInsertGame->execute([$g['title'], $g['year'], $consoleId, $pubId]);
        $gameId = $pdo->lastInsertId();
    }

    // Link Genres
    if (!empty($g['genres'])) {
        foreach ($g['genres'] as $genreName) {
            if (!isset($genresMap[$genreName])) {
                $stmtGenre->execute([$genreName]);
                $genresMap[$genreName] = $pdo->lastInsertId();
            }
            $stmtGameGenre->execute([$gameId, $genresMap[$genreName]]);
        }
    }
}

echo "JSON data successfully imported into MySQL! You can delete or ignore this file now.";
?>
