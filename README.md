chatgpt made this treehole

编写一个实现树洞功能的php程序，所有人可以发言和分享消息链接，并实现所有数据的mysql服务器存放，对每条消息发布时输入设置一个编辑密码，用于对该条消息的再编辑和删除功能，已发布的消息可以通过这个密码来管理重新编辑、保存、删除 ，这个密码只管理该条消息。
还包括以下功能， 
1、消息编辑和树洞信息流在网页中分开展示
2、消息编辑框可以实现文本编辑和图片上传，类似twitter，
3、使消息支持Markdown代码，且网页的显示支持Markdown预览
4、时间轴的方式网页消息显示，时间为北京时间
5、每条消息后添加复制该条消息全文的按钮
6、支持链接的方式分享单项消息的功能
7、可以通过键盘的Enter键或者发送按钮即提交消息
8、上传的图片存在代码文件目录下的img文件夹里
9、尽量美观大气  
10、网页样式，显示的图片为适应浏览器窗口宽带大小来展示，注意手机端访问和电脑端访问的体验。
11、在每条消息前的显示其消息的数据库ID编号；同时支持快速跳转至指定ID编号以查看消息；增加网页显示消息条数为10，可以翻页，显示所有页的页码，点击可以调整该页。
12、在消息分享页面增加显示当前消息链接二维码的功能，鼠标放在这个按钮上时，显示当前页面的二维码

下面给出一个简单的示例，实现一个“树洞”匿名留言板，使用 PHP 连接 MySQL 存储数据，支持每条消息设置编辑密码、编辑删除、Markdown 渲染、图片上传、时间轴显示、分页、消息复制、分享二维码等功能。  
  
