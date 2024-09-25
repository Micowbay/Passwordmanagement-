<?php
session_start();

// 检查 GD 库是否已启用
if (!extension_loaded('gd') || !function_exists('gd_info')) {
    die('GD 库未启用。请启用 GD 库以生成 CAPTCHA 图片。');
}

// 創建一個空白圖像
$image = imagecreatetruecolor(120, 40);

// 設定背景色
$background_color = imagecolorallocate($image, 255, 255, 255);
imagefilledrectangle($image, 0, 0, 120, 40, $background_color);

// 設定文字顏色
$font_color = imagecolorallocate($image, 0, 0, 0);

// 設定 CAPTCHA 字符
$captcha_code = '';
$possible_chars = 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghjkmnprstuvwxyz23456789';
for ($i = 0; $i < 6; $i++) {
    $captcha_code .= $possible_chars[mt_rand(0, strlen($possible_chars) - 1)];
}

// 保存 CAPTCHA 文字到 session
$_SESSION['captcha_code'] = $captcha_code;

// 在圖像上畫出 CAPTCHA 文字
imagestring($image, 5, 10, 10, $captcha_code, $font_color);

// 設定 MIME 類型
header('Content-Type: image/png');

// 輸出圖像
imagepng($image);

// 清理
imagedestroy($image);
?>
