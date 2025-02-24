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
        $input_password = trim($_POST['edit_password']);
        if (!password_verify($input_password, $message['edit_password'])) {
            $error = "密码错误。";
        } else {
            $_SESSION['verified'][$id] = true;
        }
    } elseif (isset($_POST['update'])) {
        if (!isset($_SESSION['verified'][$id]) || $_SESSION['verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            $new_content = trim($_POST['content']);
            if ($new_content == '') {
                $error = "消息内容不能为空。";
            } else {
                $file_path = $message['image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $allowed_video_types = ['video/mp4'];

                    if (in_array($_FILES['image']['type'], $allowed_image_types)) {
                        if ($file_path && file_exists($file_path)) {
                            unlink($file_path);
                        }
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
                            $error = "图片上传失败。";
                        } else {
                            $file_path = $new_filename;
                        }
                    } elseif (in_array($_FILES['image']['type'], $allowed_video_types)) {
                        if ($file_path && file_exists($file_path)) {
                            unlink($file_path);
                        }
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'video/' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
                            $error = "视频上传失败。";
                        } else {
                            $file_path = $new_filename;
                        }
                    } else {
                        $error = "只支持 JPEG, PNG, GIF 格式的图片和 MP4 格式的视频。";
                    }
                }

                if (!isset($error)) {
                    $current_time = date("Y-m-d H:i:s");
                    $stmt = $conn->prepare("UPDATE messages SET content = ?, image = ?, last_edit_time = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $new_content, $file_path, $current_time, $id);
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
  <!-- 引入 Editor.md CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/css/editormd.min.css" />
  <style>
    #editormd-container {
      margin-bottom: 15px;
    }
    .editormd-fullscreen {
      z-index: 2000 !important;
    }
    .container {
      position: relative;
      z-index: 1;
    }
    .editormd-toolbar .fa-fullscreen-custom:before {
      content: "\f065";
    }
    .editormd-toolbar .fa-fullscreen-custom.active:before {
      content: "\f066";
    }
  </style>
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
  <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data" id="editForm">
    <div class="mb-3">
      <label for="content" class="form-label">消息内容 (支持 Markdown)</label>
      <div id="editormd-container">
        <textarea style="display:none;" id="content" name="content" required><?php echo htmlspecialchars($message['content']); ?></textarea>
      </div>
    </div>
    
    <?php if ($message['image'] && (strpos($message['image'], '.mp4') !== false)): ?>
      <div class="mb-3">
        <label class="form-label">当前视频：</label><br>
        <video controls style="max-width: 200px;">
          <source src="<?php echo $message['image']; ?>" type="video/mp4">
          您的浏览器不支持播放视频。
        </video>
      </div>
    <?php elseif ($message['image']): ?>
      <div class="mb-3">
        <label class="form-label">当前图片：</label><br>
        <img src="<?php echo $message['image']; ?>" alt="当前图片" style="max-width:200px;">
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="image" class="form-label">更换图片或视频 (可选)：</label>
      <input class="form-control" type="file" id="image" name="image" accept="image/*,video/mp4">
    </div>

    <button type="submit" name="update" class="btn btn-success">保存更新</button>
    <a href="index.php" class="btn btn-secondary">取消</a>
  </form>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/editormd.min.js"></script>
<script>
$(document).ready(function(){
  <?php if(isset($_SESSION['verified'][$id]) && $_SESSION['verified'][$id] === true): ?>
  var editor = editormd("editormd-container", {
    width: "100%",
    height: 300,
    path: "https://cdn.jsdelivr.net/npm/editor.md@1.5.0/lib/",
    markdown: $("#content").val(),
    syncScrolling: "single",
    toolbarIcons: function() {
      return [
        "undo", "redo", "|", 
        "bold", "italic", "quote", "|", 
        "h1", "h2", "h3", "|", 
        "list-ul", "list-ol", "hr", "|",
        "link", "image", "code", "table", "|",
        "preview", "watch", "|",
        "fullscreen-custom"
      ];
    },
    toolbarIconsClass: {
      "fullscreen-custom": "fa-fullscreen-custom"
    },
    toolbarHandlers: {
      "fullscreen-custom": function(cm, icon, cursor, selection) {
        this.fullscreen();
        icon.toggleClass("active");
      }
    },
    saveHTMLToTextarea: true,
    onfullscreen: function() {
      $(".container").hide();
    },
    onfullscreenExit: function() {
      $(".container").show();
      $(".fa-fullscreen-custom").removeClass("active");
    }
  });

  $('#editForm').on('keypress', function(e) {
    if (e.which == 13 && !e.shiftKey && !editor.isFullScreen()) {
      e.preventDefault();
      $('button[name="update"]').click();
    }
  });
  <?php endif; ?>
});
</script>
</body>
</html>
