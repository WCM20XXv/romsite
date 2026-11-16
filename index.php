<?php
session_start();
require 'database.php';

// 1. Fetch Consoles formatted for your JS array: {n, m, y, e}
$consolesData = $pdo->query("
    SELECT name AS n, manufacturer AS m, release_year AS y, emulator AS e 
    FROM consoles 
    ORDER BY release_year ASC
")->fetchAll();

// 2. Fetch Games formatted for your JS array: {t, y, c, p, g}
$gamesRaw = $pdo->query("
    SELECT g.title AS t, g.release_year AS y, c.name AS c, COALESCE(p.name, 'Unknown') AS p,
           GROUP_CONCAT(DISTINCT ge.name SEPARATOR '||') AS genres_str
    FROM games g
    JOIN consoles c ON g.console_id = c.console_id
    LEFT JOIN publishers p ON g.publisher_id = p.publisher_id
    LEFT JOIN game_genres gg ON g.game_id = gg.game_id
    LEFT JOIN genres ge ON gg.genre_id = ge.genre_id
    GROUP BY g.game_id
")->fetchAll();

$gamesData = [];
foreach ($gamesRaw as $row) {
    $gamesData[] = [
        't' => $row['t'],
        'y' => (int)$row['y'],
        'c' => $row['c'],
        'p' => $row['p'],
        'g' => $row['genres_str'] ? explode('||', $row['genres_str']) : []
    ];
}

// 3. Fetch Reviews formatted for your JS array: {u, t, r, b}
$reviewsData = $pdo->query("
    SELECT u.username AS u, g.title AS t, CAST(r.rating AS UNSIGNED) AS r, r.body AS b
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN games g ON r.game_id = g.game_id
    ORDER BY r.created_at DESC
")->fetchAll();

// 4. Fetch Favorites formatted for your JS object: {username: [game1, game2]}
$favsRaw = $pdo->query("
    SELECT u.username, g.title
    FROM favorites f
    JOIN users u ON f.user_id = u.user_id
    JOIN games g ON f.game_id = g.game_id
    ORDER BY f.added_at ASC
")->fetchAll();

$favsData = [];
foreach ($favsRaw as $row) {
    $favsData[$row['username']][] = $row['title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Emulator Site - Game Catalog</title>
<style>
  :root{--desk:#2b2b6b;--silver:#d4d0c8;--navy:#0a246a;--sky:#a6caf0;--paper:#fffff0;--hot:#ff3300;--lime:#99ff00;--purple:#6a1fb5}
  *{box-sizing:border-box}
  body{margin:0;padding:14px;font:14px/1.45 Verdana,Tahoma,Geneva,sans-serif;color:#000;background-color:var(--desk);
    background-image:radial-gradient(circle,rgba(255,255,255,.35) 1px,transparent 1.5px);background-size:22px 22px}
  a{color:#0000ee} a:visited{color:#551a8b} a:hover{color:var(--hot)}
  .win{max-width:900px;margin:0 auto;background:var(--silver);border:2px solid;border-color:#fff #404040 #404040 #fff;box-shadow:6px 6px 0 rgba(0,0,0,.5);padding:3px}
  .titlebar{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:4px 6px;font-weight:bold;font-size:13px;color:#fff;
    background:linear-gradient(90deg,var(--navy),var(--purple) 65%,var(--sky))}
  .btns span{display:inline-block;width:18px;height:16px;margin-left:2px;text-align:center;line-height:14px;font-size:11px;color:#000;background:var(--silver);border:2px solid;border-color:#fff #404040 #404040 #fff}
  .menu{padding:3px 6px;border-bottom:1px solid #808080;font-size:13px}
  .menu a{margin-right:14px;color:#000;text-decoration:none}.menu a:hover{text-decoration:underline;color:var(--navy)}
  .ticker{background:#000;color:var(--lime);font:bold 13px "Courier New",monospace;padding:4px 0;overflow:hidden;white-space:nowrap}
  .ticker span{display:inline-block;padding-left:100%;animation:scroll 26s linear infinite}
  @keyframes scroll{to{transform:translateX(-100%)}}
  .page{display:grid;grid-template-columns:200px 1fr;gap:6px;padding:6px}
  .side,.main{background:var(--paper);border:2px solid;border-color:#808080 #fff #fff #808080;padding:10px}
  h1{margin:0 0 6px;padding:6px 10px;font:bold 38px/1.05 "Arial Black",Impact,sans-serif;letter-spacing:-1px;color:#fff;text-shadow:2px 2px 0 #000;
    background:linear-gradient(#7b3fe0,#0a246a);border:2px solid;border-color:#fff #404040 #404040 #fff}
  h2{margin:18px 0 6px;padding:3px 8px;font:bold 15px Verdana,sans-serif;color:#fff;background:var(--navy)}
  .side h3{margin:0 0 6px;font-size:13px;color:var(--navy);border-bottom:2px dotted var(--navy)}
  .side ul{margin:0 0 14px;padding-left:16px}
  .counter{display:inline-block;padding:2px 8px;font:bold 18px "Courier New",monospace;letter-spacing:4px;color:var(--lime);background:#000;border:2px inset #888}
  .blink{color:var(--hot);font-weight:bold;animation:blink 1s steps(2,start) infinite}
  @keyframes blink{to{visibility:hidden}}
  .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;padding:6px;background:var(--silver);border:2px solid;border-color:#fff #808080 #808080 #fff}
  .bar select,.bar input{font:13px Verdana;padding:2px;border:2px inset #999}
  .bar input{flex:1;min-width:120px}
  button{font:bold 13px Verdana;padding:3px 12px;cursor:pointer;background:var(--silver);border:2px solid;border-color:#fff #404040 #404040 #fff}
  button:active{border-color:#404040 #fff #fff #404040}
  table.list{width:100%;border-collapse:collapse;font-size:13px;background:#fff}
  table.list th{padding:3px 6px;text-align:left;background:var(--silver);border:2px solid;border-color:#fff #808080 #808080 #fff}
  table.list td{padding:3px 6px;border:1px solid #c0c0c0}
  table.list tr:nth-child(even) td{background:#eef0ff}
  .stars{color:#e69500;letter-spacing:1px;white-space:nowrap}
  .tag{display:inline-block;margin:0 2px 2px 0;padding:0 5px;font-size:11px;color:#fff;background:var(--purple);border:1px solid #000}
  .cons{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:6px}
  .box{padding:8px;background:#fff;border:2px inset #999}
  .box b{color:var(--navy)}
  .rev{margin:6px 0;padding:6px 8px;background:#fff;border:2px inset #999}
  .login input{width:100%;margin:2px 0 6px;padding:3px;font:13px Verdana;border:2px inset #999}
  .auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .notice{padding:6px 8px;margin-bottom:8px;background:#eef0ff;border:2px solid var(--navy);font-weight:bold}
  .tiny{margin-top:10px;font-size:11px;color:#444;text-align:center}
  .status{display:flex;justify-content:space-between;padding:3px 8px;font-size:12px;border-top:1px solid #808080}
  :focus-visible{outline:2px dotted var(--hot);outline-offset:2px}
  @media (prefers-reduced-motion:reduce){.ticker span,.blink{animation:none;padding-left:8px}}
  @media (max-width:700px){.page,.auth-grid{grid-template-columns:1fr}h1{font-size:26px}}
</style>
</head>
<body>
<div class="win">
  <div class="titlebar"><span>Emulator Site - Internet Explorer</span><span class="btns"><span>_</span><span>□</span><span>x</span></span></div>
  <div class="menu"><a href="#browse">Browse</a><a href="#consoles">Consoles</a><a href="#reviews">Reviews</a><a href="#favorites">Favorites</a><a href="#join">Join / Log In</a></div>
  <div class="ticker"><span>*** Welcome to the Emulator Site!!! *** Browse the game catalog *** Rate your favorites *** Best viewed at 800x600 in Internet Explorer 6 ***</span></div>

  <div class="page">
    <aside class="side">
      <h3>Menu</h3>
      <ul>
        <li><a href="#browse">Browse Games</a></li>
        <li><a href="#consoles">Consoles</a></li>
        <li><a href="#reviews">Reviews</a></li>
        <li><a href="#favorites">Favorites</a></li>
        <li><a href="#join">Join Us!</a></li>
      </ul>
      <h3>Visitor #</h3>
      <p><span class="counter" id="count">000042</span></p>
      <h3>Site Stats</h3>
      <p id="stats"></p>
      <p><span class="blink">NEW!</span> Reviews &amp; favorites</p>
    </aside>

    <main class="main">
      <h1>EMULATOR SITE</h1>
      <p><b>The game catalog for classic consoles.</b> Look up a game, see which console and emulator it goes with, and read what other members think.</p>

      <h2 id="browse">🎮 Browse Games</h2>
      <div class="bar">
        <label>Console: <select id="fc" aria-label="Console filter"><option value="">All</option></select></label>
        <label>Genre: <select id="fg" aria-label="Genre filter"><option value="">All</option></select></label>
        <input id="fs" type="text" placeholder="Search by title..." aria-label="Search by title">
        <button type="button" id="reset">Reset</button>
      </div>
      <table class="list">
        <thead><tr><th>Title</th><th>Console</th><th>Year</th><th>Publisher</th><th>Genre</th><th>Rating</th></tr></thead>
        <tbody id="games"></tbody>
      </table>
      <p class="tiny" id="shown"></p>

      <h2 id="consoles">💾 Consoles</h2>
      <div class="cons" id="consoleBoxes"></div>

      <h2 id="reviews">★ Member Reviews</h2>
      <div id="revs"></div>

      <h2 id="favorites">❤ Favorites</h2>
      <div id="favs"></div>

      <h2 id="join">✎ Club Membership</h2>

      <?php if (isset($_GET['joined'])): ?>
        <div class="notice">Welcome to the club! Your account has been created—you can log in below.</div>
      <?php endif; ?>

      <?php if (isset($_SESSION['username'])): ?>
        <div class="box">
          Logged in as <b><?= htmlspecialchars($_SESSION['username']) ?></b>! 
          <a href="site-logout.php"><button type="button">Log Out</button></a>
        </div>
      <?php else: ?>
        <div class="auth-grid">
          <div class="box login">
            <b>Join the Club (Sign Up)</b><br><br>
            <form method="POST" action="signup_setup.php">
              <label>Username<input type="text" name="username" required aria-label="Username"></label>
              <label>Email<input type="email" name="email" required aria-label="Email"></label>
              <label>Password<input type="password" name="password" required aria-label="Password"></label>
              <button type="submit">Sign Me Up!</button>
            </form>
          </div>

          <div class="box login">
            <b>Member Log In</b><br><br>
            <form method="POST" action="site-login.php">
              <label>Username<input type="text" name="username" required aria-label="Login Username"></label>
              <label>Password<input type="password" name="password" required aria-label="Login Password"></label>
              <button type="submit">Log In</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <p class="tiny">&copy; 2026 Emulator Site. Game titles belong to their respective owners.</p>
    </main>
  </div>
  <div class="status"><span>Done</span><span>🌐 Internet</span></div>
</div>

<script>
// Live data injected directly from MySQL via PHP
var consoles = <?= json_encode($consolesData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var games    = <?= json_encode($gamesData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var reviews  = <?= json_encode($reviewsData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var favs     = <?= json_encode($favsData ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function $(i){return document.getElementById(i)}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]})}
function stars(n){return "\u2605".repeat(n)+"\u2606".repeat(5-n)}
function avg(t){var r=reviews.filter(function(x){return x.t===t});if(!r.length)return null;
  return Math.round(r.reduce(function(a,x){return a+Number(x.r)},0)/r.length)}

var genres=[];games.forEach(function(g){g.g.forEach(function(x){if(genres.indexOf(x)<0)genres.push(x)})});
consoles.forEach(function(c){$("fc").add(new Option(c.n,c.n))});
genres.sort().forEach(function(g){$("fg").add(new Option(g,g))});

function render(){
  var c=$("fc").value,g=$("fg").value,s=$("fs").value.toLowerCase();
  var rows=games.filter(function(x){return(!c||x.c===c)&&(!g||x.g.indexOf(g)>-1)&&(!s||x.t.toLowerCase().indexOf(s)>-1)})
   .sort(function(a,b){return a.y-b.y||a.t.localeCompare(b.t)});
  $("games").innerHTML=rows.map(function(x){var a=avg(x.t);
    return "<tr><td>"+esc(x.t)+"</td><td>"+esc(x.c)+"</td><td>"+x.y+"</td><td>"+esc(x.p)+"</td><td>"+
    x.g.map(function(t){return '<span class="tag">'+esc(t)+"</span>"}).join("")+
    '</td><td class="stars">'+(a?stars(a):"no reviews")+"</td></tr>"}).join("")||'<tr><td colspan="6">No games match. Try clearing a filter.</td></tr>';
  $("shown").textContent="Showing "+rows.length+" of "+games.length+" games";
}
["fc","fg"].forEach(function(i){$(i).onchange=render});$("fs").oninput=render;
$("reset").onclick=function(){$("fc").value="";$("fg").value="";$("fs").value="";render()};

$("consoleBoxes").innerHTML=consoles.map(function(c){var n=games.filter(function(g){return g.c===c.n}).length;
  return '<div class="box"><b>'+esc(c.n)+"</b><br>"+esc(c.m)+", "+c.y+"<br>Emulator: "+esc(c.e)+"<br>"+n+" game"+(n===1?"":"s")+"</div>"}).join("");
$("revs").innerHTML=reviews.map(function(r){return '<div class="rev"><b>'+esc(r.t)+'</b> <span class="stars">'+stars(Number(r.r))+"</span><br>&ldquo;"+esc(r.b)+"&rdquo; - <i>"+esc(r.u)+"</i></div>"}).join("");
$("favs").innerHTML=Object.keys(favs).map(function(u){return '<div class="box" style="margin-bottom:6px"><b>'+esc(u)+"</b>: "+favs[u].map(esc).join(", ")+"</div>"}).join("");
$("stats").innerHTML=games.length+" games<br>"+consoles.length+" consoles<br>"+reviews.length+" reviews";
render();

try{var n=parseInt(localStorage.getItem("emu_visits")||"42",10)+1;localStorage.setItem("emu_visits",n);$("count").textContent=("000000"+n).slice(-6)}catch(e){}
</script>
</body>
</html>