<?php
// delete.php
session_start();
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("无效的消息ID。");
}

// 获取该消息数据
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("消息不存在。");
}
$message = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['del_verified'])) {
        $_SESSION['del_verified'] = array();
    }
    if (isset($_POST['verify'])) {
        // 验证密码：使用 password_verify() 验证密码是否正确
        $input_password = trim($_POST['edit_password']);
        if (!password_verify($input_password, $message['edit_password'])) {
            $error = "密码错误。";
        } else {
            $_SESSION['del_verified'][$id] = true;
        }
    } elseif (isset($_POST['delete'])) {
        if (!isset($_SESSION['del_verified'][$id]) || $_SESSION['del_verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            // 删除记录
            $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // 删除图片文件（如果存在）
                if ($message['image'] && file_exists($message['image'])) {
                    unlink($message['image']);
                }
                unset($_SESSION['del_verified'][$id]);
                header("Location: index.php");
                exit;
            } else {
                $error = "删除失败: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>删除消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h3>删除消息 #<?php echo $id; ?></h3>
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>
  
  <?php if(!isset($_SESSION['del_verified'][$id]) || $_SESSION['del_verified'][$id] !== true): ?>
  <!-- 验证密码表单 -->
  <form action="delete.php?id=<?php echo $id; ?>" method="post">
    <div class="mb-3">
      <label for="edit_password" class="form-label">请输入编辑密码确认删除：</label>
      <input type="password" class="form-control" id="edit_password" name="edit_password" required>
    </div>
    <button type="submit" name="verify" class="btn btn-danger">验证密码</button>
    <a href="index.php" class="btn btn-secondary">返回</a>
  </form>
  <?php else: ?>
  <div class="alert alert-warning">确认删除消息 #<?php echo $id; ?>？此操作不可恢复。</div>
  <form action="delete.php?id=<?php echo $id; ?>" method="post">
    <button type="submit" name="delete" class="btn btn-danger">确定删除</button>
    <a href="index.php" class="btn btn-secondary">取消</a>
  </form>
  <?php endif; ?>
</div>
<!-- 引入 Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
