<?php
// search.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';
$Parsedown = new Parsedown();

// 获取搜索关键词
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q == '') {
    die("请输入搜索内容。");
}

// 分页设置，每页显示10条记录
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

if ($q === "图片") {
    // 当搜索关键字为“图片”时，显示所有有图片的消息
    $sql_ids = "SELECT id FROM messages WHERE image IS NOT NULL AND image != ''";
    $stmt_ids = $conn->prepare($sql_ids);
    $stmt_ids->execute();
    $result_ids = $stmt_ids->get_result();
    $search_ids = [];
    while ($row = $result_ids->fetch_assoc()) {
        $search_ids[] = $row['id'];
    }
    $total_results = count($search_ids);

    // 查询分页的消息记录，按发布时间倒序排列
    $sql_display = "SELECT * FROM messages WHERE image IS NOT NULL AND image != '' ORDER BY post_time DESC LIMIT $limit OFFSET $offset";
    $stmt_display = $conn->prepare($sql_display);
    $stmt_display->execute();
    $messages = $stmt_display->get_result();
} elseif ($q === "视频") {
    // 当搜索关键字为“视频”时，显示所有有视频的消息
    $sql_ids = "SELECT id FROM messages WHERE image LIKE '%.mp4'";
    $stmt_ids = $conn->prepare($sql_ids);
    $stmt_ids->execute();
    $result_ids = $stmt_ids->get_result();
    $search_ids = [];
    while ($row = $result_ids->fetch_assoc()) {
        $search_ids[] = $row['id'];
    }
    $total_results = count($search_ids);

    // 查询分页的消息记录，按发布时间倒序排列
    $sql_display = "SELECT * FROM messages WHERE image LIKE '%.mp4' ORDER BY post_time DESC LIMIT $limit OFFSET $offset";
    $stmt_display = $conn->prepare($sql_display);
    $stmt_display->execute();
    $messages = $stmt_display->get_result();
} else {
    // 按消息内容或标签（带 # 的标签）进行搜索
    $sql_ids = "SELECT id FROM messages WHERE content LIKE ? OR content LIKE ?";
    $stmt_ids = $conn->prepare($sql_ids);
    $searchTerm = "%" . $q . "%";
    $searchTag = "%" . '#' . $q . "%";
    $stmt_ids->bind_param("ss", $searchTerm, $searchTag);
    $stmt_ids->execute();
    $result_ids = $stmt_ids->get_result();
    $search_ids = [];
    while ($row = $result_ids->fetch_assoc()) {
        $search_ids[] = $row['id'];
    }
    $total_results = count($search_ids);

    // 查询分页的消息记录，按发布时间倒序排列
    $sql_display = "SELECT * FROM messages WHERE content LIKE ? OR content LIKE ? ORDER BY post_time DESC LIMIT $limit OFFSET $offset";
    $stmt_display = $conn->prepare($sql_display);
    $stmt_display->bind_param("ss", $searchTerm, $searchTag);
    $stmt_display->execute();
    $messages = $stmt_display->get_result();
}

