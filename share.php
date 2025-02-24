<?php
// share.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';
$Parsedown = new Parsedown();

// 获取主贴ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("无效的消息ID。");
}

// 获取该消息
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die("消息不存在。");
}
$message = $result->fetch_assoc();
$stmt->close();

// 生成分享链接
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$share_url  = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/share.php?id=" . $id;
$qr_code_url= "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($share_url);

// 读取该消息下的所有评论
$stmt_c = $conn->prepare("SELECT * FROM comments WHERE message_id = ? ORDER BY post_time ASC");
$stmt_c->bind_param("i", $id);
$stmt_c->execute();
$comments_result = $stmt_c->get_result();
$stmt_c->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>分享消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/bootstrap.min.css">
  <!-- 引入 Editor.md CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/css/editormd.min.css" />
  <!-- 引入 html2canvas 库（用于截图） -->
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
  <style>
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
    .search-container {
      position: fixed;
      bottom: 70px;
      right: 20px;
      background-color: white;
      border: 1px solid #ddd;
      border-radius: 5px;
      padding: 10px;
      width: 300px;
      display: none;
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
      z-index: 999;
    }
    .search-container input {
      width: 100%;
      margin-bottom: 10px;
    }
    .qr-code {
      position: fixed;
      display: none;
      border: 1px solid #ddd;
      padding: 5px;
      background: #fff;
      z-index: 1000;
    }
    .share-btn {
      position: relative;
    }
    .screenshot-loading {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 15px 25px;
      border-radius: 5px;
      z-index: 1001;
      display: none;
    }
    .function-buttons {
      display: flex;
      flex-wrap: nowrap;
      justify-content: space-between;
      align-items: center;
      gap: 5px;
      margin-top: 10px;
    }
    .function-buttons > button,
    .function-buttons > a {
      flex: 1;
      min-width: 60px;
      font-size: 12px;
      padding: 6px 8px;
      text-align: center;
    }
    @media (max-width: 576px) {
      .function-buttons {
        flex-wrap: wrap;
      }
    }
    .card {
      max-width: 100%;
      margin: 0 auto;
    }
    .card img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 0 auto;
    }
    .content img {
      width: auto;
      max-width: 100%;
      height: auto;
      display: block;
      margin: 0 auto;
    }
    .comment {
      border: 1px solid #eee;
      padding: 10px;
      margin-bottom: 10px;
      position: relative;
    }
    .comment .comment-actions {
      display: none;
      position: absolute;
      bottom: 5px;
      right: 10px;
    }
    .comment:hover .comment-actions,
    .comment:focus-within .comment-actions {
      display: block;
    }
    .comment-time {
      font-size: 0.9em;
      color: #666;
    }
    /* Editor.md 自定义样式 */
    #comment-editormd-container {
      margin-bottom: 15px;
    }
    .editormd-fullscreen {
      z-index: 2000 !important;
    }
    #mainMessageCard {
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

  <h3>分享消息 #<?php echo $id; ?></h3>

  <!-- 主贴卡片 -->
  <div class="card" id="mainMessageCard">
    <div class="card-body">
      <div class="mb-2">
        <strong>#<?php echo $message['id']; ?></strong>
        <span class="text-muted">
          <?php echo date("Y-m-d H:i:s", strtotime($message['post_time'])); ?>
        </span>
        <?php if (!empty($message['last_edit_time'])): ?>
          <span class="text-muted">
            (最后修改于 <?php echo date("Y-m-d H:i:s", strtotime($message['last_edit_time'])); ?>)
          </span>
        <?php endif; ?>
      </div>
      <div class="content">
        <?php echo $Parsedown->text($message['content']); ?>
      </div>
      <?php 
      $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/';
      if (preg_match_all($pattern, $message['content'], $matches)) {
          foreach ($matches[1] as $videoId) {
              echo '<div class="ratio ratio-16x9 mb-3">';
              echo '<iframe src="https://www.youtube.com/embed/'.$videoId.'" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
              echo '</div>';
          }
      }
      ?>
      <?php if($message['image']): ?>
        <?php 
          $ext = strtolower(pathinfo($message['image'], PATHINFO_EXTENSION));
          if ($ext === 'mp4') {
            echo '<video controls style="max-width:100%; height:auto;">
                    <source src="'.$message['image'].'" type="video/mp4">
                    您的浏览器不支持视频播放。
                  </video>';
          } else {
            echo '<img src="'.$message['image'].'" alt="上传图片">';
          }
        ?>
      <?php endif; ?>

      <div class="function-buttons mt-3">
        <button class="btn btn-outline-secondary" id="copyFullContentBtn" data-content="<?php echo htmlspecialchars($message['content']); ?>">复制</button>
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">编辑</a>
        <button class="btn btn-outline-info" id="copyLinkBtn">链接</button>
        <button class="btn btn-outline-info share-btn" id="qrBtn">二维码</button>
        <div class="qr-code" id="qrCode">
          <img src="<?php echo $qr_code_url; ?>" alt="二维码">
        </div>
        <button class="btn btn-outline-success" id="downloadSmallImageBtn">小图</button>
        <button class="btn btn-outline-success" id="downloadLargeImageBtn">大图</button>
      </div>
    </div>
  </div>

  <!-- 评论列表 -->
  <hr>
  <h5>评论区</h5>
  <?php if ($comments_result->num_rows > 0): ?>
    <?php while($cmt = $comments_result->fetch_assoc()): ?>
      <div class="comment">
        <div>
          <strong>评论 #<?php echo $cmt['id']; ?></strong>
          <span class="comment-time">
            发表于：<?php echo date("Y-m-d H:i:s", strtotime($cmt['post_time'])); ?>
            <?php if (!empty($cmt['last_edit_time'])): ?>
              （最后修改：<?php echo date("Y-m-d H:i:s", strtotime($cmt['last_edit_time'])); ?>）
            <?php endif; ?>
          </span>
        </div>
        <div class="mt-2">
          <?php echo $Parsedown->text($cmt['content']); ?>
        </div>
        <?php if($cmt['image']): ?>
          <?php 
            $c_ext = strtolower(pathinfo($cmt['image'], PATHINFO_EXTENSION));
            if ($c_ext === 'mp4') {
              echo '<video controls style="max-width:100%; height:auto;">
                      <source src="'.$cmt['image'].'" type="video/mp4">
                      您的浏览器不支持视频播放。
                    </video>';
            } else {
              echo '<img src="'.$cmt['image'].'" alt="评论图片" style="max-width:100%; height:auto;">';
            }
          ?>
        <?php endif; ?>
        <div class="comment-actions">
          <a href="comment_edit.php?id=<?php echo $cmt['id']; ?>" class="btn btn-sm btn-outline-warning">编辑</a>
          <a href="comment_delete.php?id=<?php echo $cmt['id']; ?>" class="btn btn-sm btn-outline-danger">删除</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>还没有任何评论，快来抢沙发~</p>
  <?php endif; ?>

  <!-- 添加新评论的表单 -->
  <hr>
  <h6>添加新评论</h6>
  <form action="comment_process.php" method="post" enctype="multipart/form-data" id="commentForm">
    <input type="hidden" name="message_id" value="<?php echo $id; ?>">
    <div class="mb-3">
      <label for="comment_content" class="form-label">评论内容 (支持 Markdown)：</label>
      <div id="comment-editormd-container">
        <textarea style="display:none;" id="comment_content" name="content" required></textarea>
      </div>
    </div>
    <div class="mb-3">
      <label for="comment_image" class="form-label">上传图片或视频 (可选)：</label>
      <input class="form-control" type="file" id="comment_image" name="image" accept="image/*,video/mp4">
    </div>
    <div class="mb-3">
      <label for="comment_edit_password" class="form-label">编辑密码 (用于后续编辑或删除)：</label>
      <input type="password" class="form-control" id="comment_edit_password" name="edit_password" placeholder="设置编辑密码" required>
    </div>
    <button type="submit" class="btn btn-primary" id="submitCommentBtn">提交评论</button>
  </form>

  <!-- 返回首页 -->
  <div class="mt-3">
    <a href="index.php" class="btn btn-link">返回树洞</a>
  </div>
