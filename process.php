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
    
    // 图片处理
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            die("只支持 JPEG, PNG, GIF 格式的图片。");
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
            die("图片上传失败。");
        }
        $image_path = $new_filename;
    }
    
    // 插入数据库
    $stmt = $conn->prepare("INSERT INTO messages (content, image, edit_password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $content, $image_path, $edit_password);
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
