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

// Taiwan ID validation function
function validateTaiwanID($id) {
    if (!preg_match("/^[A-Z][12][0-9]{8}$/", $id)) {
        return false;
    }
    $alphabetMap = array(
        'A'=>10,'B'=>11,'C'=>12,'D'=>13,'E'=>14,'F'=>15,'G'=>16,'H'=>17,'I'=>34,'J'=>18,
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
        $check_sql = "SELECT * FROM users WHERE username = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($username));

        if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
            // 如果用户名已存在
            header("Location: error.php?error=Username+is+already+taken.");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // 存儲用戶資料到 session，等待驗證
        $_SESSION['registration_data'] = array(
            'username' => $username,
            'password' => $hashed_password,
            'email' => $email,
            'ID_number' => $ID_number,
            'birthday' => $birthday
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
    <style>

        .container {
            display: flex;
            justify-content: space-between;
            width: 800px; /* 根據需要設置寬度 */
            margin: auto; /* 居中容器 */
            background-color: #f9f9f9
        }
        .top_title{
            text-align: center; /* 文字置中 */
            margin-bottom: 20px;
        }
        .left-column,
        .right-column {
            width: 70%; /* 根據需要調整寬度 */
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 50px; /* 在表單組之間添加一些空間 */
        }

        .hints {
            margin-left: 10px;
            display: inline-block;
        }

        .invalid {
            color: red;
        }

        .valid {
            color: green;
        }

        .hint {
            display: block;
            margin-top: 5px;
        }

        .success {
            display: none;
            color: green;
            font-size: 18px;
        }

        .hint-box {
            border: 1px solid #ccc;
            padding: 15px;
            background-color: #f9f9f9;
            width: 800px; /* 根據需要設置寬度 */
            margin: 20px auto; /* 垂直間距和水平置中 */
            text-align: center; /* 文字置中 */
        }
        .hints{
            display: block; /* 使每個提示在新的一行顯示 */
            margin-top: 5px
        }

        .hint-box p {
            margin: 0;
        }

        .return-link {
            position: absolute;
            top: 10px;
            right: 10px;
            display: inline-block;
            padding: 10px 20px;
            background-color: #28a745; /* 綠色背景 */
            color: white; /* 字體顏色 */
            text-decoration: none; /* 移除連結下劃線 */
            border-radius: 5px; /* 圓角 */
            border: 2px solid #28a745; /* 邊框為綠色 */
        }

        .return-link:hover {
            background-color: white; /* 滑鼠懸停時背景變為白色 */
            color: #28a745; /* 滑鼠懸停時字體變為綠色 */
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

<a href="index.html" class="return-link">Return to Login Page</a>

<?php if (!$registration_successful): ?>
    <div class="top_title">
        <h2>Register</h2>
    </div>
    <div class="hint-box">
        <p>Username must contain at least one letter and one number.</p>
        <p>Password must be at least 7 characters long, contain both upper and lower case letters, and at least one number.</p>
    </div>

    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <div class="container">
            <div class="left-column">
                <div class="form-group">
                    <label for="username">使用者名稱:</label>
                    <input type="text" id="username" name="username" oninput="validateUsername(); checkUsernameAvailability()" required>
                    <div class="hints">
                        <span id="letter-hint" class="hint invalid">至少一個字元</span>
                        <span id="number-hint" class="hint invalid">至少一個數字</span>
                        <span id="username-hint" class="hint"></span> <!-- 用于显示用户名的可用性 -->
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
                    <span id="password-match-hint" class="hint invalid" style="margin-left:10px; display:none;">不同於第一次輸入</span>
                </div>
                <input type="submit" value="註冊">
            </div>

            <div class="right-column">
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
                    <img id="captcha-image" src="captcha.php" alt="CAPTCHA Image"><br>
                    <input type="text" id="captcha" name="captcha" required style="margin-top: 10px; margin-bottom: 10px;"><br>
                    <button type="button" onclick="refreshCaptcha()" style="display: block; margin-top: 5px;">刷新驗證碼</button>
                </div>
            </div>
        </div>
        
    </form>
<?php endif; ?>

</body>
</html>
