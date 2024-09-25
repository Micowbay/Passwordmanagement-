<?php
session_start();

// 資料庫連接設定
$serverName = "MSI";
$connectionOptions = array(
    "Database" => "account_verify",
    "Uid" => "sa",
    "PWD" => "jim93329"
);

// 連接資料庫
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(json_encode(["success" => false, "message" => "資料庫連接失敗"]));
}

// 確保使用者已登入並在 session 中儲存了 username
if (!isset($_SESSION['username'])) {
    die(json_encode(["success" => false, "message" => "使用者未登入"]));
}

$username = $_SESSION['username'];
$inputPassword = $_POST['password'];

// 從資料庫中查詢使用者的登入密碼
$sql = "SELECT password FROM users WHERE username = ?";
$params = array($username);
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(json_encode(["success" => false, "message" => "查詢錯誤"]));
}

// 獲取資料庫中的密碼
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$storedPassword = $row['password'];

// 驗證密碼
if (password_verify($inputPassword, $storedPassword)) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
