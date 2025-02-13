<?php
// edit.php
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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['verified'])) {
        $_SESSION['verified'] = array();
    }
    if (isset($_POST['verify'])) {
        // 验证密码
        $input_password = trim($_POST['edit_password']);
        if ($input_password !== $message['edit_password']) {
            $error = "密码错误。";
        } else {
            $_SESSION['verified'][$id] = true;
        }
    } elseif (isset($_POST['update'])) {
        // 更新消息
        if (!isset($_SESSION['verified'][$id]) || $_SESSION['verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            $new_content = trim($_POST['content']);
            if ($new_content == '') {
                $error = "消息内容不能为空。";
            } else {
                $image_path = $message['image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['image']['type'], $allowed_types)) {
                        $error = "只支持 JPEG, PNG, GIF 格式的图片。";
                    } else {
                        // 若存在旧图片则删除
                        if ($image_path && file_exists($image_path)) {
                            unlink($image_path);
                        }
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
                            $error = "图片上传失败。";
                        } else {
                            $image_path = $new_filename;
                        }
                    }
                }
                if (!isset($error)) {
                    $stmt = $conn->prepare("UPDATE messages SET content = ?, image = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $new_content, $image_path, $id);
                    if ($stmt->execute()) {
                        unset($_SESSION['verified'][$id]);
                        header("Location: index.php");
                        exit;
                    } else {
                        $error = "更新失败: " . $conn->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>编辑消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h3>编辑消息 #<?php echo $id; ?></h3>
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>
  
  <?php if(!isset($_SESSION['verified'][$id]) || $_SESSION['verified'][$id] !== true): ?>
  <!-- 验证密码表单 -->
  <form action="edit.php?id=<?php echo $id; ?>" method="post">
    <div class="mb-3">
      <label for="edit_password" class="form-label">请输入编辑密码：</label>
      <input type="password" class="form-control" id="edit_password" name="edit_password" required>
    </div>
    <button type="submit" name="verify" class="btn btn-primary">验证密码</button>
    <a href="index.php" class="btn btn-secondary">返回</a>
  </form>
  <?php else: ?>
  <!-- 编辑表单 -->
  <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label for="content" class="form-label">消息内容 (支持 Markdown)</label>
      <textarea class="form-control" id="content" name="content" rows="4" required><?php echo htmlspecialchars($message['content']); ?></textarea>
    </div>
    <?php if($message['image']): ?>
    <div class="mb-3">
      <label class="form-label">当前图片：</label><br>
      <img src="<?php echo $message['image']; ?>" alt="当前图片" style="max-width:200px;">
    </div>
    <?php endif; ?>
    <div class="mb-3">
      <label for="image" class="form-label">更换图片 (可选)：</label>
      <input class="form-control" type="file" id="image" name="image" accept="image/*">
    </div>
    <button type="submit" name="update" class="btn btn-success">保存更新</button>
    <a href="index.php" class="btn btn-secondary">取消</a>
  </form>
  <?php endif; ?>
</div>
<!-- 引入 Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
