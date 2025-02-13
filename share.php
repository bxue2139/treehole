<?php
// share.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';
$Parsedown = new Parsedown();

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

// 构造分享链接（指向 share.php 并带上该消息ID）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$share_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/share.php?id=" . $id;
// 使用 QRServer API 生成二维码
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($share_url);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>分享消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="/bootstrap.min.css">
  <style>
    .qr-code {
      position: absolute;
      display: none;
      border: 1px solid #ddd;
      padding: 5px;
      background: #fff;
      z-index: 1000;
    }
    .share-btn {
      position: relative;
    }
  </style>
</head>
<body>
<div class="container mt-4">
  <h3>分享消息 #<?php echo $id; ?></h3>
  <div class="card">
    <div class="card-body">
      <div class="mb-2">
        <strong>#<?php echo $message['id']; ?></strong>
        <span class="text-muted"><?php echo date("Y-m-d H:i:s", strtotime($message['post_time'])); ?></span>
      </div>
      <div class="content">
        <?php echo $Parsedown->text($message['content']); ?>
      </div>
      <?php if($message['image']): ?>
      <div class="mt-2">
        <img src="<?php echo $message['image']; ?>" alt="上传图片" style="max-width: 100%; height: auto;">
      </div>
      <?php endif; ?>
      <div class="mt-3">
        <!-- 复制全文按钮 -->
        <button class="btn btn-outline-secondary" id="copyFullContentBtn" data-content="<?php echo htmlspecialchars($message['content']); ?>">复制</button>
        <!-- 编辑按钮 -->
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">编辑</a>
        <!-- 分享链接按钮 -->
        <a href="<?php echo $share_url; ?>" class="btn btn-outline-info">链接</a>
        <!-- 二维码分享按钮 -->
        <button class="btn btn-outline-info share-btn" id="qrBtn">二维码</button>
        <div class="qr-code" id="qrCode">
          <img src="<?php echo $qr_code_url; ?>" alt="二维码">
        </div>
      </div>
    </div>
  </div>
  <a href="index.php" class="btn btn-link mt-3">返回树洞</a>
</div>

<!-- 引入 jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
  // 复制全文功能
  $('#copyFullContentBtn').click(function(){
    var content = $(this).data('content');
    navigator.clipboard.writeText(content).then(function(){
      alert("已复制消息内容到剪贴板");
    }, function(err){
      alert("复制失败: " + err);
    });
  });

  // 显示二维码，当鼠标悬停在二维码分享按钮上时显示二维码
  $('#qrBtn').hover(function(){
    $('#qrCode').css({
      top: $(this).position().top + $(this).outerHeight() + 5,
      left: $(this).position().left
    }).fadeIn();
  }, function(){
    $('#qrCode').fadeOut();
  });
});
</script>
</body>
</html>
