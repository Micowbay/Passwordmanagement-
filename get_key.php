<?php
// 获取环境变量中的密钥
header('Content-Type: application/json');
$encodedKey = getenv('SECRET_KEY');

if ($encodedKey === false) {
    die(json_encode(['error' => 'Secret key not found!']));
}

$decodedKey = base64_decode($encodedKey);
echo json_encode(['secretKey' => $decodedKey]);
?>
