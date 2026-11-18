<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Server is running!',
    'endpoint' => 'https://getkey2.onrender.com/api_check.php?key=KEY&hwid=HWID'
]);