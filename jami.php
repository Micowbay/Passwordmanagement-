<?php
// Database configuration
$servername = "MSI"; // 修改为你的服务器名称
$database = "jami"; // 修改为你的数据库名称
$username = "sa"; // 修改为你的数据库用户名
$password = "jim93329"; // 修改为你的数据库密码

// Create connection
$connectionInfo = array("Database" => $database, "UID" => $username, "PWD" => $password);
$conn = sqlsrv_connect($servername, $connectionInfo);

// Check connection
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// 读取环境变量中的密钥
$encodedKey = getenv('SECRET_KEY');

if ($encodedKey === false) {
    die('Error: Secret key not found!');
}

$secretKey = base64_decode($encodedKey);

if ($secretKey === false) {
    die('Error: Invalid secret key format!');
}

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $user_password = $_POST['password'];

    // 检查密码是否为空
    if (empty($user_password)) {
        die('Error: Password cannot be empty!');
    }

    // 使用 AES 解密（确保实现了这个函数）
    $user_id = decryptAES($user_id, $secretKey);
    $user_password = decryptAES($user_password, $secretKey);

    // Hash the password
    $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);

    // Insert into database
    $sql = "INSERT INTO users (ID, password) VALUES (?, ?)";
    $params = array($user_id, $hashed_password);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        echo "ID and password saved successfully!";
    } else {
        echo "Error: " . print_r(sqlsrv_errors(), true);
    }
}

// Close connection
sqlsrv_close($conn);

// 伪代码：请根据实际情况实现解密函数
function decryptAES($data, $key) {
    // 实现 AES 解密逻辑，返回解密后的字符串
}
?>
