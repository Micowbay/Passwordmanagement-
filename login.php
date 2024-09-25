<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$servername = "MSI";
$dbname = "account_verify";
$username = "sa"; // MS SQL username
$password = "jim93329"; // MS SQL password

if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['captcha'])) {
    $user_username = $_POST['username'];
    $user_password = $_POST['password'];
    $user_captcha = $_POST['captcha'];

    // 檢查 CAPTCHA 是否正確
    if ($user_captcha !== $_SESSION['captcha_code']) {
        echo '<div style="background-color: black; color: white;font-size: 2em; text-align: center;">Invalid CAPTCHA.</div>';

        // 新增一個按鈕，按下會導回 index.html
        echo '<div style="text-align: center; margin-top: 20px;">';
        echo '<button onclick="window.location.href=\'index.html\'" style="font-size: 1.2em; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to login Page</button>';
        echo '</div>';
        exit();
    }

    $connectionInfo = array(
        "Database" => $dbname,
        "UID" => $username,
        "PWD" => $password
    );

    $conn = sqlsrv_connect($servername, $connectionInfo);

    if ($conn) {
        $query = "SELECT * FROM users WHERE username = ?";
        $params = array($user_username);
        $stmt = sqlsrv_query($conn, $query, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        if ($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (password_verify($user_password, $user['password'])) {
                echo '<div style="background-color: yellow; font-size: 2em; text-align: center;">Login successful!</div>';
                $verification_code = rand(100000, 999999);
                $_SESSION['verification_code'] = $verification_code;
                $_SESSION['username'] = $user_username;
                $_SESSION['code_expiry'] = time() + 90;

                echo '<div style="background-color: lightblue; font-size: 1.5em; text-align: center;">Your verification code is: ' . $verification_code . '</div>';

                $user_email = $user['email'];
                $_SESSION['user_email'] = $user['email'];
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'aligadou49@gmail.com';
                    $mail->Password   = 'fwexbtvrfecsxrmh';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('aligadou49@gmail.com', 'Password Management System');
                    $mail->addAddress($user_email, $user_username);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your Verification Code';
                    $mail->Body    = "Your verification code is <b>$verification_code</b>";
                    $mail->AltBody = "Your verification code is $verification_code";

                    $mail->send();
                    echo 'Verification code has been sent to your email.';
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }

                header("Location: verify.php");
                exit();
            } else {
                echo '<div style="background-color: black; color: white;font-size: 2em; text-align: center;">Invalid username or password.</div>';
                // 新增一個按鈕，按下會導回 index.html
                echo '<div style="text-align: center; margin-top: 20px;">';
                echo '<button onclick="window.location.href=\'index.html\'" style="font-size: 1.2em; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to login Page</button>';
                echo '</div>';
            }
        } else {
            echo '<div style="background-color: black; color: white;font-size: 2em; text-align: center;">Invalid username or password.</div>';
            // 新增一個按鈕，按下會導回 index.html
            echo '<div style="text-align: center; margin-top: 20px;">';
            echo '<button onclick="window.location.href=\'index.html\'" style="font-size: 1.2em; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to login Page</button>';
            echo '</div>';
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
    } else {
        echo "Connection could not be established.";
    }
} else {
    echo "All fields must be provided.";
}
?>
