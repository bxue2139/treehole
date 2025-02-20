<?php
// process.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content']);
    $edit_password = trim($_POST['edit_password']);
    
    if ($content == '' || $edit_password == '') {
        die("消息内容和编辑密码不能为空。");
    }
    
    // 使用 password_hash() 对编辑密码进行加密处理
    $encrypted_password = password_hash($edit_password, PASSWORD_DEFAULT);

    // 文件处理（支持图片和 MP4 视频）
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // 允许的 MIME 类型：JPEG, PNG, GIF 图片以及 MP4 视频
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            die("只支持 JPEG, PNG, GIF 格式的图片以及 MP4 格式的视频。");
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        // 根据文件后缀选择保存的目录
        if ($ext == 'mp4') {
            $new_filename = 'video/' . time() . '_' . rand(1000,9999) . '.' . $ext;
        } else {
            $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
        }
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
            die("文件上传失败。");
        }
        $image_path = $new_filename;
    }
    
    // 获取用户IP地址和设备信息
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $device_info = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '未知设备';
    
    // 插入数据库，保存加密后的编辑密码、IP和设备信息
    $stmt = $conn->prepare("INSERT INTO messages (content, image, edit_password, ip_address, device_info) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $content, $image_path, $encrypted_password, $user_ip, $device_info);
    if ($stmt->execute()) {
        header("Location: index.php");
    } else {
        echo "发布消息失败: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: index.php");
}
?>