// 计算总页数
$total_pages = ceil($total_results / $limit);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>搜索结果</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/bootstrap.min.css">
  <style>
    .search-ids { margin-bottom: 20px; }
    .message { 
        border-bottom: 1px solid #ddd; 
        padding: 10px 0; 
        position: relative; /* 用于定位操作按钮 */
    }
    .message img, .message video, .message iframe { 
        max-width: 100%; 
        height: auto;
        display: block;
        margin: 0 auto;
    }
    .timeline-time { color: #888; font-size: 0.9em; }
    
    /* 默认隐藏消息操作按钮 */
    .actions {
      display: none;
      position: absolute;
      right: 10px;
      bottom: 10px;
    }

    /* 鼠标悬浮或聚焦时显示操作按钮 */
    .message:hover .actions,
    .message:focus-within .actions {
      display: block;
    }

    /* 悬浮搜索按钮样式 */
    .search-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: #007bff;
      color: white;
      border: none;
      border-radius: 50%;
      padding: 15px;
      font-size: 20px;
      cursor: pointer;
      z-index: 1000;
    }
    
    /* 搜索框的样式 */
    .search-container {
      position: fixed;
      bottom: 70px;
      right: 20px;
      background-color: white;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 10px;
      width: 300px;
      display: none; /* 默认隐藏 */
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
      z-index: 999;
    }
    
    .search-container input {
      width: 100%;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
<div class="container mt-4">
  <h3>搜索结果：<?php echo htmlspecialchars($q); ?></h3>
  
  <!-- 显示搜索到的条数并列出ID，链接指向分享页 -->
  <?php if ($total_results > 0): ?>
    <div class="search-ids">
      <p>搜索到 <?php echo $total_results; ?> 条消息，点击ID跳转到对应消息分享页：</p>
      <div>
        <?php foreach ($search_ids as $id): ?>
          <a href="share.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm"><?php echo $id; ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <p>没有找到相关的消息。</p>
  <?php endif; ?>

  <!-- 搜索结果展示 -->
  <div class="card mb-4">
    <div class="card-body">
      <?php while ($msg = $messages->fetch_assoc()): ?>
      <div class="message" id="msg-<?php echo $msg['id']; ?>" tabindex="0">
        <div>
          <strong>#<?php echo $msg['id']; ?></strong>
          <span class="timeline-time"><?php echo date("Y-m-d H:i:s", strtotime($msg['post_time'])); ?></span>
        </div>
        <div class="content">
          <?php 
            // 使用 Parsedown 渲染 Markdown 为 HTML
            $htmlContent = $Parsedown->text($msg['content']);
            echo $htmlContent;
            
            // 检测消息内容中的 YouTube 视频链接并显示视频
            $pattern = '/https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/';
            preg_match_all($pattern, $msg['content'], $matches);
            if (!empty($matches[1])) {
              foreach ($matches[1] as $videoId) {
                echo '<div class="mt-2"><iframe width="560" height="315" src="https://www.youtube.com/embed/'.$videoId.'" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="max-width:100%; height:auto;"></iframe></div>';
              }
            }
          ?>
        </div>
        
        <!-- 如果是视频文件 -->
        <?php if (isset($msg['image']) && strtolower(pathinfo($msg['image'], PATHINFO_EXTENSION)) === 'mp4'): ?>
          <div class="mt-2">
            <video controls>
              <source src="<?php echo $msg['image']; ?>" type="video/mp4">
              您的浏览器不支持播放视频。
            </video>
          </div>
        <?php elseif ($msg['image']): ?>
          <div class="mt-2">
            <img src="<?php echo $msg['image']; ?>" alt="上传图片">
          </div>
        <?php endif; ?>
        
        <!-- 操作按钮，仅在悬浮或聚焦时显示 -->
        <div class="actions">
          <button class="btn btn-sm btn-outline-secondary copy-btn" data-content="<?php echo htmlspecialchars($msg['content']); ?>">复制全文</button>
          <a href="share.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info">分享</a>
          <a href="edit.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-warning">编辑</a>
          <a href="delete.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger">删除</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
      <a href="index.php" class="btn btn-link mt-3">返回树洞</a>
  </div>

  <!-- 分页导航 -->
  <?php if ($total_pages > 1): ?>
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
      <!-- 上一页 -->
      <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $page - 1; ?>">上一页</a>
      </li>
      <!-- 页码列表 -->
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <li class="page-item <?php if($i == $page) echo 'active'; ?>">
        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
      </li>
      <?php endfor; ?>
      <!-- 下一页 -->
      <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $page + 1; ?>">下一页</a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>

  <!-- 悬浮搜索按钮 -->
  <button class="search-btn" id="searchBtn">🔍</button>

  <!-- 搜索框 -->
  <div class="search-container" id="searchContainer">
    <form action="search.php" method="get">
      <input type="text" class="form-control" name="q" placeholder="输入搜索内容或标签" required>
      <button class="btn btn-primary" type="submit">搜索</button>
      <button type="button" class="btn btn-outline-danger" id="closeSearch">关闭</button>
    </form>
  </div>

  <!-- 引入 jQuery 与 Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  $(document).ready(function() {
    // 点击搜索按钮，显示搜索框
    $('#searchBtn').click(function() {
      $('#searchContainer').fadeIn();
    });

    // 点击关闭按钮，隐藏搜索框
    $('#closeSearch').click(function() {
      $('#searchContainer').fadeOut();
    });
    
    // 处理复制全文按钮点击事件
    $('.copy-btn').click(function() {
      var content = $(this).data('content');
      // 优先使用 Clipboard API
      if (navigator.clipboard) {
          navigator.clipboard.writeText(content).then(function() {
              alert('复制成功！');
          }, function(err) {
              alert('复制失败，请手动复制。');
          });
      } else {
          // 兼容处理：创建临时文本框
          var $temp = $("<textarea>");
          $("body").append($temp);
          $temp.val(content).select();
          try {
              document.execCommand("copy");
              alert('复制成功！');
          } catch (err) {
              alert('复制失败，请手动复制。');
          }
          $temp.remove();
      }
    });
  });
  </script>
</div>
</body>
</html>
