<?php
// ===============================
// share.php
// 增加文件缓存逻辑示例
// ===============================

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入数据库配置
require_once 'config.php';

// 引入支持 Markdown 的 ParsedownExtra
require_once 'parsedown.php';
require_once 'parsedown-extra.php';
$Parsedown = new ParsedownExtra();

// 读取 GET 参数中的主贴 ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("无效的消息ID。");
}

// --------------【1. 预先查询数据库，以获取必要的更新时间信息】--------------
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $stmt->close();
    die("消息不存在。");
}
$message = $result->fetch_assoc();
$stmt->close();

// 评论部分（主要是拿到所有评论的最后更新时间，以便判断是否需要刷新缓存）
$stmt_c = $conn->prepare("SELECT id, post_time, last_edit_time FROM comments WHERE message_id = ? ORDER BY post_time ASC");
$stmt_c->bind_param("i", $id);
$stmt_c->execute();
$comments_result_for_time = $stmt_c->get_result();
$stmt_c->close();

// 计算主贴和所有评论中最新的更新时间（没有 last_edit_time 则使用 post_time）
$lastUpdateTimestamp = strtotime($message['last_edit_time'] ?? $message['post_time']);
while ($cmtTimeCheck = $comments_result_for_time->fetch_assoc()) {
    $commentLatest = $cmtTimeCheck['last_edit_time'] ?? $cmtTimeCheck['post_time'];
    $commentTimeTs = strtotime($commentLatest);
    if ($commentTimeTs > $lastUpdateTimestamp) {
        $lastUpdateTimestamp = $commentTimeTs;
    }
}
// 将评论记录指针重置以便后续继续读取（也可以重新查询一次）
$comments_result_for_time->data_seek(0);

// --------------【2. 配置文件缓存路径及逻辑】--------------
$cacheEnabled = true;  // 是否启用缓存，可根据需求改为配置项
$cacheDir     = __DIR__ . '/cache';  // 缓存文件存放目录
$cacheFile    = $cacheDir . '/share_' . $id . '.html'; // 缓存文件名

// 如果启用了缓存，且缓存文件存在，我们还需要验证它是否过期
// 这里的策略是：若缓存文件的修改时间 >= 最新更新时间，则视为可用
if ($cacheEnabled && file_exists($cacheFile)) {
    $cacheMTime = filemtime($cacheFile);
    if ($cacheMTime !== false && $cacheMTime >= $lastUpdateTimestamp) {
        // 缓存文件是最新的，直接输出缓存并退出
        readfile($cacheFile);
        exit;
    }
}

// --------------【3. 如缓存无效或不存在，则继续生成 HTML】--------------
// 注意：因为之前的 $comments_result_for_time 已经被用来遍历一次
// 我们还需要查询完整评论信息(包含 content、image 等)，用于后面实际渲染
$stmt_c2 = $conn->prepare("SELECT * FROM comments WHERE message_id = ? ORDER BY post_time ASC");
$stmt_c2->bind_param("i", $id);
$stmt_c2->execute();
$comments_result = $stmt_c2->get_result();
$stmt_c2->close();

// 生成分享链接（用于二维码）
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
              || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$share_url  = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])
            . "/share.php?id=" . $id;
$qr_code_url= "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data="
            . urlencode($share_url);

