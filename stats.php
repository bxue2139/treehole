<?php
// stats.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php'; // 引入数据库连接

// 1. 获取消息总数
$result = $conn->query("SELECT COUNT(*) AS total_messages FROM messages");
$row = $result->fetch_assoc();
$totalMessages = $row['total_messages'];

// 2. 获取评论总数
$result = $conn->query("SELECT COUNT(*) AS total_comments FROM comments");
$row = $result->fetch_assoc();
$totalComments = $row['total_comments'];

// 3. 查询评论数最多的前10条消息
$sql = "SELECT message_id, COUNT(*) AS comment_count
        FROM comments
        GROUP BY message_id
        ORDER BY comment_count DESC
        LIMIT 10";
$result = $conn->query($sql);
$topMessages = [];
while($row = $result->fetch_assoc()) {
    $topMessages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>数据统计</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h3>数据统计</h3>

  <!-- 显示消息总数、评论总数 -->
  <p>当前共有消息数：<?php echo $totalMessages; ?></p>
  <p>当前共有评论数：<?php echo $totalComments; ?></p>

  <hr>
  <h5>评论数最多的前10条消息：</h5>
  <?php if (count($topMessages) > 0): ?>
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th>消息ID</th>
          <th>评论数</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($topMessages as $msgInfo): ?>
        <tr>
          <!-- 点击链接跳转到 share.php?id=xxx -->
          <td>
            <a href="share.php?id=<?php echo $msgInfo['message_id']; ?>" target="_blank">
              #<?php echo $msgInfo['message_id']; ?>
            </a>
          </td>
          <td><?php echo $msgInfo['comment_count']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>暂无评论数据。</p>
  <?php endif; ?>

  <!-- 返回首页按钮 -->
  <a href="index.php" class="btn btn-secondary mt-3">返回首页</a>
</div>

<!-- 引入 jQuery 与 Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="/bootstrap.bundle.min.js"></script>
</body>
</html>
