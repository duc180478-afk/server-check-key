<?php
// test_link4m.php — CHẨN ĐOÁN API link4m. ⚠️ XÓA FILE NÀY SAU KHI XONG!

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

header('Content-Type: text/plain; charset=utf-8');

 $API = '6a68cadf75156e7ce6748248';
 $base = 'https://link4m.co/st?api=' . $API . '&url=';

 $tests = [
    '1. google NGUYEN BAN (raw)'      => $base . 'https://google.com',
    '2. google ENCODE'                => $base . urlencode('https://google.com'),
    '3. viewkey gia lap (raw)'        => $base . getSiteBaseUrl() . '/viewkey.php?token=test123',
];

foreach ($tests as $name => $url) {
    echo "========== $name ==========\n";
    echo "Goi: $url\n\n";

    $resp = keygenHttpGet($url);

    if ($resp === false) {
        echo ">>> LOI: khong ket noi duoc den link4m!\n\n";
        continue;
    }

    echo ">>> HTTP xong. RESPONSE THO:\n";
    echo $resp . "\n\n";

    $short = parseLink4mResponse($resp);
    if ($short) {
        echo ">>> PARSE OK! Link rut gon: $short\n\n";
    } else {
        echo ">>> PARSE THAT BAI — link4m tra ve cai gi do khong hieu duoc.\n\n";
    }
}

echo "===== XONG TEST =====\n";