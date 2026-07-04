<?php
// Private analytics dashboard for the invitation site.
// Access requires the dashboard password (stored below as a SHA-256 hash,
// so the real password never appears in the repository).

session_start();
header('X-Robots-Tag: noindex, nofollow');

const PW_HASH = 'dd64fbc54bb23952bd81a497f8e25d7faf64758a28e990a32937207e14f11423';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: stats.php');
    exit;
}
$err = '';
if (isset($_POST['pw'])) {
    if (hash('sha256', (string)$_POST['pw']) === PW_HASH) {
        session_regenerate_id(true);
        $_SESSION['auth'] = 1;
        header('Location: stats.php');
        exit;
    }
    usleep(700000); // slow down brute force
    $err = 'Wrong password';
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['auth'])) { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Stats — Login</title>
<style>
body{font-family:system-ui,sans-serif;background:#f4f4ef;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
form{background:#fff;padding:2.2rem;border-radius:16px;box-shadow:0 10px 40px -12px rgba(0,0,0,.15);text-align:center;width:min(90vw,320px)}
h1{font-size:1.1rem;color:#3d4a33;margin:0 0 1rem}
input{width:100%;padding:.7rem .9rem;border:1px solid #ccc;border-radius:10px;font-size:1rem;box-sizing:border-box}
button{margin-top:1rem;width:100%;padding:.7rem;border:0;border-radius:10px;background:#6b7a4f;color:#fff;font-size:1rem;cursor:pointer}
.err{color:#b3392f;font-size:.85rem;margin-top:.6rem}
</style></head><body>
<form method="post" autocomplete="off">
<h1>🌿 Invitation Analytics</h1>
<input type="password" name="pw" placeholder="Password" autofocus>
<button>Sign in</button>
<?php if ($err) echo '<div class="err">'.h($err).'</div>'; ?>
</form></body></html>
<?php exit; }

// ---------- load data ----------
$file = __DIR__ . '/visits.jsonl';
$rows = [];
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $r = json_decode($ln, true);
        if (is_array($r)) $rows[] = $r;
    }
}

// ---------- helpers ----------
function ua_os($ua) {
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iOS';
    if (stripos($ua, 'Android') !== false)      return 'Android';
    if (stripos($ua, 'Windows') !== false)      return 'Windows';
    if (stripos($ua, 'Mac OS') !== false)       return 'macOS';
    if (stripos($ua, 'Linux') !== false)        return 'Linux';
    return $ua === '' ? 'Unknown' : 'Other';
}
function ua_browser($ua) {
    if (stripos($ua, 'Edg') !== false)            return 'Edge';
    if (stripos($ua, 'OPR') !== false)            return 'Opera';
    if (stripos($ua, 'SamsungBrowser') !== false) return 'Samsung Internet';
    if (stripos($ua, 'Firefox') !== false)        return 'Firefox';
    if (stripos($ua, 'CriOS') !== false)          return 'Chrome (iOS)';
    if (stripos($ua, 'Chrome') !== false)         return 'Chrome';
    if (stripos($ua, 'Safari') !== false)         return 'Safari';
    return $ua === '' ? 'Unknown' : 'Other';
}
function ua_device($ua, $d) {
    if (preg_match('/iPad|Tablet/i', $ua)) return 'Tablet';
    if (isset($d['mob']) && ($d['mob'] === '1' || $d['mob'] === 'true')) return 'Mobile';
    if (preg_match('/Mobi|iPhone|Android/i', $ua)) return 'Mobile';
    return 'Desktop';
}
function bump(&$arr, $key) { $key = $key === '' ? '(none)' : $key; $arr[$key] = ($arr[$key] ?? 0) + 1; }
function top($arr, $n = 10) { arsort($arr); return array_slice($arr, 0, $n, true); }

// ---------- aggregate ----------
$views = []; $events = []; $durs = [];
foreach ($rows as $r) {
    $ev = $r['d']['ev'] ?? 'view';
    if ($ev === 'view') $views[] = $r;
    elseif ($ev === 'leave') { $d = (int)($r['d']['dur'] ?? 0); if ($d > 0 && $d < 7200) $durs[] = $d; }
    else bump($events, $ev);
}
$total = count($views);
$uniq = [];
foreach ($views as $r) {
    $id = $r['d']['vid'] ?? '';
    if ($id === '' || $id === 'na') $id = 'ipua:' . md5(($r['ip'] ?? '') . ($r['ua'] ?? ''));
    $uniq[$id] = true;
}
$uniqCount = count($uniq);

$byDay = []; $dev = []; $os = []; $br = []; $cc = []; $lang = []; $scr = []; $ref = []; $src = []; $tz = []; $net = [];
$today = gmdate('Y-m-d'); $todayCount = 0;
foreach ($views as $r) {
    $day = substr($r['ts'] ?? '', 0, 10);
    bump($byDay, $day);
    if ($day === $today) $todayCount++;
    $ua = $r['ua'] ?? '';
    bump($dev, ua_device($ua, $r['d'] ?? []));
    bump($os, ua_os($ua));
    bump($br, ua_browser($ua));
    bump($cc, $r['cc'] ?? '');
    bump($lang, strtolower(substr($r['d']['lang'] ?? explode(',', $r['al'] ?? '')[0], 0, 5)));
    bump($scr, $r['d']['sc'] ?? '');
    $rf = $r['d']['ref'] ?? '';
    bump($ref, $rf === '' ? '(direct)' : (parse_url($rf, PHP_URL_HOST) ?: $rf));
    $pg = $r['d']['page'] ?? '';
    bump($src, parse_url($pg, PHP_URL_HOST) ?: '(unknown)');
    bump($tz, $r['d']['tz'] ?? '');
    bump($net, $r['d']['net'] ?? '');
}
ksort($byDay);
$last14 = array_slice($byDay, -14, 14, true);
$maxDay = max(array_merge([1], array_values($last14)));
$avgDur = $durs ? (int)round(array_sum($durs) / count($durs)) : 0;
$recent = array_slice(array_reverse($views), 0, 40);

function table($title, $arr, $totalN) {
    echo '<div class="card"><h3>' . h($title) . '</h3><table>';
    foreach (top($arr) as $k => $v) {
        $pct = $totalN ? round($v * 100 / $totalN) : 0;
        echo '<tr><td>' . h($k) . '</td><td class="n">' . $v . '</td><td class="bar"><i style="width:' . $pct . '%"></i></td></tr>';
    }
    echo '</table></div>';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>🌿 Invitation Analytics</title>
<style>
:root{--ink:#3d4a33;--soft:#5d6f52;--olive:#6b7a4f;--bg:#f4f4ef}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--ink);margin:0;padding:1.2rem}
.wrap{max-width:1050px;margin:0 auto}
header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;margin-bottom:1.2rem}
h1{font-size:1.25rem;margin:0}
a.btn{color:var(--olive);text-decoration:none;font-size:.85rem;border:1px solid #cfd6c3;background:#fff;padding:.4rem .9rem;border-radius:999px}
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;margin-bottom:1.2rem}
.tile{background:#fff;border-radius:14px;padding:1rem;box-shadow:0 6px 20px -10px rgba(0,0,0,.12)}
.tile .v{font-size:1.7rem;font-weight:700;color:var(--olive)}
.tile .l{font-size:.78rem;color:var(--soft);letter-spacing:.06em;text-transform:uppercase;margin-top:.2rem}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.9rem}
.card{background:#fff;border-radius:14px;padding:1rem 1.1rem;box-shadow:0 6px 20px -10px rgba(0,0,0,.12)}
.card h3{margin:0 0 .7rem;font-size:.95rem;color:var(--soft)}
table{width:100%;border-collapse:collapse;font-size:.86rem}
td{padding:.28rem .3rem;border-bottom:1px solid #f0f0e8;vertical-align:middle}
td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
td.bar{width:40%}
td.bar i{display:block;height:8px;border-radius:99px;background:linear-gradient(90deg,#8fa07f,#6b7a4f);min-width:2px}
.days{display:flex;align-items:flex-end;gap:4px;height:110px}
.days .d{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;font-size:.62rem;color:var(--soft)}
.days .d i{width:100%;background:linear-gradient(180deg,#8fa07f,#6b7a4f);border-radius:4px 4px 0 0;min-height:2px}
.days .d b{font-size:.68rem;color:var(--ink)}
.recent{overflow-x:auto}
.recent table{min-width:760px;font-size:.78rem}
.recent th{text-align:left;color:var(--soft);font-weight:600;padding:.28rem .3rem;border-bottom:2px solid #e4e6da;white-space:nowrap}
.recent td{white-space:nowrap}
footer{color:var(--soft);font-size:.75rem;margin-top:1.2rem;text-align:center}
</style></head><body><div class="wrap">
<header>
  <h1>🌿 സ്നേഹ വിരുന്ന് — Analytics</h1>
  <div><a class="btn" href="stats.php">↻ Refresh</a> <a class="btn" href="stats.php?logout=1">Logout</a></div>
</header>

<div class="tiles">
  <div class="tile"><div class="v"><?= $total ?></div><div class="l">Total visits</div></div>
  <div class="tile"><div class="v"><?= $uniqCount ?></div><div class="l">Unique visitors</div></div>
  <div class="tile"><div class="v"><?= $todayCount ?></div><div class="l">Visits today (UTC)</div></div>
  <div class="tile"><div class="v"><?= $avgDur ?>s</div><div class="l">Avg time on page</div></div>
  <div class="tile"><div class="v"><?= array_sum($events) ?></div><div class="l">Button taps</div></div>
</div>

<div class="card" style="margin-bottom:.9rem">
  <h3>Visits — last 14 days</h3>
  <div class="days">
  <?php foreach ($last14 as $d => $v): ?>
    <div class="d"><b><?= $v ?></b><i style="height:<?= max(2, (int)($v * 90 / $maxDay)) ?>px"></i><span><?= h(substr($d, 5)) ?></span></div>
  <?php endforeach; if (!$last14) echo '<span style="color:#999">No visits recorded yet.</span>'; ?>
  </div>
</div>

<div class="grid">
<?php
table('Devices', $dev, $total);
table('Operating systems', $os, $total);
table('Browsers', $br, $total);
table('Countries', $cc, $total);
table('Guest actions (taps)', $events, max(1, array_sum($events)));
table('Languages', $lang, $total);
table('Screen sizes', $scr, $total);
table('Referrers', $ref, $total);
table('Served from', $src, $total);
table('Time zones', $tz, $total);
table('Connection', $net, $total);
?>
</div>

<div class="card recent" style="margin-top:.9rem">
  <h3>Recent visits (latest 40)</h3>
  <table>
  <tr><th>Time (UTC)</th><th>Country</th><th>IP</th><th>Device</th><th>OS</th><th>Browser</th><th>Screen</th><th>Site lang</th><th>From</th></tr>
  <?php foreach ($recent as $r): $ua = $r['ua'] ?? ''; $d = $r['d'] ?? []; ?>
  <tr>
    <td><?= h(str_replace(['T','+00:00'], [' ',''], $r['ts'] ?? '')) ?></td>
    <td><?= h($r['cc'] ?? '') ?></td>
    <td><?= h($r['ip'] ?? '') ?></td>
    <td><?= h(ua_device($ua, $d)) ?></td>
    <td><?= h(ua_os($ua)) ?></td>
    <td><?= h(ua_browser($ua)) ?></td>
    <td><?= h($d['sc'] ?? '') ?></td>
    <td><?= h($d['ui'] ?? '') ?></td>
    <td><?= h($d['ref'] ? (parse_url($d['ref'], PHP_URL_HOST) ?: $d['ref']) : '(direct)') ?></td>
  </tr>
  <?php endforeach; ?>
  </table>
</div>

<footer><?= count($rows) ?> events on record · data stays on this server (visits.jsonl)</footer>
</div></body></html>
