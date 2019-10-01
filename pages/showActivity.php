<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 展示互动情况，比如评论赞踩、文章点赞等
 */
include 'header.php';
include 'menu.php';
?>
<link rel="stylesheet" href="<?php Helper::options()->pluginUrl('VOID/pages/votes.03.css') ?>">

<div class="main">
    <div class="body container">
        <div class="row typecho-page-main" role="main">
            <div id="votes-container">
                <div class="typecho-page-title" style="margin-top: 50px">
                    <h2>最近的访客互动</h2>
                </div>
                <ul id="votes">
                </ul>
                <button class="btn primary loadmore" onclick="window.loadMoreActivity()">加载更多</button>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php'
?>

<script>
window.queryActivityUrl = "<?php Helper::options()->index('/action/void?show'); ?>";
</script>
<script src="<?php Helper::options()->pluginUrl('VOID/pages/votes.02.js') ?>"></script>

<?php
include 'footer.php';
?>