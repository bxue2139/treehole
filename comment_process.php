<?php
// comment_process.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取主贴的 ID
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if ($message_id <= 0) {
        die("无效的主消息ID。");
    }

    $content       = trim($_POST['content']);
    $edit_password = trim($_POST['edit_password']);

    if ($content === '' || $edit_password === '') {
        die("评论内容和编辑密码不能为空。");
    }

    // 加密密码
    $encrypted_password = password_hash($edit_password, PASSWORD_DEFAULT);

    // 处理上传文件（图片或 MP4）
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            die("只支持 JPEG, PNG, GIF 格式的图片以及 MP4 格式的视频。");
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        // 按后缀决定存放目录
        if ($ext == 'mp4') {
            $new_filename = 'video/' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        } else {
            $new_filename = 'img/' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        }

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
            die("文件上传失败。");
        }
        $image_path = $new_filename;
    }

    // 获取 IP 及设备信息
    $user_ip    = $_SERVER['REMOTE_ADDR'];
    $device_info = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '未知设备';

    // 将评论写入数据库
    $stmt = $conn->prepare("INSERT INTO comments (message_id, content, image, edit_password, ip_address, device_info) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $message_id, $content, $image_path, $encrypted_password, $user_ip, $device_info);
    if ($stmt->execute()) {
        // 发布成功后跳回 share.php 对应的主贴
        header("Location: share.php?id=" . $message_id);
    } else {
        echo "发布评论失败: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: index.php");
}
?>
