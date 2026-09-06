<?php
// test_link4m.php — CHẨN ĐOÁN API link4m. ⚠️ XÓA FILE NÀY SAU KHI XONG!
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

 $API = '6a68cadf75156e7ce6748248';
 $base = 'https://link4m.co/st?api=' . $API . '&url=';

 $tests = [
    'google NGUYEN BAN (raw)'     => $base . 'https://google.com',
    'google ENCODE'               => $base . urlencode('https://google.com'),
    'google co tham so ?x=1 (raw)' => $base . 'https://google.com/?x=1',
];

foreach ($tests as $name => $url) {
    echo "========== $name ==========\n";
    echo "Goi: $url\n\n";
    $resp = keygenHttpGet($url); // cần keygen_functions.php
    if ($resp === false) {
        echo ">>> LOI: khong ket noi duoc!\n\n";
    } else {
        echo ">>> RESPONSE THO:\n$resp\n\n";
        $short = parseLink4mResponse($resp);
        echo $short ? ">>> PARSE OK, link: $short\n\n" : ">>> PARSE THAT BAI (format khong hieu duoc)\n\n";
    }
}