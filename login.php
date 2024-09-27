<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$servername = "MSI";
$dbname = "account_verify";
$db_user = "sa"; // MS SQL username
$db_password = "jim93329"; // MS SQL password

if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['captcha'])) {
    $user_username = $_POST['username'];
    $user_password = $_POST['password'];
    $user_captcha = $_POST['captcha'];

    // 檢查 CAPTCHA 是否正確
    if ($user_captcha !== $_SESSION['captcha_code']) {
        echo '<div style="background-color: black; color: white;font-size: 2em; text-align: center;">Invalid CAPTCHA.</div>';
        echo '<div style="text-align: center; margin-top: 20px;">';
        echo '<button onclick="window.location.href=\'index.html\'" style="font-size: 1.2em; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to login Page</button>';
        echo '</div>';
        exit();
    }

    $connectionInfo = array(
        "Database" => $dbname,
        "UID" => $db_user,
        "PWD" => $db_password
    );

    $conn = sqlsrv_connect($servername, $connectionInfo);

    if ($conn) {
        // 獲取滿足 username 的所有資料
        $query = "SELECT * FROM users_test WHERE username = ?";
        $params = array($user_username);
        $stmt = sqlsrv_query($conn, $query, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        if ($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // 第一步：計算 Ui
            $r = base64_decode("ukBcYWAon+g//jF/Q2lbTvjEBtl0H1p0Xz2UrK+RpQQ=");
            $C = hex2bin($user['C']); // 假設資料庫中有 C 欄位

            if (strlen($user_password) < strlen($C)) {
                $user_password = str_pad($user_password, strlen($C), "\0", STR_PAD_RIGHT);
            } else if (strlen($user_password) > strlen($C)) {
                $C = str_pad($C, strlen($user_password), "\0", STR_PAD_RIGHT);
            }
            
            $Ui_da = xor_strings($C, $user_password); // Ui_da = C XOR password
            
            // 確保 Ui_da 的長度與 C 一致
            if (strlen($Ui_da) < strlen($C)) {
                $Ui_da = str_pad($Ui_da, strlen($C), "\0", STR_PAD_RIGHT);
            }
            
            $A_da = hash('sha256', $user_password . $Ui_da, true);
            $B_prime = hash('sha256', $A_da . $user_username . $r, true);
            $B_prime_BIN = bin2hex($B_prime);
            // 第四步：比較 B' 與資料庫中的 B
            if ($B_prime_BIN === $user['B']) { // 假設資料庫中有 B 欄位
                echo '<div style="background-color: yellow; font-size: 2em; text-align: center;">Login successful!</div>';
                $verification_code = rand(100000, 999999);
                $_SESSION['verification_code'] = $verification_code;
                $_SESSION['username'] = $user_username;
                $_SESSION['code_expiry'] = time() + 90;

                echo '<div style="background-color: lightblue; font-size: 1.5em; text-align: center;">Your verification code is: ' . $verification_code . '</div>';

                $user_email = $user['email'];
                $_SESSION['user_email'] = $user_email;
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
                echo '<div style="text-align: center; margin-top: 20px;">';
                echo '<button onclick="window.location.href=\'index.html\'" style="font-size: 1.2em; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to login Page</button>';
                echo '</div>';
            }
        } else {
            echo '<div style="background-color: black; color: white;font-size: 2em; text-align: center;">Invalid username or password.</div>';
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

// 輔助函數：進行 XOR 運算
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
?>