// 使用输出缓冲，将页面 HTML 内容存储在变量里
ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>分享消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="/bootstrap.min.css">

  <!-- 引入 Editor.md 的 CSS -->
  <link rel="stylesheet" href="assets/editor.md/css/editormd.min.css" />

  <!-- 引入 Prism.js CSS（代码高亮） -->
  <link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css" rel="stylesheet"/>
  
  <!-- 也可按需引入 Prism.js 其他语言支持，如下演示 PHP -->
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js"></script>

  <!-- html2canvas：用于将消息内容保存为图片 -->
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
    /* 针对 Markdown 表格的样式 */
    .content table, .comment table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1rem;
    }
    .content table th, .content table td,
    .comment table th, .comment table td {
      border: 1px solid #dee2e6;
      padding: 0.75rem;
      vertical-align: top;
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
    /* 用于隐藏或显示普通文本框的容器 */
    #plainTextareaContainer {
      display: none; /* 默认隐藏，选择“普通文本框”时显示 */
      margin-bottom: 15px;
    }
    #plainTextarea {
      width: 100%;
      height: 200px;
      padding: 10px;
      box-sizing: border-box;
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

      <!-- 消息内容区，支持 Markdown + Prism 高亮 -->
      <div class="content">
        <?php 
          // 将消息内容转化为 HTML
          $htmlContent = $Parsedown->text($message['content']);
          echo $htmlContent;
        ?>
      </div>

      <?php
      // 检测消息内容中的 YouTube 视频链接并显示视频
      $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/';
      if (preg_match_all($pattern, $message['content'], $matches)) {
          foreach ($matches[1] as $videoId) {
              echo '<div class="ratio ratio-16x9 mb-3">';
              echo '<iframe src="https://www.youtube.com/embed/'.$videoId.'" 
                     allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                     allowfullscreen></iframe>';
              echo '</div>';
          }
      }
      ?>

      <!-- 如果有图片或视频文件 -->
      <?php if($message['image']): ?>
        <?php 
          $ext = strtolower(pathinfo($message['image'], PATHINFO_EXTENSION));
          if ($ext === 'mp4') {
            // 视频
            echo '<video controls style="max-width:100%; height:auto;">
                    <source src="'.$message['image'].'" type="video/mp4">
                    您的浏览器不支持视频播放。
                  </video>';
          } else {
            // 图片
            echo '<img src="'.$message['image'].'" alt="上传图片">';
          }
        ?>
      <?php endif; ?>

      <!-- 一些功能按钮：复制内容、编辑、复制链接、生成二维码、小图大图下载 -->
      <div class="function-buttons mt-3">
        <button class="btn btn-outline-secondary" id="copyFullContentBtn" 
                data-content="<?php echo htmlspecialchars($message['content']); ?>">复制</button>
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
          <!-- 采用 Parsedown 转换评论内容 -->
          <?php echo $Parsedown->text($cmt['content']); ?>
        </div>
        <?php if($cmt['image']): ?>
          <!-- 如果评论中有图片或视频 -->
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

  <!-- 选择编辑模式：Editor.md 或 普通文本框 -->
  <div class="mb-2">
    <label for="editorMode" class="form-label">选择编辑模式：</label>
    <select id="editorMode" class="form-select" style="max-width: 200px;">
      <option value="editor" selected>富文本 (Editor.md)</option>
      <option value="plain">普通文本框</option>
    </select>
  </div>

  <!-- 评论表单 -->
  <form action="comment_process.php" method="post" enctype="multipart/form-data" id="commentForm">
    <input type="hidden" name="message_id" value="<?php echo $id; ?>">

    <!-- 隐藏字段，真正提交给后端的内容 -->
    <input type="hidden" id="finalContent" name="content" value="">

    <!-- Editor.md 的容器（默认显示） -->
    <div id="comment-editormd-container">
      <textarea style="display:none;" id="commentEditorContent"></textarea>
    </div>

    <!-- 普通文本框的容器（默认隐藏） -->
    <div id="plainTextareaContainer">
      <textarea id="plainTextarea" placeholder="在此输入评论内容"></textarea>
    </div>

    <div class="mb-3">
      <label for="comment_image" class="form-label">上传图片或视频 (可选)：</label>
      <input class="form-control" type="file" id="comment_image" name="image" accept="image/*,video/mp4">
    </div>
    <div class="mb-3">
      <label for="comment_edit_password" class="form-label">编辑密码 (用于后续编辑或删除)：</label>
      <input type="password" class="form-control" id="comment_edit_password" name="edit_password" 
             placeholder="设置编辑密码" required>
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
<script src="assets/editor.md/lib/raphael.min.js"></script>
<script src="assets/editor.md/lib/marked.min.js"></script>
<script src="assets/editor.md/lib/prettify.min.js"></script>
<script src="assets/editor.md/lib/sequence-diagram.min.js"></script>
<script src="assets/editor.md/lib/jquery.flowchart.min.js"></script>
<script src="assets/editor.md/editormd.min.js"></script>