> **注意：**  
> 1. 此示例仅为基本示例，生产环境下请注意防止 SQL 注入、XSS、文件上传安全等问题，并增加错误处理、用户输入过滤等安全措施。  
> 2. 示例中使用了 [Parsedown](https://github.com/erusev/parsedown) 进行 Markdown 渲染，需将 parsedown.php 文件放在代码目录下；同时请提前在 MySQL 中创建好数据库和数据表。  
> 3. 上传图片将保存在项目目录下的 `img/` 文件夹中，请保证该目录存在且具有写权限。  

下面给出各文件的示例代码：  

---

### 1. 数据库表结构

在 MySQL 中创建一个数据库（如 `treehole`），然后创建数据表 `messages`（示例 SQL 如下）：

```sql
CREATE TABLE `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `content` TEXT NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `post_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edit_password` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 2. config.php  
用于数据库连接配置。

```php
<?php
// config.php
$servername = "localhost";
$username   = "root";       // 数据库用户名
$password   = "";           // 数据库密码
$dbname     = "treehole";   // 数据库名

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
```

---

### 3. index.php  
主页面，包含“消息编辑”区和“树洞信息流”区，支持 Markdown 预览、图片上传、分页、快速跳转等功能。

```php
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
  <title>树洞 - 匿名留言板</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
  <style>
    body { padding-top: 20px; }
    .message { border-bottom: 1px solid #ddd; padding: 10px 0; }
    .message img { 
        max-width: 100%;     /* 保证图片宽度不超过父容器宽度 */
        height: auto;       /* 保持图片的纵横比 */
        display: block;     /* 图片显示为块级元素，避免有空白 */
        margin: 0 auto;     /* 可选：使图片居中显示 */
    }
    .timeline-time { color: #888; font-size: 0.9em; }
    .copy-btn { margin-left: 10px; }
    /* 分开显示消息编辑区和信息流 */
    .container { max-width: 800px; }
    .preview { border: 1px solid #ccc; padding: 10px; margin-top: 10px; display: none; }
    
    /* 手机端样式 */
    @media (max-width: 768px) {
        .message img {
            max-width: 100%;
        }
    }
  </style>
</head>
<body>
<div class="container">
  <h2 class="text-center">树洞匿名留言板</h2>
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
          <button class="btn btn-sm btn-outline-secondary copy-btn" data-content="<?php echo htmlspecialchars($msg['content']); ?>">复制全文</button>
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

```

---

### 4. process.php  
处理新消息提交，包含图片上传和将消息写入数据库。

```php
<?php
// process.php
date_default_timezone_set('Asia/Shanghai');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content']);
    $edit_password = trim($_POST['edit_password']);
    
    if ($content == '' || $edit_password == '') {
        die("消息内容和编辑密码不能为空。");
    }
    
    // 图片处理
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            die("只支持 JPEG, PNG, GIF 格式的图片。");
        }
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
            die("图片上传失败。");
        }
        $image_path = $new_filename;
    }
    
    // 插入数据库
    $stmt = $conn->prepare("INSERT INTO messages (content, image, edit_password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $content, $image_path, $edit_password);
    if ($stmt->execute()) {
        header("Location: index.php");
    } else {
        echo "发布消息失败: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: index.php");
}
?>
```

---

### 5. edit.php  
实现消息编辑功能：先验证编辑密码（此处使用 session 保存验证状态），验证通过后显示编辑表单，支持更换图片。

```php
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
        // 验证密码
        $input_password = trim($_POST['edit_password']);
        if ($input_password !== $message['edit_password']) {
            $error = "密码错误。";
        } else {
            $_SESSION['verified'][$id] = true;
        }
    } elseif (isset($_POST['update'])) {
        // 更新消息
        if (!isset($_SESSION['verified'][$id]) || $_SESSION['verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            $new_content = trim($_POST['content']);
            if ($new_content == '') {
                $error = "消息内容不能为空。";
            } else {
                $image_path = $message['image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['image']['type'], $allowed_types)) {
                        $error = "只支持 JPEG, PNG, GIF 格式的图片。";
                    } else {
                        // 若存在旧图片则删除
                        if ($image_path && file_exists($image_path)) {
                            unlink($image_path);
                        }
                        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'img/' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $new_filename)) {
                            $error = "图片上传失败。";
                        } else {
                            $image_path = $new_filename;
                        }
                    }
                }
                if (!isset($error)) {
                    $stmt = $conn->prepare("UPDATE messages SET content = ?, image = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $new_content, $image_path, $id);
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
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
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
  <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label for="content" class="form-label">消息内容 (支持 Markdown)</label>
      <textarea class="form-control" id="content" name="content" rows="4" required><?php echo htmlspecialchars($message['content']); ?></textarea>
    </div>
    <?php if($message['image']): ?>
    <div class="mb-3">
      <label class="form-label">当前图片：</label><br>
      <img src="<?php echo $message['image']; ?>" alt="当前图片" style="max-width:200px;">
    </div>
    <?php endif; ?>
    <div class="mb-3">
      <label for="image" class="form-label">更换图片 (可选)：</label>
      <input class="form-control" type="file" id="image" name="image" accept="image/*">
    </div>
    <button type="submit" name="update" class="btn btn-success">保存更新</button>
    <a href="index.php" class="btn btn-secondary">取消</a>
  </form>
  <?php endif; ?>
</div>
<!-- 引入 Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

---

### 6. delete.php  
实现消息删除功能，流程与编辑类似，先验证密码，再进行删除操作（同时删除图片文件）。

```php
<?php
// delete.php
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['del_verified'])) {
        $_SESSION['del_verified'] = array();
    }
    if (isset($_POST['verify'])) {
        $input_password = trim($_POST['edit_password']);
        if ($input_password !== $message['edit_password']) {
            $error = "密码错误。";
        } else {
            $_SESSION['del_verified'][$id] = true;
        }
    } elseif (isset($_POST['delete'])) {
        if (!isset($_SESSION['del_verified'][$id]) || $_SESSION['del_verified'][$id] !== true) {
            $error = "请先验证密码。";
        } else {
            // 删除记录
            $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                // 删除图片文件（如果存在）
                if ($message['image'] && file_exists($message['image'])) {
                    unlink($message['image']);
                }
                unset($_SESSION['del_verified'][$id]);
                header("Location: index.php");
                exit;
            } else {
                $error = "删除失败: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>删除消息 #<?php echo $id; ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 引入 Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
  <h3>删除消息 #<?php echo $id; ?></h3>
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
  <?php endif; ?>
  
  <?php if(!isset($_SESSION['del_verified'][$id]) || $_SESSION['del_verified'][$id] !== true): ?>
  <!-- 验证密码表单 -->
  <form action="delete.php?id=<?php echo $id; ?>" method="post">
    <div class="mb-3">
      <label for="edit_password" class="form-label">请输入编辑密码确认删除：</label>
      <input type="password" class="form-control" id="edit_password" name="edit_password" required>
    </div>
    <button type="submit" name="verify" class="btn btn-danger">验证密码</button>
    <a href="index.php" class="btn btn-secondary">返回</a>
  </form>
  <?php else: ?>
  <div class="alert alert-warning">确认删除消息 #<?php echo $id; ?>？此操作不可恢复。</div>
  <form action="delete.php?id=<?php echo $id; ?>" method="post">
    <button type="submit" name="delete" class="btn btn-danger">确定删除</button>
    <a href="index.php" class="btn btn-secondary">取消</a>
  </form>
  <?php endif; ?>
</div>
<!-- 引入 Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

---

### 7. share.php  
实现单条消息分享页面，展示消息详情以及一个分享按钮，当鼠标悬停时显示当前页面链接生成的二维码（调用 Google Chart API 生成二维码）。

```php
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
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
        <button class="btn btn-outline-secondary" id="copyFullContentBtn" data-content="<?php echo htmlspecialchars($message['content']); ?>">复制全文</button>
        <!-- 编辑按钮 -->
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">编辑消息</a>
        <!-- 分享链接按钮 -->
        <a href="<?php echo $share_url; ?>" class="btn btn-outline-info">复制分享链接</a>
        <!-- 二维码分享按钮 -->
        <button class="btn btn-outline-info share-btn" id="qrBtn">二维码分享</button>
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


```

---

## 小结

以上代码示例实现了以下功能：  

1. **消息编辑和树洞信息流分开展示**：在 index.php 中将“消息编辑”区与消息信息流分别显示。  
2. **支持文本编辑与图片上传**：编辑区内包含 textarea 和文件上传控件，类似 twitter。  
3. **支持 Markdown 代码与预览**：使用 Parsedown 渲染 Markdown，同时借助 Marked.js 实现预览。  
4. **时间轴显示（北京时间）**：通过设置时区为 Asia/Shanghai，消息按照发布时间显示。  
5. **消息复制按钮**：每条消息后都有复制按钮，调用 Clipboard API 实现复制功能。  
6. **单条消息分享**：点击“分享”进入 share.php 页面，提供分享链接及二维码（鼠标悬停显示）。  
7. **支持键盘 Enter 键提交**：在编辑区按 Enter 键（非换行）即可提交消息。  
8. **图片存放在 img 文件夹**：上传的图片存放到项目目录下的 img 文件夹中。  
9. **美观大气的页面样式**：使用 Bootstrap 实现响应式布局，适应手机和电脑端访问。  
10. **显示数据库 ID、快速跳转及分页**：每条消息前显示 ID，并提供输入框实现跳转，同时支持每页 10 条消息的翻页。  

根据实际需求可进一步扩展和完善，比如增加 CSRF 防护、密码加密存储、输入过滤等安全措施。希望这个示例能给你提供一个参考实现！
