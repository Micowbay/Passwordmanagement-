<?php
session_start();
$timeLeft = $_SESSION['logout_time'] - time();
echo json_encode(['timeLeft' => $timeLeft]);
?>
