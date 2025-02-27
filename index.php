<?php
// index.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';
require_once 'parsedown.php';  // 请确保 parsedown.php 存在
require_once 'parsedown-extra.php';  // 加载 ParsedownExtra
$Parsedown = new ParsedownExtra();

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

// 定义一组浅色背景（柔和、自然）
$colors = array('#FDEBD0', '#D6EAF8', '#D5F5E3', '#FADBD8', '#E8DAEF', '#FCF3CF', '#EBF5FB');
$colorCount = count($colors);
$counter = 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>树洞-留言板</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="/bootstrap.min.css">
  
  <!-- 引入 Editor.md CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/css/editormd.min.css" />
  
  <!-- Prism.js CSS（代码高亮） -->
  <link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css" rel="stylesheet"/>
  
  <!-- Prism.js（以及示例中的 php 语言包） -->
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js"></script>
  
  <style>
    body {
      padding-top: 20px;
      transition: background-color 0.3s ease;
    }
    .container { max-width: 800px; }
    .message {
      border-bottom: 1px solid #ddd;
      padding: 10px 0;
      position: relative;
      background-color: #fff;
      transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    .message img, .message video, .message iframe { 
      max-width: 100%; 
      height: auto; 
      filter: none;
    }
    /* 添加表格样式 */
    .message table {
      width: 100%;
      border-collapse: collapse;
      margin: 10px 0;
    }
    .message th, .message td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    .message th {
      background-color: #f2f2f2;
    }
    .timeline-time { color: #888; font-size: 0.9em; }
    .actions {
      display: none;
      position: absolute;
      right: 10px;
      bottom: 10px;
    }
    .message:hover .actions,
    .message:focus-within .actions {
      display: block;
    }
    .preview { border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none; }
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
    .invert-btn {
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: #007bff;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: 10px 15px;
      cursor: pointer;
      z-index: 1000;
    }
    .inverted {
      background-color: #121212 !important;
      color: #ccc !important;
    }
    .inverted .message {
      background-color: #333 !important;
      border-color: #555 !important;
      color: #ccc !important;
    }
    .inverted .timeline-time {
      color: #aaa !important;
    }
    .inverted .card {
      background-color: #222 !important;
      border-color: #444 !important;
    }
    .inverted .form-control {
      background-color: #333 !important;
      color: #ccc !important;
      border-color: #555 !important;
    }
    .inverted .btn {
      background-color: #444 !important;
      color: #ccc !important;
    }
    .inverted .btn-outline-secondary {
      border-color: #666 !important;
      color: #ccc !important;
    }
    .inverted .btn-outline-danger {
      border-color: #b73b3b !important;
    }
    /* 反色模式下的表格样式 */
    .inverted .message th {
      background-color: #444;
    }
    .inverted .message table {
      border-color: #555;
    }
    .inverted .message th, .inverted .message td {
      border-color: #555;
    }
    .pagination {
      flex-wrap: wrap;
    }
    /* Editor.md 自定义样式 */
    #editormd-container {
      margin-bottom: 15px;
    }
    .editormd-fullscreen {
      z-index: 2000 !important;
    }
    
    #infoFlowCard {
      position: relative;
      z-index: 1;
    }
    /* 自定义全屏按钮样式 */
    .editormd-toolbar .fa-fullscreen-custom:before {
      content: "\f065"; /* FontAwesome 全屏图标 */
    }
    .editormd-toolbar .fa-fullscreen-custom.active:before {
      content: "\f066"; /* FontAwesome 退出全屏图标 */
    }
    
    /* 新增：普通文本框容器（默认隐藏） */
    #plainTextareaContainer {
      display: none; 
      margin-bottom: 15px;
    }
    #plainTextarea {
      width: 100%;
      height: 300px;
      padding: 10px;
      box-sizing: border-box;
    }
  </style>
