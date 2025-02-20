<?php
// rss.php
header("Content-Type: application/rss+xml; charset=UTF-8");
require_once 'config.php';  // 确保引入数据库连接

// 查询最新 30 条消息
$sql = "SELECT * FROM messages ORDER BY post_time DESC LIMIT 30";
$result = $conn->query($sql);

// 输出 RSS XML
echo '<?xml version="1.0" encoding="UTF-8"?>'; 
?>
<rss version="2.0">
  <channel>
    <title>树洞 - 最新30条消息</title>
    <!-- 请将下面的链接替换为你的网站地址 -->
    <link>https://ttt.mios.fun/</link>
    <description>树洞留言板最新的30条消息</description>
    <language>zh-cn</language>
    <?php while($msg = $result->fetch_assoc()): 
      // 格式化发布时间
      $pubDate = date(DATE_RSS, strtotime($msg['post_time']));
      // 构造每条消息的链接，这里假设使用 share.php 查看详情
      $itemLink = "https://ttt.mios.fun/share.php?id=" . $msg['id'];
    ?>
    <item>
      <title><![CDATA[树洞消息 #<?php echo $msg['id']; ?>]]></title>
      <link><?php echo $itemLink; ?></link>
      <description><![CDATA[<?php echo $msg['content']; ?>]]></description>
      <pubDate><?php echo $pubDate; ?></pubDate>
      <guid><?php echo $itemLink; ?></guid>
    </item>
    <?php endwhile; ?>
  </channel>
</rss>
