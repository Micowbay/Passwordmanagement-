<?php
session_start();

// 檢查驗證碼和註冊時間是否存在
if (!isset($_SESSION['verification_code']) || !isset($_SESSION['registration_time'])) {
    die("驗證碼無效或已過期。");
}

// 檢查註冊時間是否超過 2 分鐘
if (time() - $_SESSION['registration_time'] > 120) {
    // 超過 2 分鐘，刪除註冊信息
    $serverName = "MSI"; // 根據您的設定更改
    $connectionOptions = array(
        "Database" => "account_verify", // 請更改為您的資料庫名稱
        "Uid" => "sa", // 請更改為您的使用者名稱
        "PWD" => "jim93329" // 請更改為您的密碼
    );

    // 建立資料庫連接
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $username = $_SESSION['username'];
    
    // 使用正確的 SQL 語句刪除用戶
    $delete_sql = "DELETE FROM users_test WHERE username = ?";
    $delete_stmt = sqlsrv_query($conn, $delete_sql, array($username));

    if ($delete_stmt) {
        echo "註冊信息已被刪除，因為超過 2 分鐘未驗證。";
    } else {
        echo "刪除註冊信息時出現錯誤：" . print_r(sqlsrv_errors(), true);
    }

    // 清除會話
    session_unset();
    session_destroy();
    exit();
}

$error_message = ""; // 用來顯示錯誤消息

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_code = $_POST['verification_code'];

    if ($input_code == $_SESSION['verification_code']) {
        // 驗證成功，將用戶資料插入資料庫
        $serverName = "MSI"; // 根據您的設定更改
        $connectionOptions = array(
            "Database" => "account_verify", // 請更改為您的資料庫名稱
            "Uid" => "sa", // 請更改為您的使用者名稱
            "PWD" => "jim93329" // 請更改為您的密碼
        );

        // 建立資料庫連接
        $conn = sqlsrv_connect($serverName, $connectionOptions);
        if ($conn === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        // 插入用戶資料
        $registration_data = $_SESSION['registration_data'];
        $sql = "INSERT INTO users_test (username, B, C,email, ID_number, birthday) VALUES (?, ?, ?, ?, ?,?)";
        $params = array(
            $registration_data['username'],
            $registration_data['B'],
            $registration_data['C'],
            $registration_data['email'],
            $registration_data['ID_number'],
            $registration_data['birthday'],
        );

        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            echo "
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <title>註冊成功</title>
                <script>
                    let countdown = 5; // 倒數時間
                    function updateCountdown() {
                        const countdownElement = document.getElementById('countdown');
                        if (countdown > 0) {
                            countdownElement.textContent = '將在 ' + countdown + ' 秒鐘後跳轉回首頁。';
                            countdown--;
                        } else {
                            window.location.href = 'index.html'; // 跳轉到首頁
                        }
                    }
                    setInterval(updateCountdown, 1000); // 每秒更新倒數
                </script>
            </head>
            <body>
                <h2>註冊成功！您的帳戶已經激活。</h2>
                <p id='countdown'>將在 5 秒鐘後跳轉回首頁。</p>
            </body>
            </html>
            ";

            // 刪除用戶會話中的驗證碼和其他信息
            unset($_SESSION['verification_code']);
            unset($_SESSION['username']);
            unset($_SESSION['email']);
            unset($_SESSION['registration_time']);
            unset($_SESSION['registration_data']); // 清除註冊資料
            
            exit(); // 結束當前腳本的執行
        } else {
            echo "Error: " . print_r(sqlsrv_errors(), true);
        }
    } else {
        $error_message = "驗證碼不正確，請重試。"; // 設定錯誤消息
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>驗證碼輸入</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
            background-color: #f0f0f0; /* 背景顏色 */
        }
        .form-container {
            background-color: white; /* 你可以修改顏色 */
            width: 80%; /* 調整寬度 */
            height: auto; /* 自動根據內容調整高度 */
            padding: 40px; /* 增加內邊距 */
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 20px auto; /* 居中對齊 */
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h3></h3>
        <h2>請輸入驗證碼</h2>
        <?php if (!empty($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p> <!-- 顯示錯誤消息 -->
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" name="verification_code" required>
            <input type="submit" value="提交">
        </form>
    </div>
</body>
</html>
