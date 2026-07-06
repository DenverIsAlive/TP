<?php
require_once 'functions.php';

$jsonData = getFetcherData();
$data = json_decode($jsonData, true);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
    ? "https://"
    : "http://";

$host = $_SERVER['HTTP_HOST'];

// Get current directory path only
$dir = rtrim(dirname($_SERVER['REQUEST_URI']), '/');

// Build clean base
$base = $protocol . $host . $dir;

// Final URLs
$baseMpdUrl = $base . '/manifest.mpd';

$typeFile = 'secure/type.json';

if (file_exists($typeFile)) {

    $typeData = json_decode(file_get_contents($typeFile), true);

    if (isset($typeData['type']) && $typeData['type'] === 'widevine') {
         
         $typee = "com.widevine.alpha";
        $baseKeyUrl = $base . '/jwt.php';

    } elseif (isset($typeData['type']) && $typeData['type'] === 'clearkey') {

         $typee = "clearkey";
        $baseKeyUrl = $base . '/key.php';

    }
}

$ua = $userAgent;
$origin  = "https://watch.tataplay.com";
$referer = "https://watch.tataplay.com/";

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

$player = 'default';

if (strpos($userAgent, 'tivimate') !== false) {
    $player = 'tivimate';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';
} elseif (
    strpos($userAgent, 'ott navigator') !== false ||
    strpos($userAgent, 'ott player') !== false ||
    strpos($userAgent, 'denver1769') !== false
) {
    $player = 'ott';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';
} elseif (
    strpos($userAgent, 'mozilla') !== false ||
    strpos($userAgent, 'dalvik') !== false
) {
    $player = 'ns';
    $ctag = 'catchup-type="null"';
}

$m3uContent = "#EXTM3U x-tvg-url=\"https://avkb.short.gy/epg.xml.gz\"\n\n";

foreach ($data['data']['channels'] as $channel) {

    $id = $channel['id'];
    $name = $channel['name'];
    $logo = $channel['logo_url'];
    $genre = $channel['primaryGenre'];

    $mpdUrl = $baseMpdUrl . '?id=' . $id;
    $keyUrl = $baseKeyUrl . '?id=' . $id;

    $m3uContent .= "#KODIPROP:inputstream.adaptive.license_type={$typee}\n";
    $m3uContent .= "#KODIPROP:inputstream.adaptive.license_key={$keyUrl}\n";

    $m3uContent .= "#EXTINF:-1 tvg-id=\"ts{$id}\" {$ctag} group-title=\"{$genre}\" tvg-logo=\"https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/{$logo}\",{$name}\n";

    // ================= NS PLAYER =================
    if ($player === 'ns') {

        $encodedUa = rawurlencode($ua);

        $m3uContent .= "#EXTVLCOPT:http-user-agent={$ua}\n";
        $m3uContent .= "#EXTVLCOPT:http-origin={$origin}\n";
        $m3uContent .= "#EXTVLCOPT:http-referer={$referer}\n";

        $m3uContent .= $mpdUrl .
            "%7CUser-Agent={$encodedUa}&Origin={$origin}&Referer={$referer}\n\n";
    }

    // ================= OTT NAVIGATOR =================
    elseif ($player === 'ott') {
    	
    $headersJson = json_encode([
    "User-Agent" => $ua,
    "Origin"     => $origin,
    "Referer"    => $referer
], JSON_UNESCAPED_SLASHES);

$m3uContent .= "#EXTHTTP:{$headersJson}\n";
        $m3uContent .= $mpdUrl .
            "|User-Agent={$ua}&Origin={$origin}&Referer={$referer}\n\n";
    }

    // ================= TIVIMATE =================
    elseif ($player === 'tivimate') {

        $m3uContent .= "#EXTVLCOPT:http-user-agent={$ua}\n";
        $m3uContent .= "#EXTVLCOPT:http-origin={$origin}\n";
        $m3uContent .= "#EXTVLCOPT:http-referer={$referer}\n";
        $m3uContent .= "#EXTVLCOPT:http-user-agent={$ua}\n";
        $m3uContent .= "#EXTVLCOPT:http-origin={$origin}\n";

        $m3uContent .= $mpdUrl .
            "|Origin={$origin}\n\n";
    }

    // ================= DEFAULT =================
    else {
        $m3uContent .= $mpdUrl .
            "|User-Agent={$ua}&Origin={$origin}&Referer={$referer}\n\n";
    }
}

echo $m3uContent;
exit;