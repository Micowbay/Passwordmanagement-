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
$sql = "SELECT * FROM users_test WHERE username = ?";
$params = array($username);
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(json_encode(["success" => false, "message" => "查詢錯誤"]));
}

function xor_strings($str1, $str2) {
    $result = '';
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    $length = min($len1, $len2); // 取最小長度

    for ($i = 0; $i < $length; $i++) {
        $result .= $str1[$i] ^ $str2[$i]; // 逐位 XOR
    }
    return $result;
}
// 驗證密碼
if ($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // 第一步：計算 Ui
    $r = base64_decode("ukBcYWAon+g//jF/Q2lbTvjEBtl0H1p0Xz2UrK+RpQQ=");
    $C = hex2bin($user['C']); // 假設資料庫中有 C 欄位

    if (strlen($inputPassword) < strlen($C)) {
        $inputPassword = str_pad($inputPassword, strlen($C), "\0", STR_PAD_RIGHT);
    } else if (strlen($inputPassword) > strlen($C)) {
        $C = str_pad($C, strlen($inputPassword), "\0", STR_PAD_RIGHT);
    }
    
    $Ui_da = xor_strings($C, $inputPassword); // Ui_da = C XOR password
    
    // 確保 Ui_da 的長度與 C 一致
    if (strlen($Ui_da) < strlen($C)) {
        $Ui_da = str_pad($Ui_da, strlen($C), "\0", STR_PAD_RIGHT);
    }
    
    $A_da = hash('sha256', $inputPassword . $Ui_da, true);
    $B_prime = hash('sha256', $A_da . $username . $r, true);
    $B_prime_BIN = bin2hex($B_prime);
    // 第四步：比較 B' 與資料庫中的 B
    if ($B_prime_BIN === $user['B']) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
