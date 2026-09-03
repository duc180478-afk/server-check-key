<?php
// api_check.php

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$key = isset($_GET['key']) ? $_GET['key'] : '';
$hwid = isset($_GET['hwid']) ? $_GET['hwid'] : '';

if (empty($key) || empty($hwid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu Key hoặc HWID!'
    ]);
    exit;
}

$result = validateKey($key, $hwid);
echo json_encode($result);