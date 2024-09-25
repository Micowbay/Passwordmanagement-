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
    $username = $_POST['username'];
    $check_sql = "SELECT * FROM users WHERE username = ?";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($username));

    if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
        echo "taken";
    } else {
        echo "available";
    }
}

sqlsrv_close($conn);
?>
