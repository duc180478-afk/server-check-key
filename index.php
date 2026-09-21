<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Server is running!',
    'endpoint' => 'https://server-check-key-vwoq.onrender.com/api_check.php?key=KEY&hwid=HWID'
]);