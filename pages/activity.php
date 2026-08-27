<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 展示互动情况，比如评论赞踩、文章点赞等
 */
$activityCssPath = dirname(__FILE__) . '/../assets/admin/activity/activity.css';
$activityJsPath = dirname(__FILE__) . '/../assets/admin/activity/activity.js';
$activityCssVersion = @filemtime($activityCssPath) ?: '1.4.1';
$activityJsVersion = @filemtime($activityJsPath) ?: '1.4.1';

include 'header.php';
include 'menu.php';
?>
<link rel="stylesheet" href="<?php Helper::options()->pluginUrl('VOID/assets/admin/activity/activity.css') ?>?v=<?php echo rawurlencode((string)$activityCssVersion); ?>">

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
<script src="<?php Helper::options()->pluginUrl('VOID/assets/admin/activity/activity.js') ?>?v=<?php echo rawurlencode((string)$activityJsVersion); ?>"></script>

<?php
include 'footer.php';
?>