</div>

<!-- 截图加载提示 -->
<div class="screenshot-loading" id="screenshotLoading">正在生成图片，请稍候...</div>

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
<script src="/bootstrap.bundle.min.js"></script>
<!-- 引入 Editor.md JS -->
<script src="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/editormd.min.js"></script>
<script>
$(document).ready(function() {
  // 初始化 Editor.md for comment form
  var commentEditor = editormd("comment-editormd-container", {
    width: "100%",
    height: 200,
    path: "https://cdn.jsdelivr.net/npm/editor.md@1.5.0/lib/",
    markdown: "",
    placeholder: "输入评论内容",
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
      $("#mainMessageCard").hide();
    },
    onfullscreenExit: function() {
      $("#mainMessageCard").show();
      $(".fa-fullscreen-custom").removeClass("active");
    }
  });

  // 搜索框
  $('#searchBtn').click(function() {
    $('#searchContainer').fadeIn();
  });
  $('#closeSearch').click(function() {
    $('#searchContainer').fadeOut();
  });

  // 二维码按钮
  $('#qrBtn').hover(function(){
    var buttonOffset = $(this).offset();
    var scrollTop    = $(window).scrollTop();
    var scrollLeft   = $(window).scrollLeft();
    var buttonTop    = buttonOffset.top - scrollTop;
    var buttonLeft   = buttonOffset.left - scrollLeft;
    var qrCode       = $('#qrCode');

    qrCode.css('display', 'block');
    var qrWidth  = qrCode.outerWidth();
    var qrHeight = qrCode.outerHeight();
    qrCode.hide();

    var windowWidth  = $(window).width();
    var windowHeight = $(window).height();
    var top  = buttonTop + $(this).outerHeight() + 5;
    var left = buttonLeft;
    if (left + qrWidth > windowWidth) {
      left = windowWidth - qrWidth - 10;
    }
    if (top + qrHeight > windowHeight) {
      top = buttonTop - qrHeight - 5;
    }
    qrCode.css({top: top, left: left}).fadeIn();
  }, function(){
    $('#qrCode').fadeOut();
  });

  // 复制消息全文
  $('#copyFullContentBtn').on('click', function() {
    const content = $(this).attr('data-content');
    navigator.clipboard.writeText(content)
      .then(() => { alert('内容已成功复制到剪贴板！'); })
      .catch(err => { alert('复制失败，请检查权限或浏览器兼容性。'); });
  });

  // 复制链接
  $('#copyLinkBtn').on('click', function() {
    const currentUrl = window.location.href;
    navigator.clipboard.writeText(currentUrl)
      .then(() => { alert('链接已成功复制到剪贴板！'); })
      .catch(err => { alert('复制链接失败，请检查权限或浏览器兼容性。'); });
  });

  // 下载截图
  function downloadImage(scale, label) {
    const originalCardElement = document.querySelector('.card');
    const clone = originalCardElement.cloneNode(true);

    clone.style.width      = 'auto';
    clone.style.maxWidth   = '500px'; 
    clone.style.boxSizing  = 'border-box';
    clone.style.padding    = '20px';

    var contentElement = clone.querySelector('.content');
    if (contentElement) {
        contentElement.style.whiteSpace = 'normal';
        contentElement.style.wordWrap   = 'break-word';
    }

    const btnDiv = clone.querySelector('.function-buttons');
    if (btnDiv) {
      btnDiv.remove();
    }

    const tempContainer = document.createElement('div');
    tempContainer.style.position = 'absolute';
    tempContainer.style.top = '-9999px';
    tempContainer.style.left = '-9999px';
    tempContainer.appendChild(clone);
    document.body.appendChild(tempContainer);

    const loadingElement = document.getElementById('screenshotLoading');
    loadingElement.style.display = 'block';

    html2canvas(clone, {
      backgroundColor: '#ffffff',
      scale: scale,
      useCORS: true,
      logging: false
    }).then(canvas => {
      loadingElement.style.display = 'none';
      const link = document.createElement('a');
      link.download = '树洞消息-<?php echo $id; ?>-' + label + '.png';
      link.href = canvas.toDataURL('image/png');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      document.body.removeChild(tempContainer);
    }).catch(err => {
      loadingElement.style.display = 'none';
      alert('图片生成失败，请重试');
      console.error('截图出错', err);
      document.body.removeChild(tempContainer);
    });
  }

  $('#downloadSmallImageBtn').click(function() {
    downloadImage(1, 'small');
  });
  $('#downloadLargeImageBtn').click(function() {
    downloadImage(2, 'large');
  });

  // 阻止 Enter 默认提交，但允许 Shift+Enter 换行
  $('#commentForm').on('keypress', function(e) {
    if (e.which == 13 && !e.shiftKey && !commentEditor.isFullScreen()) {
      e.preventDefault();
      $('#submitCommentBtn').click();
    }
  });
});
</script>
</body>
</html>
