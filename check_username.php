<?php
session_start();

$servername = "MSI";
$database = "account_verify";
$username = "sa"; // MS SQL username
$password = "jim93329"; // MS SQL password

$connectionInfo = array("Database" => $database, "UID" => $username, "PWD" => $password);
$conn = sqlsrv_connect($servername, $connectionInfo);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

if (isset($_POST['username'])) {
    $user_username = $_POST['username'];
    $check_sql = "SELECT * FROM users_test WHERE username = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($user_username));

    if ($check_stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    if (sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
        echo "taken";
    } else {
        echo "available";
    }
}

sqlsrv_close($conn);
?>
