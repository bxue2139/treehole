<?php
// comment_delete.php
session_start();
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("无效的评论ID。");
}

// 获取评论
$stmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("评论不存在。");
}
$comment = $result->fetch_assoc();
$stmt->close();

$message_id = $comment['message_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['comment_del_verified'])) {
        $_SESSION['comment_del_verified'] = array();
    }

    // 第一步：验证密码
    if (isset($_POST['verify'])) {
        $input_password = trim($_POST['edit_password']);
        if (!password_verify($input_password, $comment['edit_password'])) {
            $error = "密码错误。";
        } else {
            $_SESSION['comment_del_verified'][$id] = true;
        }
    }
    // 第二步：执行删除
    elseif (isset($_POST['delete'])) {
        if (!isset($_SESSION['comment_del_verified'][$id]) || $_SESSION['comment_del_verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            // 删除记录
            $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // 若有文件需要删除
                if ($comment['image'] && file_exists($comment['image'])) {
                    unlink($comment['image']);
                }
                unset($_SESSION['comment_del_verified'][$id]);
                header("Location: share.php?id=" . $message_id);
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
  <title>删除评论 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h3>删除评论 #<?php echo $id; ?></h3>
  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php if(!isset($_SESSION['comment_del_verified'][$id]) || $_SESSION['comment_del_verified'][$id] !== true): ?>
    <!-- 先验证密码 -->
    <form action="comment_delete.php?id=<?php echo $id; ?>" method="post">
      <div class="mb-3">
        <label for="edit_password" class="form-label">请输入编辑密码以确认删除：</label>
        <input type="password" class="form-control" id="edit_password" name="edit_password" required>
      </div>
      <button type="submit" name="verify" class="btn btn-danger">验证密码</button>
      <a href="share.php?id=<?php echo $message_id; ?>" class="btn btn-secondary">返回</a>
    </form>
  <?php else: ?>
    <div class="alert alert-warning">
      确认删除评论 #<?php echo $id; ?>？此操作不可恢复。
    </div>
    <form action="comment_delete.php?id=<?php echo $id; ?>" method="post">
      <button type="submit" name="delete" class="btn btn-danger">确定删除</button>
      <a href="share.php?id=<?php echo $message_id; ?>" class="btn btn-secondary">取消</a>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
