<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * VOID 主题配套插件
 * 
 * @package VOID
 * @author 熊猫小A
 * @version 1.2
 * @link https://blog.imalan.cn
 */

require_once('libs/WordCount.php');
require_once('libs/IP.php');
require_once('libs/ParseImg.php');

class VOID_Plugin implements Typecho_Plugin_Interface
{
    public static $VERSION = 1.2;

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $adapterName =  strtolower($db->getAdapterName());

        /** 字数统计相关 */
        // contents 表中若无 wordCount 字段则添加
        if (!array_key_exists('wordCount', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD COLUMN `wordCount` INT(10) DEFAULT 0;');
        // 更新一次字数统计
        VOID_WordCount::updateAllWordCount();
        // 注册 hook
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('VOID_Plugin', 'updateContent');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('VOID_Plugin', 'updateContent');

        /** 点赞相关 */
        // 创建字段
        if (!array_key_exists('likes', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD COLUMN `likes` INT(10) DEFAULT 0;');
        
        /** 评论赞踩 */
        // 创建字段
        if (!array_key_exists('likes', $db->fetchRow($db->select()->from('table.comments'))))
            $db->query('ALTER TABLE `'. $prefix .'comments` ADD COLUMN `likes` INT(10) DEFAULT 0;');
        if (!array_key_exists('dislikes', $db->fetchRow($db->select()->from('table.comments'))))
            $db->query('ALTER TABLE `'. $prefix .'comments` ADD COLUMN `dislikes` INT(10) DEFAULT 0;');

        /** 浏览量统计相关 */
        // 创建字段
        if (!array_key_exists('viewsNum', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD COLUMN `viewsNum` INT(10) DEFAULT 0;');
        //增加浏览数
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('VOID_Plugin', 'updateViewCount');

        // 增加查询方法
        Typecho_Plugin::factory('Widget_Archive')->___viewsNum = array('VOID_Plugin', 'viewsNum');
        Typecho_Plugin::factory('Widget_Archive')->___likes = array('VOID_Plugin', 'likes');
        Typecho_Plugin::factory('Widget_Archive')->___wordCount = array('VOID_Plugin', 'wordCount');

        // 创建表，保存点赞与投票相关信息
        $table_name = $prefix . 'votes';
        $sql = "SHOW TABLES LIKE '%" . $table_name . "%'";

        try {
            if (count($db->fetchAll($sql)) == 0) {
                $sql = 'create table `'.$table_name.'` (
                    `vid` int unsigned auto_increment,
                    `id` int unsigned not null,
                    `table` char(32) not null,
                    `type` char(32) not null,
                    `agent` text,
                    `ip` varchar(128),
                    `created` int unsigned default 0,
                    primary key (`vid`)
                ) default charset=utf8;
                CREATE INDEX index_ip ON '.$table_name.'(`ip`);
                CREATE INDEX index_id ON '.$table_name.'(`id`);
                CREATE INDEX index_table ON '.$table_name.'(`table`)';

                $sqls = explode(';', $sql);
                foreach ($sqls as $sql) {
                    $db->query($sql);
                }
            } else {
                if (!array_key_exists('created', $db->fetchRow($db->select()->from('table.votes')))) {
                    $db->query('ALTER TABLE `'. $prefix .'votes` ADD COLUMN `created` INT(10) DEFAULT 0;');
                }
            }
        } catch (Typecho_Db_Query_Exception $th) {
            throw new Typecho_Plugin_Exception($th->getMessage());
        }

        /**
         * 添加一个面板，展示互动信息，例如评论赞踩、文章点赞
         */
        Helper::addPanel(3, 'VOID/pages/showActivity.php', '互动', '查看访客互动', 'administrator');

        // 添加投票路由，文章与评论
        Helper::addAction('void_vote', 'VOID_Action');

        // 评论列表显示来源
        Typecho_Plugin::factory('Widget_Comments_Admin')->callIp = array('VOID_Plugin', 'commentLocation');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
	{
        Helper::removeAction('void_vote');
        Helper::removePanel(3, 'VOID/pages/showActivity.php');
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
	{
		
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 返回文章字数
     */
    public static function viewsNum($archive)
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('viewsNum')
            ->from('table.contents')
            ->where('cid = ?', $archive->cid));
        return $row['viewsNum'];
    }

    /**
     * 返回文章点赞数
     */
    public static function likes($archive)
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('likes')
            ->from('table.contents')
            ->where('cid = ?', $archive->cid));
        return $row['likes'];
    }

    /**
     * 返回文章字数
     */
    public static function wordCount($archive)
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select('wordCount')
            ->from('table.contents')
            ->where('cid = ?', $archive->cid));
        return $row['wordCount'];
    }

    /**
     * 更新文章字数统计
     * 
     * @access public
     * @param  mixed $archive
     * @return void
     */
    public static function updateContent($contents, $widget)
    {
        VOID_WordCount::wordCountByCid($widget->cid);
        VOID_ParseImgInfo::parse($widget->cid);
    }

    /**
     * 更新文章浏览量
     * 
     * @param Widget_Archive   $archive
     * @return void
     */
    public static function updateViewCount($archive) {
        if($archive->is('single')){
            $cid = $archive->cid;
            $views = Typecho_Cookie::get('__void_post_views');
            if(empty($views)){
                $views = array();
            } else {
                $views = explode(',', $views);
            }
            if(!in_array($cid,$views)){
                $db = Typecho_Db::get();
                $row = $db->fetchRow($db->select('viewsNum')
                    ->from('table.contents')
                    ->where('cid = ?', $cid));
                $db->query($db->update('table.contents')
                    ->rows(array('viewsNum' => (int)$row['viewsNum']+1))
                    ->where('cid = ?', $cid));
                array_push($views, $cid);
                $views = implode(',', $views);
                Typecho_Cookie::set('__void_post_views', $views); //记录查看cookie
            }
        }
    }

    /**
     * 插件实现方法
     * 
     * @access public
     * @param Typecho_Widget $comments 评论
     * @return void
     */
    public static function commentLocation($comments)
    {
        $location = IPLocation_IP::locate($comments->ip);
        echo $comments->ip . '<br>' . $location;
    }
}