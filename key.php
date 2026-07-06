<?php

require_once 'functions.php';

$id = $_GET['id'] ?? '';

if ($id === '') {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Missing id!']));
}

$url = 'https://local.denver69.fun/TKey/index.php?id=' . urlencode($id);

$headers = [
    'Accept: */*',
    'Content-Type: application/json',
    'Connection: keep-alive'
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => $userAgent,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);

if ($response === false) {
    die('cURL Error: ' . curl_error($ch));
}

curl_close($ch);

header('Content-Type: application/json');
echo $response;