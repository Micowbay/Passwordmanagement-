<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$servername = "MSI";
$dbname = "account_verify";
$username = "sa"; // MS SQL username
$password = "jim93329"; // MS SQL password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && isset($_POST['id_number']) && isset($_POST['birthday']) && isset($_POST['reset_type'])) {
        $email = $_POST['email'];
        $id_number = $_POST['id_number'];
        $birthday = $_POST['birthday'];
        $reset_type = $_POST['reset_type'];

        // 連接到資料庫
        $connectionInfo = array(
            "Database" => $dbname,
            "UID" => $username,
            "PWD" => $password
        );
        $conn = sqlsrv_connect($servername, $connectionInfo);

        if ($conn === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        // 檢查用戶是否存在
        $query = "SELECT username, email FROM users_test WHERE email = ? AND ID_number = ? AND birthday = ?";
        $params = array($email, $id_number, $birthday);
        $stmt = sqlsrv_query($conn, $query, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $user_email = $row['email'];
            $username = $row['username'];

            // 檢查是否要重設密碼或找回用戶名
            if ($reset_type == 'password') {
                // 生成唯一的重置 token
                $reset_token = bin2hex(random_bytes(16));
                $created_at = date('Y-m-d H:i:s');

                // 將 token 和 email 儲存在 password_resets 表中
                $insert_query = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, ?)";
                $insert_params = array($user_email, $reset_token, $created_at);
                sqlsrv_query($conn, $insert_query, $insert_params);

                // 發送重置密碼的郵件
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'aligadou49@gmail.com';
                    $mail->Password   = 'fwexbtvrfecsxrmh';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('aligadou49@gmail.com', 'Reset Password');
                    $mail->addAddress($user_email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset';
                    $mail->Body = 'Click the link to reset your password: <a href="http://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $reset_token . '">Reset Password</a>';

                    $mail->send();
                    echo 'A password reset link has been sent to your email address.';
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } elseif ($reset_type == 'username') {
                // 發送找回用戶名的郵件
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'aligadou49@gmail.com';
                    $mail->Password   = 'fwexbtvrfecsxrmh';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('aligadou49@gmail.com', 'Username Recovery');
                    $mail->addAddress($user_email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Username Recovery';
                    $mail->Body    = 'Your username is: ' . $username;

                    $mail->send();
                    echo 'Your username has been sent to your email address.';
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            }
        } else {
            echo "No user found with the provided details.";
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    } else {
        echo "All fields (email, ID number, and birthday) must be provided.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Account Details</title>
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
        input[type="text"], input[type="date"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        select, input[type="submit"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        select {
            background-color: #fff;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: #fff;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .back-to-home {
            position: absolute;
            top: 10px;
            right: 10px; /* 根據需要調整距離 */
            padding: 10px 20px;
            background-color: #28a745; /* 綠色背景 */
            color: white;
            text-decoration: none; /* 移除連結下劃線 */
            border-radius: 3px; /* 圓角 */
            border: 2px solid #28a745; /* 綠色邊框 */
            font-size: 12px; /* 小框框字體 */
        }

        .back-to-home:hover {
            background-color: white;
            color: #28a745;
            border-color: #28a745;
        }

    </style>
</head>
<body>
    <a href="index.html" class="back-to-home">Back to Home Page</a>

    <div class="container">
        <h2>Forgot Account Details</h2>
        <form method="POST" action="">
            <label for="reset_type">I forgot my:</label>
            <select name="reset_type" id="reset_type" required>
                <option value="password">Password</option>
                <option value="username">Username</option>
            </select>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="id_number" placeholder="ID Number" required>
            <input type="date" name="birthday" placeholder="Birthday" required>
            <input type="submit" value="Submit">
        </form>
    </div>
</body>
</html>
