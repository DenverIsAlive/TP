<?php
error_reporting(0);

$userAgent = 'Shraddha/5.1';

function logged_in() {
    return file_exists("secure/_sessionData");
}

function getCreds() {
    if (!logged_in()) {
        http_response_code(403);
       die("Not logged in.");
    }
    $data = file_get_contents("secure/_sessionData");
    $json = json_decode($data, true);
    return [
        'accessToken' => $json['data']['accessToken'],
        'sid' => $json['data']['userDetails']['sid'],
        'sname' => $json['data']['userDetails']['sName'],
        'profileId' => $json['data']['userProfile']['id']
    ];
}

// JSON path
function getFetcherData() {
    $filename = 'app/data.json';
    if (!file_exists(dirname($filename))) {mkdir(dirname($filename), 0755, true);}
    $cacheTime = 86400;
    $apiUrl = 'https://local.denver69.fun/test/data.json';
    if (file_exists($filename) && (time() - filemtime($filename)) < $cacheTime) {
        $cachedData = @file_get_contents($filename);
        if ($cachedData !== false) {return $cachedData;}
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && !empty($response)) {
        file_put_contents($filename, $response);
        return $response;
    }
    http_response_code(500);
    die("error while fetching fetcher data.");
}