<script>
$(document).ready(function() {

  // ==========================
  // 1. 代码高亮初始化
  // ==========================
  Prism.highlightAll(); // 对页面中所有 <pre><code> 块进行高亮

  // ==========================
  // 2. 初始化评论的 Editor.md
  // ==========================
  var commentEditor = editormd("comment-editormd-container", {
    width: "100%",
    height: 200,
    path: "https://cdn.jsdelivr.net/npm/editor.md@1.5.0/lib/",
    markdown: "",
    placeholder: "输入评论内容 (支持 Markdown)",
    syncScrolling: "single",
    toolbarIcons: function() {
      return [
        "undo", "redo", "|", 
        "bold", "italic", "quote", "|", 
        "h1", "h2", "h3", "|", 
        "list-ul", "list-ol", "hr", "|",
        "link", "image", "code","table", "|",
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
      // 全屏时隐藏主贴
      $("#mainMessageCard").hide();
    },
    onfullscreenExit: function() {
      // 退出全屏时显示主贴
      $("#mainMessageCard").show();
      $(".fa-fullscreen-custom").removeClass("active");
    }
  });

  // ==========================
  // 3. 编辑模式切换
  // ==========================
  // 默认：富文本模式 (Editor.md) 显示，普通文本框隐藏
  $("#editorMode").change(function(){
    var mode = $(this).val();
    if(mode === "editor"){
      // 显示 Editor.md，隐藏普通文本框
      $("#comment-editormd-container").show();
      $("#plainTextareaContainer").hide();
    } else {
      // 显示普通文本框，隐藏 Editor.md
      $("#comment-editormd-container").hide();
      $("#plainTextareaContainer").show();
    }
  });

  // ==========================
  // 4. 提交评论时，根据所选编辑模式获取内容
  // ==========================
  $("#commentForm").submit(function(e) {
    e.preventDefault(); // 阻止默认提交，先处理内容再提交

    var mode = $("#editorMode").val();
    if(mode === "editor"){
      // 从 Editor.md 中获取内容
      var mdContent = commentEditor.getMarkdown();
      $("#finalContent").val(mdContent);
    } else {
      // 从普通文本框获取内容
      var plainContent = $("#plainTextarea").val();
      $("#finalContent").val(plainContent);
    }

    // 最后再提交表单
    this.submit();
  });

  // ==========================
  // 5. 悬浮搜索框逻辑
  // ==========================
  $('#searchBtn').click(function() {
    $('#searchContainer').fadeIn();
  });
  $('#closeSearch').click(function() {
    $('#searchContainer').fadeOut();
  });

  // ==========================
  // 6. 二维码按钮
  // ==========================
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

  // ==========================
  // 7. 复制消息全文
  // ==========================
  $('#copyFullContentBtn').on('click', function() {
    const content = $(this).attr('data-content');
    navigator.clipboard.writeText(content)
      .then(() => { alert('内容已成功复制到剪贴板！'); })
      .catch(err => { alert('复制失败，请检查权限或浏览器兼容性。'); });
  });

  // ==========================
  // 8. 复制链接
  // ==========================
  $('#copyLinkBtn').on('click', function() {
    const currentUrl = window.location.href;
    navigator.clipboard.writeText(currentUrl)
      .then(() => { alert('链接已成功复制到剪贴板！'); })
      .catch(err => { alert('复制链接失败，请检查权限或浏览器兼容性。'); });
  });

  // ==========================
  // 9. 下载截图（小图或大图）
  // ==========================
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

    // 移除卡片内的功能按钮区域
    const btnDiv = clone.querySelector('.function-buttons');
    if (btnDiv) {
      btnDiv.remove();
    }

    // 创建临时容器放置克隆元素
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
});
</script>
</body>
</html>
<?php
// --------------【4. 将输出缓冲内容写入缓存文件，然后输出】--------------

// 获取缓冲区内容
$pageContent = ob_get_contents();
ob_end_clean();

// 如果启用了缓存，则写入文件
if ($cacheEnabled) {
    // 确保缓存目录存在
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    // 写入缓存文件
    file_put_contents($cacheFile, $pageContent);
}

// 最后输出生成的页面内容
echo $pageContent;
