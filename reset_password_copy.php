<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "MSI";
$dbname = "account_verify";
$username = "sa"; // MS SQL username
$password = "jim93329"; // MS SQL password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取用户提交的新密码和令牌
    $new_password = $_POST['new_password'];
    $reset_token = $_POST['reset_token'];
    
    // 连接到数据库
    $connectionInfo = array(
        "Database" => $dbname,
        "UID" => $username,
        "PWD" => $password
    );
    $conn = sqlsrv_connect($servername, $connectionInfo);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // 验证令牌的有效性并获取关联的用户
    $query = "SELECT email FROM password_resets WHERE token = ?";
    $params = array($reset_token);
    $stmt = sqlsrv_query($conn, $query, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    //xor function
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

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $user_email = $row['email'];

        // 使用新的密码更新用户密码
        $Ui = random_bytes(16);
        if (strlen($new_password) < strlen($Ui)) {
            // 用零填充到 Ui 的長度
            $new_password = str_pad($new_password, strlen($Ui), "\0"); // "\0" 是空字符
        } else if (strlen($new_password) > strlen($Ui)) {
            // 如果密碼比 Ui 長，則用零填充 Ui
            $Ui = str_pad($Ui, strlen($new_password), "\0"); // "\0" 是空字符
        }
        
        // 計算 A
        $A = hash('sha256', $new_password . $Ui, true); // true 參數返回二進位數據
        
        // 伺服器密鑰
        $r = base64_decode("ukBcYWAon+g//jF/Q2lbTvjEBtl0H1p0Xz2UrK+RpQQ="); // 伺服器密鑰
        
        // 計算 B
        $username_bin = $username; // 直接使用用戶名字符串
        $B = hash('sha256', $A . $username_bin . $r, true); // 返回二進位數據
        
        // 計算 C
        $C = xor_strings($new_password, $Ui); // 用二進位密碼進行 XOR

        //將所有變數轉為二進制

        $Ui_BIN = bin2hex($Ui);
        $A_BIN = bin2hex($A);
        $B_BIN = bin2hex($B);
        $C_BIN = bin2hex($C);
        
        $update_query = "UPDATE users_test SET B = ?, C = ? WHERE email = ?";
        $params = array($B_BIN, $C_BIN, $user_email);
        $update_stmt = sqlsrv_query($conn, $update_query, $params);

        if ($update_stmt === false) {
            die(print_r(sqlsrv_errors(), true));  // 如果更新密碼時出錯，顯示錯誤
        }

        // 删除已使用的令牌
        $delete_query = "DELETE FROM password_resets WHERE token = ?";
        $delete_stmt = sqlsrv_query($conn, $delete_query, array($reset_token));

        if ($delete_stmt === false) {
            die(print_r(sqlsrv_errors(), true));  // 如果刪除令牌時出錯，顯示錯誤
        }

        echo "Your password has been successfully reset.";
        echo '<br><br>';
        echo '<a href="index.html"><button>Return to Home</button></a>';

    } else {
        echo "Invalid token.";  // 如果令牌無效，顯示錯誤
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
} else {
    // 如果没有 POST 数据，显示重设密码表单
    if (isset($_GET['token'])) {
        $reset_token = $_GET['token'];
    } else {
        die("Invalid request.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            background-color: #007bff;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Your Password</h2>
        <p>Please enter your new password.</p>
        <form method="POST" action="">
            <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($reset_token); ?>">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="submit" value="Reset Password">
        </form>
    </div>
</body>
</html>
