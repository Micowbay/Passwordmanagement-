<?php
session_start();
// 設定倒數時間
$_SESSION['logout_time'] = time() + 300; // 設置新的300秒倒數
define('ENCRYPTION_KEY', 'c4674028985c2a1272812a551ff9dc131ba4ae6d503be70dea2686287daf133c');
// 每次加載頁面時重新計算剩餘時間
$timeLeft = $_SESSION['logout_time'] - time();
if ($timeLeft <= 0) {
    // 如果時間到了，執行登出操作
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}

// 如果有登出請求
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.html");
    exit();
}//這段程式碼檢查是否有登出請求，如果有則清除所有Session資料並將使用者重新導向到 index.html。


// Check if user is verified
if (!isset($_SESSION['is_verified']) || !$_SESSION['is_verified']) {
    header("Location: verify.php");
    exit();
}

// Database connection parameters
$serverName = "MSI";
$connectionOptions = array(
    "Database" => "account_verify",
    "Uid" => "sa",
    "PWD" => "jim93329"
);

// Establishes the connection
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Check if the connection was successful
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Get the username from the session (assuming it's stored there after login)
$username = $_SESSION['username'];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_password'])) {
        $website_name = $_POST['site'];
        $account_name = $_POST['account'];
        $password_plaintext = $_POST['password'];
        $notes = $_POST['notes'] ?? null;
        $created_at = $_POST['created_at'];

        // 檢查是否已存在相同的密碼
        $sql_check = "SELECT encrypted_password FROM passwordmanage WHERE username = ?";
        $check_stmt = sqlsrv_query($conn, $sql_check, array($username));

        if ($check_stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $existing_passwords = [];

        while ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            $encrypted_data = base64_decode($row['encrypted_password']);

            // 提取IV（前16位元組）和加密密碼
            $iv = substr($encrypted_data, 0, 16);
            $encrypted_password = substr($encrypted_data, 16);

            // 解密密碼
            $decrypted_password = openssl_decrypt($encrypted_password, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
            $existing_passwords[] = $decrypted_password; // 保存解密後的密碼
        }

        // 檢查用戶輸入的密碼是否與已解密的密碼相同
        if (in_array($password_plaintext, $existing_passwords)) {
            $password_exists_message = "已經有其他網站使用此密碼";
        } else {
            // 如果密碼不重複，則插入新密碼
            $sql = "INSERT INTO passwordmanage (username, website_name, account_name, encrypted_password, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $params = array($username, $website_name, $account_name, $encrypted_data, $notes, $created_at);
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                die(print_r(sqlsrv_errors(), true));
            }
        }
    }
}
// 處理顯示密碼的請求
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'show_password') {
    $id = $_POST['id'];

    // 從資料庫中查詢儲存的加密密碼
    $sql = "SELECT encrypted_password FROM passwordmanage WHERE username = ? AND id = ?";
    $params = array($username, $id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "查詢錯誤"]);
        exit();
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $encrypted_data = base64_decode($row['encrypted_password']);

    // 提取IV（前16位元組）和加密密碼
    $iv = substr($encrypted_data, 0, 16);
    $encrypted_password = substr($encrypted_data, 16);

    // 解密密碼
    $decrypted_password = openssl_decrypt($encrypted_password, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);

    echo json_encode(["success" => true, "decrypted_password" => $decrypted_password]);
    exit();
}


// Apply filters if submitted
$filter_conditions = [];
$filter_params = [];

if (!empty($_GET['site'])) {
    $filter_conditions[] = "website_name LIKE ?";
    $filter_params[] = "%" . $_GET['site'] . "%";
}
if (!empty($_GET['account'])) {
    $filter_conditions[] = "account_name LIKE ?";
    $filter_params[] = "%" . $_GET['account'] . "%";
}
if (!empty($_GET['notes'])) {
    $filter_conditions[] = "notes LIKE ?";
    $filter_params[] = "%" . $_GET['notes'] . "%";
}
if (!empty($_GET['created_at'])) {
    $filter_conditions[] = "created_at = ?";
    $filter_params[] = $_GET['created_at'];
}
if (!empty($_GET['alert']) && $_GET['alert'] == "1") {
    $filter_conditions[] = "DATEDIFF(day, created_at, GETDATE()) > 60";
}

$sql = "SELECT id, website_name, account_name, encrypted_password, notes, created_at FROM passwordmanage WHERE username = ?";
$filter_params = array_merge([$username], $filter_params);
if (!empty($filter_conditions)) {
    $sql .= " AND " . implode(" AND ", $filter_conditions);
}

