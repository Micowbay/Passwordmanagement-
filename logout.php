<?php
session_start();
session_unset(); // 清除所有的 session 變量
session_destroy();  // 銷毀所有 session 資料
header("Location: index.html");  // 重定向到登入頁面
exit();
?>
