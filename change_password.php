<?php
session_start();

// 檢查使用者是否已登入
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// 取得密碼的 ID
if (!isset($_GET['id'])) {
    die("無效的密碼 ID");
}

$id = $_GET['id'];

// 資料庫連接
$serverName = "MSI";
$connectionOptions = array(
    "Database" => "account_verify",
    "Uid" => "sa",
    "PWD" => "jim93329"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// 取得目前的密碼資訊
$sql = "SELECT website_name, account_name, encrypted_password, notes FROM passwordmanage WHERE id = ? AND username = ?";
$params = array($id, $_SESSION['username']);
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$passwordData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$passwordData) {
    die("找不到密碼資訊");
}

// 更新密碼
// 更新密碼
// 更新密碼
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 檢查兩次密碼是否一致
    if ($new_password !== $confirm_password) {
        die("兩次輸入的密碼不相同");
    }

    // 高強度密碼檢查: 至少7位數、英文大小寫、數字、特殊符號
    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{7,}$/';
    if (!preg_match($passwordPattern, $new_password)) {
        die("密碼強度不足，請使用至少7位數、包含大小寫字母、數字和特殊符號的密碼");
    }

    // 生成新的 IV 並加密新密碼
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted_password = openssl_encrypt($new_password, 'aes-256-cbc', 'your-encryption-key', 0, $iv);

    // 更新資料庫中的密碼
    $encrypted_data = base64_encode($iv . $encrypted_password);
    $sql = "UPDATE passwordmanage SET encrypted_password = ?, created_at = GETDATE() WHERE id = ? AND username = ?";
    $params = array($encrypted_data, $id, $_SESSION['username']);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // 使用 JavaScript 顯示彈窗並跳轉
    echo "<script>
        alert('請記得新密碼喔!');
        window.location.href = 'index.html';
    </script>";
    exit();
}




sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>更換密碼</title>
</head>
<body>
    <h2>更換密碼</h2>
    <form action="change_password.php?id=<?php echo $id; ?>" method="post">
        <p>網站: <?php echo htmlspecialchars($passwordData['website_name']); ?></p>
        <p>帳號: <?php echo htmlspecialchars($passwordData['account_name']); ?></p>
        <p>備註: <?php echo htmlspecialchars($passwordData['notes']); ?></p>
        <label for="new_password">新密碼：</label>
        <input type="password" id="new_password" name="new_password" required>
        <br>
        <label for="confirm_password">確認新密碼：</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <br>
        
        <span id="error-message" style="color: red; display: none;"></span>

        <br>
        <input type="submit" value="更新密碼">

    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const errorMessage = document.getElementById('error-message');
            const submitButton = document.querySelector('input[type="submit"]');

            function validatePasswords() {
                const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{7,}$/;
                if (newPassword.value !== confirmPassword.value) {
                    errorMessage.innerText = '兩次輸入的密碼不相同';
                    errorMessage.style.display = 'inline';
                    submitButton.disabled = true;
                } else if (!passwordPattern.test(newPassword.value)) {
                    errorMessage.innerText = '密碼強度不足，需包含大小寫字母、數字和特殊符號，且至少7位數';
                    errorMessage.style.display = 'inline';
                    submitButton.disabled = true;
                } else {
                    errorMessage.style.display = 'none';
                    submitButton.disabled = false;
                }
            }

            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });
    </script>


</body>
</html>
