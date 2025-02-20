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
  <link rel="stylesheet" href="/bootstrap.min.css">
  <!-- 引入 html2canvas 库 -->
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
    
    /* 二维码样式（使用 fixed 定位，确保在视窗中显示） */
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

    /* 截图加载提示样式 */
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

    /* 优化功能按钮样式：调整大小、间距，使其适应手机和电脑屏幕 */
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
    /* 手机屏幕优化：若屏幕较窄允许换行 */
    @media (max-width: 576px) {
      .function-buttons {
        flex-wrap: wrap;
      }
    }

    /* 调整消息卡片的宽度，确保响应式显示 */
    .card {
      max-width: 100%;
      margin: 0 auto;
    }

    /* 优化消息中的图片显示，确保图片不会超出消息卡片边界 */
    .card img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: 0 auto;
    }
    
    /* 如内容区中有 Markdown 渲染的图片，则允许自动适应 */
    .content img {
      width: auto;
      max-width: 100%;
      height: auto;
      display: block;
      margin: 0 auto;
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
        
        <!-- 如果存在最后修改时间，则显示 -->
        <?php if (!empty($message['last_edit_time'])): ?>
          <span class="text-muted">(最后修改于 <?php echo date("Y-m-d H:i:s", strtotime($message['last_edit_time'])); ?>)</span>
        <?php endif; ?>
        
      </div>
      <div class="content">
        <?php echo $Parsedown->text($message['content']); ?>
      </div>
      <?php 
      // 检测消息内容中的 YouTube 视频链接，并以嵌入形式显示（支持多个视频）
      $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/';
      if (preg_match_all($pattern, $message['content'], $matches)) {
          foreach ($matches[1] as $videoId) {
              echo '<div class="ratio ratio-16x9 mb-3">';
              echo '<iframe src="https://www.youtube.com/embed/'.$videoId.'" title="YouTube video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
              echo '</div>';
          }
      }
      ?>
      
      <!-- 如果消息包含图片或视频 -->
      <?php if($message['image']): ?>
        <?php 
          $ext = strtolower(pathinfo($message['image'], PATHINFO_EXTENSION));
          if($ext === 'mp4'){
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
  <a href="index.php" class="btn btn-link mt-3">返回树洞</a>
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

    // 显示二维码：鼠标悬停时根据按钮位置及视窗边界调整二维码的位置
    $('#qrBtn').hover(function(){
      var buttonOffset = $(this).offset();
      var scrollTop = $(window).scrollTop();
      var scrollLeft = $(window).scrollLeft();
      var buttonTop = buttonOffset.top - scrollTop;
      var buttonLeft = buttonOffset.left - scrollLeft;
      var qrCode = $('#qrCode');
      // 临时显示以获取尺寸
      qrCode.css('display', 'block');
      var qrWidth = qrCode.outerWidth();
      var qrHeight = qrCode.outerHeight();
      qrCode.hide();
      var windowWidth = $(window).width();
      var windowHeight = $(window).height();
      var top = buttonTop + $(this).outerHeight() + 5;
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

    // 处理复制全文按钮点击事件
    $('#copyFullContentBtn').on('click', function() {
      const content = $(this).attr('data-content');
      navigator.clipboard.writeText(content)
        .then(() => {
          alert('内容已成功复制到剪贴板！');
        })
        .catch(err => {
          console.error('无法复制内容：', err);
          alert('复制失败，请检查权限或使用其他方式！');
        });
    });

    // 处理复制链接按钮点击事件（复制当前页面链接）
    $('#copyLinkBtn').on('click', function() {
      const currentUrl = window.location.href;
      navigator.clipboard.writeText(currentUrl)
        .then(() => {
          alert('链接已成功复制到剪贴板！');
        })
        .catch(err => {
          console.error('复制链接失败：', err);
          alert('复制链接失败，请检查权限或使用其他方式！');
        });
    });

    // 封装下载图片的函数，参数 scale 控制生成图片的分辨率
    function downloadImage(scale, label) {
      const originalCardElement = document.querySelector('.card');
      // 克隆卡片元素
      const clone = originalCardElement.cloneNode(true);
      
      // 修改克隆元素样式，限制宽度并确保文字自动换行
      clone.style.width = 'auto';
      clone.style.maxWidth = '500px'; // 限制截图宽度，可根据需求调整
      clone.style.boxSizing = 'border-box';
      clone.style.padding = '20px';
      
      // 确保内容区文字换行
      var contentElement = clone.querySelector('.content');
      if (contentElement) {
          contentElement.style.whiteSpace = 'normal';
          contentElement.style.wordWrap = 'break-word';
      }
      
      // 从克隆中移除包含功能按钮的区域（.function-buttons 部分）
      const btnDiv = clone.querySelector('.function-buttons');
      if (btnDiv) {
        btnDiv.remove();
      }
      
      // 将克隆元素放入一个临时容器中，并隐藏该容器
      const tempContainer = document.createElement('div');
      tempContainer.style.position = 'absolute';
      tempContainer.style.top = '-9999px';
      tempContainer.style.left = '-9999px';
      tempContainer.appendChild(clone);
      document.body.appendChild(tempContainer);

      const loadingElement = document.getElementById('screenshotLoading');
      // 显示加载提示
      loadingElement.style.display = 'block';

      // 使用 html2canvas 截图
      html2canvas(clone, {
        backgroundColor: '#ffffff', // 设置白色背景
        scale: scale, // 根据传入参数设置分辨率
        useCORS: true, // 启用跨域支持
        logging: true // 开启日志（调试用）
      }).then(canvas => {
        // 隐藏加载提示
        loadingElement.style.display = 'none';
        
        // 创建下载链接
        const link = document.createElement('a');
        link.download = '树洞消息-<?php echo $id; ?>-' + label + '.png';
        link.href = canvas.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        // 移除临时容器
        document.body.removeChild(tempContainer);
      }).catch(err => {
        console.error('截图生成失败:', err);
        loadingElement.style.display = 'none';
        alert('图片生成失败，请重试！');
        // 移除临时容器
        document.body.removeChild(tempContainer);
      });
    }

    // 下载小图（scale = 1）
    $('#downloadSmallImageBtn').click(function() {
      downloadImage(1, 'small');
    });

    // 下载大图（scale = 2）
    $('#downloadLargeImageBtn').click(function() {
      downloadImage(2, 'large');
    });
  });
</script>
</body>
</html>