</head>
<body>
<div class="container">
  <h2 class="text-center">树洞-留言板</h2>

  <!-- 消息编辑部分（新增：编辑器模式切换） -->
  <div class="card mb-4">
    <div class="card-header">消息编辑</div>
    <div class="card-body">
      <!-- 模式选择：富文本 (Editor.md) / 普通文本框 -->
      <div class="mb-2">
        <label for="editorMode" class="form-label">选择编辑模式：</label>
        <select id="editorMode" class="form-select" style="max-width: 200px;">
          <option value="editor" selected>富文本 (Editor.md)</option>
          <option value="plain">普通文本框</option>
        </select>
      </div>

      <form action="process.php" method="post" enctype="multipart/form-data" id="messageForm">
        <!-- 隐藏字段，真正提交到后端 -->
        <input type="hidden" id="finalContent" name="content" value="" required>

        <!-- Editor.md 容器（默认显示） -->
        <div id="editormd-container">
          <textarea style="display:none;" id="editorMdContent"></textarea>
        </div>

        <!-- 普通文本框容器（默认隐藏） -->
        <div id="plainTextareaContainer">
          <textarea id="plainTextarea" placeholder="在此输入消息内容"></textarea>
        </div>

        <div class="mb-3">
          <label for="image" class="form-label">上传图片或视频 (可选)</label>
          <input class="form-control" type="file" id="image" name="image" accept="image/*,video/mp4">
        </div>
        <div class="mb-3">
          <label for="edit_password" class="form-label">编辑密码 (用于后续编辑或删除)</label>
          <input type="password" class="form-control" id="edit_password" name="edit_password" placeholder="设置编辑密码" required>
        </div>
        <button type="submit" class="btn btn-primary" id="sendBtn">发送</button>
      </form>
    </div>
  </div>
  
  <!-- 树洞信息流部分 -->
  <div class="card mb-4" id="infoFlowCard">
    <div class="card-header">树洞信息流</div>
    <div class="card-body">
      <?php while($msg = $messages->fetch_assoc()): ?>
      <div class="message" id="msg-<?php echo $msg['id']; ?>" tabindex="0" style="background-color: <?php echo $colors[$counter % $colorCount]; ?>;">
        <div>
          <strong>#<?php echo $msg['id']; ?></strong>
          <span class="timeline-time"><?php echo date("Y-m-d H:i:s", strtotime($msg['post_time'])); ?></span>
        </div>
        <div class="content">
          <?php 
            // 缓存生成的 HTML 内容：先判断缓存文件是否存在且有效（文件修改时间不早于消息最后编辑时间或发布时间）
            $cacheDir = 'cache';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $cacheFile = $cacheDir . '/parsed_' . $msg['id'] . '.html';
            $msgTime = strtotime(!empty($msg['last_edit_time']) ? $msg['last_edit_time'] : $msg['post_time']);
            $useCache = false;
            if (file_exists($cacheFile)) {
                if (filemtime($cacheFile) >= $msgTime) {
                    $useCache = true;
                }
            }
            if ($useCache) {
                $htmlContent = file_get_contents($cacheFile);
            } else {
                $htmlContent = $Parsedown->text($msg['content']);
                file_put_contents($cacheFile, $htmlContent);
            }
            echo $htmlContent;

            // 检测 YouTube 链接
            $pattern = '/https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/';
            preg_match_all($pattern, $msg['content'], $matches);
            if (!empty($matches[1])) {
              foreach ($matches[1] as $videoId) {
                echo '<div class="mt-2"><iframe width="560" height="315" src="https://www.youtube.com/embed/'.$videoId.'" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="max-width:100%; height:auto;"></iframe></div>';
              }
            }
          ?>
        </div>
        <?php if($msg['image']): ?>
        <div class="mt-2">
          <?php 
            $ext = strtolower(pathinfo($msg['image'], PATHINFO_EXTENSION));
            if($ext === 'mp4'){
              echo '<video controls style="max-width:100%; height:auto;">
                      <source src="'.$msg['image'].'" type="video/mp4">
                      您的浏览器不支持视频播放。
                    </video>';
            } else {
              echo '<img src="'.$msg['image'].'" alt="上传图片">';
            }
          ?>
        </div>
        <?php endif; ?>
        <div class="actions">
          <button class="btn btn-sm btn-outline-secondary copy-btn" data-content="<?php echo htmlspecialchars($msg['content']); ?>">复制</button>
          <a href="share.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-info">分享</a>
          <a href="edit.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-warning">编辑</a>
          <a href="delete.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-danger">删除</a>
        </div>
      </div>
      <?php $counter++; endwhile; ?>
      
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
  
  <!-- RSS 导出入口、统计入口 -->
  <div class="text-center mb-4">
    <a href="rss.php" target="_blank" class="btn btn-outline-primary">RSS导出</a>
    <a href="stats.php" class="btn btn-outline-success ms-2">数据统计</a>
  </div>
</div>

<!-- 悬浮搜索按钮 -->
<button class="search-btn" id="searchBtn">🔍</button>

<!-- 反色主题按钮 -->
<button id="invertBtn" class="invert-btn">反色</button>

<!-- 搜索框 -->
<div class="search-container" id="searchContainer">
  <form action="search.php" method="get">
    <input type="text" class="form-control" name="q" placeholder="输入搜索内容或标签" required>
    <div id="quickSearch" class="mt-2">
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="移民">移民</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="加拿大">加拿大</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="新西兰">新西兰</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="澳大利亚">澳大利亚</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="澳洲">澳洲</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="交易心得与规则总结">交易心得与规则总结</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="每日交易">每日交易</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="宝宝">宝宝</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="婴儿">婴儿</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="雅思">雅思</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="单词">单词</span>
      <span class="quick-search-item badge bg-secondary me-1" style="cursor:pointer;" data-keyword="IELTS">IELTS</span>
    </div>
    <div class="mt-2">
      <button class="btn btn-primary" type="submit">搜索</button>
      <button type="button" class="btn btn-outline-danger" id="closeSearch">关闭</button>
    </div>
  </form>
