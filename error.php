<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        .error-message {
            color: red;
            font-size: 18px;
            text-align: center;
            margin-top: 50px;
        }

        /* 綠色框框樣式 */
        .return-link {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: green;
            color: white;
            padding: 10px;
            text-decoration: none;
            border-radius: 5px;
        }

        .return-link:hover {
            background-color: darkgreen;
        }
    </style>
</head>
<body>

    <!-- 綠色框框，點選返回 index.html -->
    <a href="register.php" class="return-link">Register Again</a>

    <div class="error-message">
        <?php
        if (isset($_GET['error'])) {
            echo htmlspecialchars($_GET['error']);
        } else {
            echo "An unknown error occurred.";
        }
        ?>
    </div>
</body>
</html>
