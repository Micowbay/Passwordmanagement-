<?php
// Database configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
$servername = "MSI";
$database = "account_verify";
$username = "sa"; // MS SQL username
$password = "jim93329"; // MS SQL password

// Create connection
$connectionInfo = array("Database" => $database, "UID" => $username, "PWD" => $password);
$conn = sqlsrv_connect($servername, $connectionInfo);

// Check connection
if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Initialize variables
$registration_successful = false;

// Start session to handle CAPTCHA
session_start();

function hash_data(...$args) {
    $data = implode('', $args);
    return hash('sha256', $data);
}

// XOR function
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

// Taiwan ID validation function
function validateTaiwanID($id) {
    if (!preg_match("/^[A-Z][12][0-9]{8}$/", $id)) {
        return false;
    }
    $alphabetMap = array(
        'A'=>10,'B'=>11,'C'=>12,'D'=>13,'E'=>14,'F'=>15,'G'=>16,'
        H'=>17,'I'=>34,'J'=>18,
        'K'=>19,'L'=>20,'M'=>21,'N'=>22,'O'=>35,'P'=>23,'Q'=>24,'R'=>25,'S'=>26,'T'=>27,
        'U'=>28,'V'=>29,'W'=>32,'X'=>30,'Y'=>31,'Z'=>33
    );

    $idArray = str_split($id);
    $firstLetterValue = $alphabetMap[$idArray[0]];
    $sum = intval($firstLetterValue / 10) + ($firstLetterValue % 10) * 9;

    for ($i = 1; $i <= 8; $i++) {
        $sum += intval($idArray[$i]) * (9 - $i);
    }
    
    $sum += intval($idArray[9]);

    return $sum % 10 === 0;
}

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    $ID_number = $_POST['ID_number'];
    $birthday = $_POST['birthday'];
    $captcha = $_POST['captcha'];

    if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($ID_number) || empty($birthday) || empty($captcha)) {
        header("Location: error.php?error=All+fields+are+required");
        exit();
    } elseif ($password !== $confirm_password) {
        header("Location: error.php?error=Confirm+password+not+correct");
        exit();
    } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d).+$/', $username)) {
        header("Location: error.php?error=Username+must+contain+at+least+one+letter+and+one+number.");
        exit();
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{7,}$/', $password)) {
        header("Location: error.php?error=Password+must+be+at+least+7+characters+long%2C+contain+both+upper+and+lower+case+letters%2C+and+at+least+one+number.");
        exit();
    } elseif (!validateTaiwanID($ID_number)) {
        header("Location: error.php?error=Invalid+Taiwan+ID+number.");
        exit();
    } elseif ($captcha !== $_SESSION['captcha_code']) {
        header("Location: error.php?error=Incorrect+CAPTCHA.");
        exit();
    } else {
        $check_sql = "SELECT * FROM users_test WHERE username = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($username));

        if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
            // 如果用户名已存在
            header("Location: error.php?error=Username+is+already+taken.");
            exit();
        }

        //加密方法
        $Ui = random_bytes(16);
        if (strlen($password) < strlen($Ui)) {
            // 用零填充到 Ui 的長度
            $password = str_pad($password, strlen($Ui), "\0"); // "\0" 是空字符
        } else if (strlen($password) > strlen($Ui)) {
            // 如果密碼比 Ui 長，則用零填充 Ui
            $Ui = str_pad($Ui, strlen($password), "\0"); // "\0" 是空字符
        }
        
        // 計算 A
        $A = hash('sha256', $password . $Ui, true); // true 參數返回二進位數據
        
        // 伺服器密鑰
        $r = base64_decode("ukBcYWAon+g//jF/Q2lbTvjEBtl0H1p0Xz2UrK+RpQQ="); // 伺服器密鑰
        
        // 計算 B
        $username_bin = $username; // 直接使用用戶名字符串
        $B = hash('sha256', $A . $username_bin . $r, true); // 返回二進位數據
        
        // 計算 C
        $C = xor_strings($password, $Ui); // 用二進位密碼進行 XOR

        //將所有變數轉為二進制

        $Ui_BIN = bin2hex($Ui);
        $A_BIN = bin2hex($A);
        $B_BIN = bin2hex($B);
        $C_BIN = bin2hex($C);

        // 存儲用戶資料到 session，等待驗證
        $_SESSION['registration_data'] = array(
            'username' => $username,
            'email' => $email,
            'ID_number' => $ID_number,
            'birthday' => $birthday,
            'C'=>$C_BIN,
            'B'=>$B_BIN,
        );

        // Generate verification code
        $verification_code = rand(100000, 999999);
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['username'] = $username; // 保存用戶名
        $_SESSION['email'] = $email; // 保存用戶電子郵件
        $_SESSION['registration_time'] = time(); // 記錄註冊時間
        
        // 發送郵件通知
        $user_email = $email; // 使用註冊時填寫的電子郵件
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'aligadou49@gmail.com'; // 替換為你的 Gmail 郵箱
            $mail->Password   = 'fwexbtvrfecsxrmh'; // 替換為你的應用程序密碼
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('aligadou49@gmail.com', 'Password Management System');
            $mail->addAddress($user_email);

            $mail->isHTML(true);
            $mail->Subject = 'Verification Code';
            $mail->Body    = "Your verification code is: $verification_code";

            $mail->send();
            header("Location: verify_register.php"); // 跳轉到驗證碼輸入頁面
            exit();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}

