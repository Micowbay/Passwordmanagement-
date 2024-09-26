<?php
// 生成一個隨機的 32 字元長度的密鑰
$encryption_key = bin2hex(random_bytes(32));

// 將密鑰儲存到安全的地方，例如環境變數或安全的配置文件
echo "生成的密鑰: " . $encryption_key;
?>