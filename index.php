<?php
// index.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';  // 请确保 parsedown.php 存在
$Parsedown = new Parsedown();

// 分页设置
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$messages_per_page = 10;
$offset = ($page - 1) * $messages_per_page;

// 获取总记录数
$result = $conn->query("SELECT COUNT(*) as total FROM messages");
$row = $result->fetch_assoc();
$total_messages = $row['total'];
$total_pages = ceil($total_messages / $messages_per_page);

// 查询当前页消息（按发布时间倒序）
$sql = "SELECT * FROM messages ORDER BY post_time DESC LIMIT $offset, $messages_per_page";
$messages = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>留言板</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="/bootstrap.min.css">
  <style>
    body { padding-top: 20px; }
    .message { border-bottom: 1px solid #ddd; padding: 10px 0; }
    .message img { max-width: 100%; height: auto; }
    .timeline-time { color: #888; font-size: 0.9em; }
    .copy-btn { margin-left: 10px; }
    /* 分开显示消息编辑区和信息流 */
    .container { max-width: 800px; }
    .preview { border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none; }
  </style>
</head>
<body>
<div class="container">
  <h2 class="text-center">留言板</h2>
  <!-- 消息编辑部分 -->
  <div class="card mb-4">
    <div class="card-header">消息编辑</div>
    <div class="card-body">
      <form action="process.php" method="post" enctype="multipart/form-data" id="messageForm">
        <div class="mb-3">
          <label for="content" class="form-label">消息内容 (支持 Markdown)</label>
          <textarea class="form-control" id="content" name="content" rows="4" placeholder="输入消息内容" required></textarea>
        </div>
        <div class="mb-3">
          <label for="image" class="form-label">上传图片 (可选)</label>
          <input class="form-control" type="file" id="image" name="image" accept="image/*">
        </div>
        <div class="mb-3">
          <label for="edit_password" class="form-label">编辑密码 (用于后续编辑或删除)</label>
          <input type="password" class="form-control" id="edit_password" name="edit_password" placeholder="设置编辑密码" required>
        </div>
        <button type="submit" class="btn btn-primary" id="sendBtn">发送</button>
        <button type="button" class="btn btn-secondary" id="previewBtn">预览</button>
      </form>
      <div class="preview" id="previewArea"></div>
    </div>
  </div>
  
  <!-- 树洞信息流部分 -->
  <div class="card mb-4">
    <div class="card-header">树洞信息流</div>
    <div class="card-body">
      <?php while($msg = $messages->fetch_assoc()): ?>
      <div class="message" id="msg-<?php echo $msg['id']; ?>">
        <div>
          <strong>#<?php echo $msg['id']; ?></strong>
          <span class="timeline-time"><?php echo date("Y-m-d H:i:s", strtotime($msg['post_time'])); ?></span>
        </div>
        <div class="content">
          <?php 
            // 使用 Parsedown 渲染 Markdown 为 HTML
            $htmlContent = $Parsedown->text($msg['content']);
            echo $htmlContent;
          ?>
        </div>
        <?php if($msg['image']): ?>
        <div class="mt-2">
          <img src="<?php echo $msg['image']; ?>" alt="上传图片">
        </div>
        <?php endif; ?>
        <div class="mt-2">
          <!-- 复制按钮 -->
          <button class="btn btn-sm btn-outline-secondary copy-btn" data-content="<?php echo htmlspecialchars($msg['content']); ?>">复制</button>
          <!-- 分享按钮 -->
          <a href="share.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info">分享</a>
          <!-- 编辑和删除按钮 -->
          <a href="edit.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-warning">编辑</a>
          <a href="delete.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger">删除</a>
        </div>
      </div>
      <?php endwhile; ?>
      
      <!-- 分页导航 -->
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
          <?php for($i = 1; $i <= $total_pages; $i++): ?>
          <li class="page-item <?php if($i == $page) echo 'active'; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      
      <!-- 快速跳转至指定ID -->
      <div class="input-group mb-3">
        <input type="number" class="form-control" id="jumpId" placeholder="输入消息ID跳转">
        <button class="btn btn-outline-secondary" id="jumpBtn">跳转</button>
      </div>
    </div>
  </div>
</div>

<!-- 引入 jQuery 与 Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- 引入 Marked.js 用于 Markdown 预览 -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
$(document).ready(function(){
  // 表单支持 Enter 键提交（不换行）
  $('#messageForm').on('keypress', function(e) {
    if(e.which == 13 && !e.shiftKey) {
      e.preventDefault();
      $('#sendBtn').click();
    }
  });
  
  // 预览按钮功能：将 Markdown 转为 HTML 显示/隐藏预览区
  $('#previewBtn').click(function(){
    var content = $('#content').val();
    var html = marked.parse(content);
    $('#previewArea').html(html).toggle();
  });
  
  // 复制按钮功能
  $('.copy-btn').click(function(){
    var content = $(this).data('content');
    navigator.clipboard.writeText(content).then(function(){
      alert("已复制消息内容到剪贴板");
    }, function(err){
      alert("复制失败: " + err);
    });
  });
  
  // 快速跳转功能：跳转到指定消息 ID 的位置
  $('#jumpBtn').click(function(){
    var id = $('#jumpId').val();
    if(id){
      $('html, body').animate({
        scrollTop: $('#msg-' + id).offset().top
      }, 500);
    }
  });
});
</script>
</body>
</html>
