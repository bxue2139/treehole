<?php
// search.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';
$Parsedown = new Parsedown();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q == '') {
    die("请输入搜索内容。");
}

// 搜索消息内容或标签（带 # 的标签）
$sql = "SELECT * FROM messages WHERE content LIKE ? OR content LIKE ?";
$stmt = $conn->prepare($sql);
$searchTerm = "%" . $q . "%";
$searchTag = "%" . '#' . $q . "%";
$stmt->bind_param("ss", $searchTerm, $searchTag);
$stmt->execute();
$messages = $stmt->get_result();

// 获取搜索到的消息 ID 列表
$search_ids = [];
while ($row = $messages->fetch_assoc()) {
    $search_ids[] = $row['id'];
}

// 获取消息总数
$total_results = count($search_ids);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>搜索结果</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
  <style>
    .search-ids { margin-bottom: 20px; }
    .message { border-bottom: 1px solid #ddd; padding: 10px 0; }
    .message img { 
        max-width: 100%; 
        height: auto;
        display: block;
        margin: 0 auto;
    }
    .timeline-time { color: #888; font-size: 0.9em; }
    .copy-btn { margin-left: 10px; }
  </style>
</head>
<body>
<div class="container mt-4">
  <h3>搜索结果：<?php echo htmlspecialchars($q); ?></h3>
  
  <!-- 显示搜索到的条数并列出ID -->
  <?php if ($total_results > 0): ?>
    <div class="search-ids">
      <p>搜索到 <?php echo $total_results; ?> 条消息，点击ID跳转到对应消息：</p>
      <div>
        <?php foreach ($search_ids as $id): ?>
          <a href="#msg-<?php echo $id; ?>" class="btn btn-outline-primary btn-sm"><?php echo $id; ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <p>没有找到相关的消息。</p>
  <?php endif; ?>

  <!-- 搜索结果展示 -->
  <div class="card mb-4">
    <div class="card-body">
      <?php 
      // 查询所有符合条件的消息，按时间排序
      $sql = "SELECT * FROM messages WHERE content LIKE ? OR content LIKE ? ORDER BY post_time DESC";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ss", $searchTerm, $searchTag);
      $stmt->execute();
      $messages = $stmt->get_result();

      while ($msg = $messages->fetch_assoc()): ?>
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
        <?php if ($msg['image']): ?>
        <div class="mt-2">
          <img src="<?php echo $msg['image']; ?>" alt="上传图片">
        </div>
        <?php endif; ?>
        <div class="mt-2">
          <button class="btn btn-sm btn-outline-secondary copy-btn" data-content="<?php echo htmlspecialchars($msg['content']); ?>">复制全文</button>
          <a href="share.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info">分享</a>
          <a href="edit.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-warning">编辑</a>
          <a href="delete.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger">删除</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>

  <!-- 返回首页按钮，放在页面底部 -->
  <div class="text-center mt-4">
    <a href="index.php" class="btn btn-outline-secondary">返回首页</a>
  </div>

  <!-- 引入 jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</div>
</body>
</html>
