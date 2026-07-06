<?php
include 'functions.php';
header('Content-Type: application/json');

if (!logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = (isset($input['type']) && in_array($input['type'], ['widevine', 'clearkey'], true))
    ? $input['type']
    : 'widevine';

$saved = file_put_contents('secure/type.json', json_encode(['type' => $type]));

if ($saved === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not write type.json']);
    exit;
}

echo json_encode(['success' => true, 'type' => $type]);