// Close connection
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 760px;
            margin: 50px auto;
            padding: 40px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .top_title h2 {
            text-align: center;
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="date"],
        input[type="submit"],
        button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="date"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.2);
        }

        input[type="submit"] {
            background-color: #007bff;
            color: #fff;
            font-weight: 500;
            border: none;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        button {
            background-color: #28a745;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background-color: #218838;
        }

        .return-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            text-align: center;
        }

        .return-link:hover {
            background-color: white;
            color: #007bff;
        }

        .hint-box {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #555;
        }

        .hints {
            font-size: 14px;
            margin-top: 5px;
        }

        .valid {
            color: #28a745;
        }

        .invalid {
            color: #dc3545;
        }

        #success-alert {
            display: none;
            color: #28a745;
            font-size: 18px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
    <script>
        function validateUsername() {
            const username = document.getElementById("username").value;
            const letterHint = document.getElementById("letter-hint");
            const numberHint = document.getElementById("number-hint");

            const hasLetter = /[a-zA-Z]/.test(username);
            const hasNumber = /\d/.test(username);

            if (hasLetter) {
                letterHint.classList.remove("invalid");
                letterHint.classList.add("valid");
            } else {
                letterHint.classList.remove("valid");
                letterHint.classList.add("invalid");
            }

            if (hasNumber) {
                numberHint.classList.remove("invalid");
                numberHint.classList.add("valid");
            } else {
                numberHint.classList.remove("valid");
                numberHint.classList.add("invalid");
            }
        }

        function validatePassword() {
            const password = document.getElementById("password").value;
            const lengthHint = document.getElementById("length-hint");
            const upperCaseHint = document.getElementById("uppercase-hint");
            const lowerCaseHint = document.getElementById("lowercase-hint");
            const numberHint = document.getElementById("password-number-hint");
            const specialCharHint = document.getElementById("special-char-hint");

            const hasLength = password.length >= 7;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            lengthHint.className = hasLength ? "valid" : "invalid";
            upperCaseHint.className = hasUpperCase ? "valid" : "invalid";
            lowerCaseHint.className = hasLowerCase ? "valid" : "invalid";
            numberHint.className = hasNumber ? "valid" : "invalid";
            specialCharHint.className = hasSpecialChar ? "valid" : "invalid";
        }

        function validateConfirmPassword() {
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirm_password").value;
            const passwordMatchHint = document.getElementById("password-match-hint");

            if (password !== confirmPassword) {
                passwordMatchHint.style.display = "inline";
                passwordMatchHint.classList.remove("valid");
                passwordMatchHint.classList.add("invalid");
            } else {
                passwordMatchHint.style.display = "none";
            }
        }

        function showSuccessAlert() {
            const alertBox = document.getElementById("success-alert");
            alertBox.style.display = "block";
        }

        window.onload = function() {
            <?php if ($registration_successful): ?>
            showSuccessAlert();
            <?php endif; ?>
        };

        function refreshCaptcha() {
            var captchaImage = document.getElementById('captcha-image');
            captchaImage.src = 'captcha.php?' + Date.now(); // 添加時間戳來避免快取
        }
        
        function checkUsernameAvailability() {
            const username = document.getElementById("username").value;
            const usernameHint = document.getElementById("username-hint");

            if (username.length > 0) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "check_username.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        if (xhr.responseText === "taken") {
                            usernameHint.textContent = "此名稱已被使用";
                            usernameHint.classList.add("invalid");
                            usernameHint.classList.remove("valid");
                        } else {
                            usernameHint.textContent = "此名稱可以使用";
                            usernameHint.classList.add("valid");
                            usernameHint.classList.remove("invalid");
                        }
                    }
                };
                xhr.send("username=" + encodeURIComponent(username));
            } else {
                usernameHint.textContent = "";
            }
        }
        document.getElementById("username").addEventListener("input", checkUsernameAvailability);
    </script>
