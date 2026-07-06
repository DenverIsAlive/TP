<?php
require_once 'functions.php';
error_reporting(0);
header("Content-Type: application/json");
date_default_timezone_set('Asia/Kolkata');

$id = $_GET['id'] ?? exit(json_encode(['error' => 'Missing id!']));

// Check if `cache/` directory exists
if (!is_dir('cache')) {
    mkdir('cache', 0777, true);
}

// Define cache file path
$cacheFile = "cache/hmac_$id";

// Check if the cache exists
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    
    if (
        isset($cacheData['hmac']['hdnea']['value']) &&
        preg_match('/exp=(\d+)/', $cacheData['hmac']['hdnea']['value'], $matches)
    ) {
        $hdnea_exp_unix = (int)$matches[1];
        
        // Return cached data if still valid
        if ($hdnea_exp_unix > time()) {
            echo json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// Read and decode credentials
$UserData = file_get_contents("secure/_sessionData");
$json = json_decode($UserData, true);

if (!$json) {
    exit(json_encode(['error' => 'Invalid or missing credentials']));
}

$TPAUTH = array(
    'accessToken' => $json['data']['accessToken'],
    'refreshToken' => $json['data']['refreshToken'],
    'sid' => $json['data']['userDetails']['sid'],
    'sname' => $json['data']['userDetails']['sName'],
    'profileId' => $json['data']['userProfile']['id']
);

function decrypt_source_url($encrypted_url) {
    $SECRET_KEY = "aesEncryptionKey";
    $cipher = "AES-128-ECB";
    
    // Remove last 3 characters
    $encrypted_url = substr($encrypted_url, 0, -3);
    $decoded_data = base64_decode($encrypted_url);
    if ($decoded_data === false) {
        return "Error: Base64 decoding failed.";
    }

    $decrypted = openssl_decrypt($decoded_data, $cipher, $SECRET_KEY, OPENSSL_RAW_DATA);
    if ($decrypted === false) {
        return "Error: Decryption failed.";
    }

    // Remove padding
    $padding_length = ord(substr($decrypted, -1));
    if ($padding_length >= 1 && $padding_length <= 16) {
        $decrypted = substr($decrypted, 0, -$padding_length);
    } else {
        $decrypted = rtrim($decrypted);
    }

    return $decrypted;
}

function extract_exp_time($token) {
    preg_match('/exp=(\d+)/', $token, $matches);
    if (isset($matches[1])) {
        return date("d/m/Y h:i A", $matches[1]); // Convert to Kolkata time
    }
    return "N/A";
}

function capture_cookies($url) {
	global $userAgent;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.9',
        'Origin: https://watch.tataplay.com',
        'Referer: https://watch.tataplay.com/',
        "User-Agent: $userAgent"
    ]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    if ($response === false) {
        return ['error' => 'cURL error: ' . curl_error($ch)];
    }
    
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    $header = substr($response, 0, $header_size);
    $responseHeaders = substr($response, 0, $header_size);
    
    // Extract Set-Cookie value
    if (preg_match('/Set-Cookie:\s*hdntl=([^;\r\n]+)/i', $header, $matches)) {
    return 'hdntl=' . $matches[1];
}

// 2. If no Set-Cookie, try Location header
if (preg_match('/Location:\s*[^\r\n]*[?&]hdntl=([^&\r\n]+)/i', $header, $matches)) {
    return 'hdntl=' . $matches[1];
}
    
    return $header; // Return null if hdntl is not found
}

$chnDetailsAPI = 'https://tm.tapi.videoready.tv/digital-feed-services/api/partner/cdn/player/details/LIVE/' . $id;
$chnDlHeads = array(
    'accept: */*',
    'accept-language: en-US,en;q=0.9,en-IN;q=0.8',
    'authorization: ' . $TPAUTH['accessToken'],
    'content-type: application/json',
    "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"7683d93848b0f472c508e38b1827038a\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $TPAUTH['sname'] . "\"}", 
    'kp: false',
    'locale: ENG',
    'origin: https://watch.tataplay.com',
    'platform: web',
    'priority: u=1, i',
    "profileid: " . $TPAUTH['profileId'],
    'referer: https://watch.tataplay.com/',
    "user-agent: $userAgent"
);

$process = curl_init($chnDetailsAPI);
curl_setopt($process, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($process, CURLOPT_HTTPHEADER, $chnDlHeads);
curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
curl_setopt($process, CURLOPT_ENCODING, 'gzip, deflate, br');

$chnOut = curl_exec($process);
$http_code = curl_getinfo($process, CURLINFO_HTTP_CODE);
curl_close($process);

if ($http_code != 200) {
    exit(json_encode(['error' => 'API request failed', 'status_code' => $http_code]));
}

$vUData = @json_decode($chnOut, true);
if (!$vUData || !isset($vUData['data'])) {
    exit(json_encode(['error' => 'Invalid API response']));
}

$mpd = decrypt_source_url($vUData['data']['dashWidewinePlayUrl'] ?? '');
$widevine = decrypt_source_url($vUData['data']['dashWidewineLicenseUrl'] ?? '');

$parsedUrl = parse_url($mpd);
$query_params = [];
parse_str($parsedUrl['query'] ?? '', $query_params);

$hdnea_value = $query_params['hdnea'] ?? 'N/A';
$hdnea_exp_time = extract_exp_time($hdnea_value);
$hdnea = 'hdnea=' . $hdnea_value;

$hdntl_value = capture_cookies($mpd);
$hdntl_exp_time = extract_exp_time($hdntl_value);

$dash_Url = str_replace("bpwta", "bpaita", $mpd);

$data = [
    "Type" => "TP Cookie API",
    "Author" => "@Denver1769",
    "Channel" => "http://t.me/DenverIsAlivee",
    "mpd_link" => $dash_Url,
    "widevine" => $widevine,
    "hmac" => [
        "hdnea" => [
            "value" => $hdnea,
            "expires_at" => $hdnea_exp_time,
        ],
        "hdntl" => [
            "value" => $hdntl_value,
            "expires_at" => $hdntl_exp_time,
        ],
    ],
    "userAgent" => $userAgent,
    "NOTE" => 'Use with Provided User-Agent only!'
];

// Cache the data
file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>