</div>

<!-- 引入 jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- 引入 Editor.md JS -->
<script src="https://cdn.jsdelivr.net/npm/editor.md@1.5.0/editormd.min.js"></script>
<script src="/bootstrap.bundle.min.js"></script>

<!-- 以下几段脚本是 Editor.md 可能需要的依赖（如果本地已有，可修改路径） -->
<script src="assets/editor.md/lib/raphael.min.js"></script>
<script src="assets/editor.md/lib/marked.min.js"></script>
<script src="assets/editor.md/lib/prettify.min.js"></script>
<script src="assets/editor.md/lib/sequence-diagram.min.js"></script>
<script src="assets/editor.md/lib/jquery.flowchart.min.js"></script>
<script src="assets/editor.md/editormd.min.js"></script>

<script>
$(document).ready(function(){
  // 如果有暗色模式Cookie，则启用
  if (getCookie("darkMode") === "enabled") {
    $('body').addClass('inverted');
  }
  
  // 代码高亮
  Prism.highlightAll();

  // 初始化 Editor.md（用于富文本编辑模式）
  var editor = editormd("editormd-container", {
    width: "100%",
    height: 300,
    path: "https://cdn.jsdelivr.net/npm/editor.md@1.5.0/lib/",
    markdown: "",
    placeholder: "输入消息内容 (支持 Markdown)",
    syncScrolling: "single",
    codeFold: true,
    saveHTMLToTextarea: true,
    previewCodeHighlight: true,
    toolbarIcons: function() {
      return [
        "undo", "redo", "|", 
        "bold", "italic", "quote", "|", 
        "h1", "h2", "h3", "|", 
        "list-ul", "list-ol", "hr", "|",
        "link", "image", "code","code-block","preformatted-text","table", "|",
        "preview", "watch", "|",
        "fullscreen-custom" // 自定义全屏按钮
      ];
    },
    toolbarIconsClass: {
      "fullscreen-custom": "fa-fullscreen-custom"
    },
    toolbarHandlers: {
      "fullscreen-custom": function(cm, icon, cursor, selection) {
        this.fullscreen(); // 切换全屏
        icon.toggleClass("active");
      }
    },
    onfullscreen: function() {
      $("#infoFlowCard").hide();
    },
    onfullscreenExit: function() {
      $("#infoFlowCard").show();
      $(".fa-fullscreen-custom").removeClass("active");
    }
  });

  // 模式切换：editor / plain
  $("#editorMode").change(function(){
    var mode = $(this).val();
    if(mode === "editor"){
      // 显示 Editor.md
      $("#editormd-container").show();
      $("#plainTextareaContainer").hide();
    } else {
      // 显示普通文本框
      $("#editormd-container").hide();
      $("#plainTextareaContainer").show();
    }
  });

  // 表单提交时，根据模式获取内容到隐藏字段
  $("#messageForm").submit(function(e){
    // 防止 HTML5 required 在没填时报错，这里统一处理
    var finalContent = "";
    if($("#editorMode").val() === "editor"){
      // 从Editor.md获取
      finalContent = editor.getMarkdown();
    } else {
      // 从普通文本框获取
      finalContent = $("#plainTextarea").val();
    }
    // 赋值到隐藏input
    $("#finalContent").val(finalContent.trim());
    
    // 如果为空，阻止提交并提示
    if(!finalContent.trim()){
      alert("消息内容不能为空！");
      e.preventDefault();
      return false;
    }
  });

  // 复制消息全文
  $('.copy-btn').click(function(){
    var content = $(this).data('content');
    navigator.clipboard.writeText(content).then(function(){
      alert("已复制消息内容到剪贴板");
    }, function(err){
      alert("复制失败: " + err);
    });
  });

  // 跳转到指定ID
  $('#jumpBtn').click(function(){
    var id = $('#jumpId').val();
    if(id){
      window.location.href = 'share.php?id=' + id;
    }
  });

  // 显示/关闭搜索框
  $('#searchBtn').click(function() {
    $('#searchContainer').fadeIn();
  });
  $('#closeSearch').click(function() {
    $('#searchContainer').fadeOut();
  });

  // 切换反色模式
  $('#invertBtn').click(function(){
    $('body').toggleClass('inverted');
    if ($('body').hasClass('inverted')) {
      setCookie("darkMode", "enabled", 30);
    } else {
      setCookie("darkMode", "disabled", 30);
    }
  });

  // 快速搜索标签
  $('.quick-search-item').click(function(){
    var keyword = $(this).data('keyword');
    window.location.href = 'search.php?q=' + encodeURIComponent(keyword);
  });

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + (days*24*60*60*1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
  }

  function getCookie(name) {
    var name = name + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for(var i = 0; i < ca.length; i++){
      var c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  }
});
</script>
</body>
</html>
