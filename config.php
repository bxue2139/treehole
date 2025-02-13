<?php
// config.php
$servername = "localhost";
$username   = "tree3";       // 数据库用户名
$password   = "M3fmCwPYkX3eRjbF";           // 数据库密码
$dbname     = "tree3";   // 数据库名

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