</head>

<body>

    <div id="success-alert" class="success">
        <h2>Registration Successful!</h2>
        <p>Your account has been created successfully. <a href="index.html">Return to Login</a></p>
    </div>

    <div class="container">
        <div class="top_title">
            <h2>註冊系統</h2>
        </div>

        <div class="hint-box">
            <p>使用者名稱必須至少包含一個字母和一個數字。</p>
            <p>密碼長度必須至少為 7 個字符，同時包含大小寫字母和至少 1 個數字和 1 個特殊符號。</p>
        </div>

        <form method="post" action="#">
            <div class="form-group">
            <label for="username">使用者名稱:</label>
                <input type="text" id="username" name="username" oninput="validateUsername(); checkUsernameAvailability()" required>
                <div class="hints">
                    <span id="letter-hint" class="hint invalid">至少一個字元</span>
                    <span id="number-hint" class="hint invalid">至少一個數字</span>
                    <span id="username-hint" class="hint"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="password">密碼:</label>
                <input type="password" id="password" name="password" oninput="validatePassword()" required>
                <div class="hints">
                    <span id="length-hint" class="hint invalid">至少七個字</span>
                    <span id="uppercase-hint" class="hint invalid">沒有大寫</span>
                    <span id="lowercase-hint" class="hint invalid">沒有小寫</span>
                    <span id="password-number-hint" class="hint invalid">沒有數字</span>
                    <span id="special-char-hint" class="hint invalid">至少一個特殊符號</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">確認密碼:</label>
                <input type="password" id="confirm_password" name="confirm_password" oninput="validateConfirmPassword()" required>
                <span id="password-match-hint" class="hint invalid" style="display:none;">不同於第一次輸入</span>
            </div>

            <div class="form-group">
                <label for="email">電子郵件:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="ID_number">身分證字號:</label>
                <input type="text" id="ID_number" name="ID_number" required>
            </div>

            <div class="form-group">
                <label for="birthday">生日:</label>
                <input type="date" id="birthday" name="birthday" required>
            </div>

            <div class="form-group">
                <label for="captcha">驗證碼：</label>
                <img id="captcha-image" src="captcha.php" alt="CAPTCHA">
                <button id="reload-captcha" onclick="reloadCaptcha()">重置驗證碼</button>
                <input type="text" id="captcha" name="captcha" required>
            </div>

            <input type="submit" value="註冊">
        </form>

        <a href="index.html" class="return-link">返回首頁</a>
    </div>

    <script src="form-validation.js"></script>
</body>

</html>