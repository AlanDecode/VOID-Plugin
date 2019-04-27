<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * VOID 主题配套插件
 * 
 * @package VOID
 * @author 熊猫小A
 * @version 1.0.0
 * @link https://blog.imalan.cn
 */

require_once('libs/WordCount.php');

class VOID_Plugin implements Typecho_Plugin_Interface
{
    static $VERSION = 1.00;

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

        /** 字数统计相关 */
        // contents 表中若无 wordCount 字段则添加
        if (!array_key_exists('wordCount', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD `wordCount` INT(10) DEFAULT 0;');
        // 删除以前创建的沙雕字段
        if (array_key_exists('wordCountTime', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` DROP `wordCountTime`;');
        // 更新一次字数统计
        VOID_WordCount::updateAllWordCount();
        // 注册 hook
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('VOID_Plugin', 'updateWordCount');

        /** 点赞相关 */
        // 创建字段
        if (!array_key_exists('likes', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD `likes` INT(10) DEFAULT 0;');
        Helper::addAction('void_like', 'VOID_Action');
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
        Helper::removeAction("void_like");
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
     * 更新文章字数统计
     * 
     * @access public
     * @param  mixed $archive
     * @return void
     */
    public static function updateWordCount($contents, $widget)
    {
        VOID_WordCount::wordCountByCid($widget->cid);
    }
}