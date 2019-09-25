<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 为图片获取长款信息
 * 
 * @author AlanDecode | 熊猫小A
 */

require_once 'ParserDom.php';

Class VOID_ParseImgInfo
{
    /**
     * 解析 $cid 指定文章中的图片数据
     * 
     * @return array (图片总数 | 执行解析数 | 跳过数 | 失败数)
     */
    public static function parse($cid)
    {
        $db = Typecho_Db::get();

        $content = $db->fetchRow($db->select('text')
                ->from('table.contents')
                ->where('cid = ?', $cid));
        $content = $content['text'];
        
        $html = Markdown::convert($content);
        $doc = new \HtmlParser\ParserDom($html);
        $imgArr = $doc->find("img");

        $result = array(0, 0, 0, 0);
        $result[0] = count($imgArr);
        
        $changed = false;
        foreach ($imgArr as $v) {
            $src = $v->getAttr('src');
            if (strpos($src, 'vwid') != false) {
                $result[2]++;
                continue; // 已经处理过该图片
            }

            $size = self::GetImageSize($src);
            if ($size == false) {
                $result[3]++;
                continue; // 该图片获取失败
            }

            $src_new = $src.'#vwid='.$size['width'].'&vhei='.$size['height'];
            echo $src .' => '. $src_new.'<br>'.PHP_EOL;

            // 更新原文章数据
            $changed = true;
            $content = str_replace($src, $src_new, $content);
            $result[1]++;
        }

        // 更新数据库
        if ($changed) {
            $db->query($db->update('table.contents')
                ->rows(array('text' => $content))
                ->where('cid = ?',  $cid));
        }

        return $result;
    }

    /**
     * 清理连接中包含的长宽信息
     * 
     * @return int 清理图片数
     */
    public static function clean($cid)
    {
        $db = Typecho_Db::get();

        $content = $db->fetchRow($db->select('text')
                ->from('table.contents')
                ->where('cid = ?', $cid));
        $content = $content['text'];

        $count = 0;
        $content = preg_replace("/#vwid=\d{0,5}&vhei=\d{0,5}/i", '', $content, -1, $count);
        
        if ($count) {
            $db->query($db->update('table.contents')
                ->rows(array('text' => $content))
                ->where('cid = ?',  $cid));
        }

        return $count;
    }

    /**
     * 获取远程图片的宽高和体积大小
     *
     * @param string $url 远程图片的链接
     * @return false|array
     */
    public static function GetImageSize($url) 
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RANGE, '0-167');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $dataBlock = curl_exec($ch);
        curl_close($ch);

        if (! $dataBlock) return false;
    
        $size = getimagesize('data://image/jpeg;base64,'. base64_encode($dataBlock));
        if (empty($size)) {
            return false;
        }
    
        $result['width'] = $size[0];
        $result['height'] = $size[1];
    
        return $result;
    }
}