$stmt = sqlsrv_query($conn, $sql, $filter_params);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$passwords = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $encrypted_data = base64_decode($row['encrypted_password']);

    // Extract the IV (first 16 bytes) and the encrypted password
    $iv = substr($encrypted_data, 0, 16);
    $encrypted_password = substr($encrypted_data, 16);

    // Decrypt the password
    $decrypted_password = openssl_decrypt($encrypted_password, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    $row['decrypted_password'] = $decrypted_password;

    // Check if the password is older than 60 days
    $created_at = new DateTime($row['created_at']->format('Y-m-d'));
    $today = new DateTime();
    $interval = $today->diff($created_at);

    if ($interval->days > 60) {
        $row['password_alert'] = "需要更換密碼";
    } else {
        $row['password_alert'] = "";
    }

    $passwords[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Manager</title>
    <script>
        function generatePassword() {
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
            let password = "";
            for (let i = 0; i < 12; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById("password").value = password;
        }
    </script>
    <script>
        // 確保 DOM 完全加載後執行倒數計時
        window.onload = function() {
            let timeLeft = <?php echo $timeLeft; ?>; // 從PHP獲取剩餘時間
            const timerElement = document.getElementById("timer");

            const countdown = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    // 畫面變空白
                    document.body.innerHTML = "";

                    // 延遲顯示逾時通知，確保畫面先變空白
                    setTimeout(() => {
                        alert("逾時，請重新登入");
                        // 導向 index.html
                        window.location.href = "index.html";
                    }, 100); // 0.1 秒後顯示彈窗
                } else {
                    timerElement.textContent = timeLeft;
                }
                timeLeft -= 1;
            }, 1000);

            // 定期向伺服器請求更新剩餘時間，確保計時不會因切換頁面而暫停
            setInterval(() => {
                fetch('timer_update.php')
                    .then(response => response.json())
                    .then(data => {
                        timeLeft = data.timeLeft;
                    });
            }, 10000); // 每10秒向伺服器請求一次
        };

    </script>
    <script>
        function showPassword(id) {
            const passwordField = document.getElementById('password-' + id);
            const toggleButton = document.getElementById('toggle-button-' + id);
            
            if (passwordField.type === 'password') {
                // 提示使用者輸入系統密碼來顯示儲存的密碼
                const systemPassword = prompt("請輸入系統密碼來顯示儲存的密碼：");

                if (systemPassword) {
                    // 使用 AJAX 發送請求驗證系統密碼
                    const xhr = new XMLHttpRequest();
                    xhr.open("POST", "verify_password.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // 密碼正確，顯示實際密碼
                                fetch('password_manager_copy.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `id=${id}&action=show_password`
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        passwordField.type = 'text';
                                        passwordField.value = data.decrypted_password;  // 實際密碼來自伺服器回應
                                        toggleButton.textContent = '隱藏密碼';
                                    } else {
                                        alert("無法顯示密碼");
                                    }
                                });
                            } else {
                                alert("系統密碼不正確！");
                            }
                        }
                    };
                    xhr.send("password=" + encodeURIComponent(systemPassword));
                }
            } else {
                // 隱藏密碼
                passwordField.type = 'password';
                passwordField.value = '************';  // 恢復為固定長度的掩碼
                toggleButton.textContent = '顯示密碼';
            }
        }




    </script>




    <style>
    #logout-message {
        position: fixed;
        top: 10px;
        right: 100px;
        background-color: #4CAF50;
        color: white;
        padding: 10px;
        border-radius: 5px;
        font-family: Arial, sans-serif;
    }

    #logout {
        position: fixed;
        top: 10px;
        right: 10px;
        background-color: #f44336;
        color: white;
        padding: 10px;
        border-radius: 5px;
        font-family: Arial, sans-serif;
    }

    #logout a {
        color: white;
        text-decoration: none;
    }

    #logout:hover {
        background-color: #d32f2f;
    }

    #timer {
        font-weight: bold;
    }

    </style>

</head>
<body>
    <div id="logout-message">
        <span id="timer">300</span> 秒後自動登出
    </div>
    
    <div id="logout">
        <a href="?action=logout">Log Out</a>
    </div>


    <div class="container">
        <h2>Password Manager</h2>
        <form action="password_manager_copy.php" method="post">
            <input type="text" name="site" placeholder="Site" required>
            <input type="text" name="account" placeholder="Account" required>
            <input type="text" name="password" id="password" placeholder="Password" required>
            <button type="button" onclick="generatePassword()">自動產生</button>
            <input type="date" name="created_at" required>
            <input type="text" name="notes" placeholder="Notes (optional)">
            <input type="submit" name="add_password" value="Add Password">
        </form>

        <!-- Filter Form -->
        <form method="get" action="password_manager_copy.php">
            <h3>Filter Passwords</h3>
            <input type="text" name="site" placeholder="Site">
            <input type="text" name="account" placeholder="Account">
            <input type="text" name="notes" placeholder="Notes">
            <input type="date" name="created_at">
            <label>
                <input type="checkbox" name="alert" value="1"> 需要更換密碼
            </label>
            <input type="submit" value="Filter">
        </form>

        <h3>Stored Passwords</h3>
        <table>
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Account</th>
                    <th>Password</th>
                    <th>Notes</th>
                    <th>Created At</th>
                    <th>Alert</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($passwords as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['website_name']); ?></td>
                        <td><?php echo htmlspecialchars($entry['account_name']); ?></td>
                        <td>
                            <input type="password" id="password-<?php echo $entry['id']; ?>" value="<?php echo str_repeat('*', 12); ?>" readonly>
                            <button type="button" id="toggle-button-<?php echo $entry['id']; ?>" onclick="showPassword(<?php echo $entry['id']; ?>)">顯示密碼</button>
                        </td>


                        <td><?php echo htmlspecialchars($entry['notes']); ?></td>
                        <td><?php echo htmlspecialchars($entry['created_at']->format('Y-m-d')); ?></td>
                        <td class="alert">
                            <?php if ($entry['password_alert'] == "需要更換密碼"): ?>
                                <button type="button" onclick="location.href='change_password.php?id=<?php echo $entry['id']; ?>'">更換密碼</button>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form action="password_manager_copy.php" method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                <input type="submit" name="delete_password" value="Delete">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</body>
</html>
