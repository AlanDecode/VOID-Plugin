<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 为图片获取长款信息
 * 
 * @author AlanDecode | 熊猫小A
 */

Class VOID_ParseImgInfo
{
    private static function hasDomSupport()
    {
        return class_exists('DOMDocument') && class_exists('DOMXPath');
    }

    private static function contentToHtml($content)
    {
        $content = (string)$content;

        if (0 === strpos($content, '<!--html-->')) {
            return $content;
        }

        if (class_exists('Markdown') && method_exists('Markdown', 'convert')) {
            return (string)Markdown::convert($content);
        }

        return $content;
    }

    private static function collectImageSources($html)
    {
        if (!self::hasDomSupport()) {
            return array();
        }

        $html = trim((string)$html);
        if ($html === '') {
            return array();
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = '<?xml encoding="UTF-8"><!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $options = 0;
        foreach (array('LIBXML_COMPACT', 'LIBXML_NOERROR', 'LIBXML_NOWARNING') as $flag) {
            if (defined($flag)) {
                $options |= constant($flag);
            }
        }

        $previousErrorHandling = libxml_use_internal_errors(true);
        $loaded = $options
            ? $dom->loadHTML($wrappedHtml, $options)
            : $dom->loadHTML($wrappedHtml);

        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
            return array();
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//img');
        $sources = array();

        if ($nodes instanceof DOMNodeList) {
            foreach ($nodes as $node) {
                $sources[] = $node instanceof DOMElement ? trim((string)$node->getAttribute('src')) : '';
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        return $sources;
    }

    private static function updateContentText($cid, $content)
    {
        $db = Typecho_Db::get();
        $db->query($db->update('table.contents')
            ->rows(array('text' => (string)$content))
            ->where('cid = ?', $cid));
    }

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
        $content = isset($content['text']) ? (string)$content['text'] : '';

        $html = self::contentToHtml($content);
        $imgArr = self::collectImageSources($html);

        if (!count($imgArr)) return array(0, 0, 0, 0);

        $limit = Helper::options()->plugin('VOID')->parseImgLimit;
        if (empty($limit) || (int)$limit <= 0) $limit = 10;

        $result = array(0, 0, 0, 0);
        $result[0] = count($imgArr);
        $updatedContent = $content;
        $contentChanged = false;

        foreach ($imgArr as $src) {
            $src = trim((string)$src);

            if ($src === '') {
                $result[3]++;
                continue;
            }

            if (strpos($src, 'vwid') !== false) {
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

            $updatedContent = str_replace($src, $src_new, $updatedContent, $replaceCount);
            if ($replaceCount > 0) {
                $contentChanged = true;
            }
            $result[1]++;

            if (++$GLOBALS['ImgParsed'] >= $limit) {
                if ($contentChanged) {
                    self::updateContentText($cid, $updatedContent);
                }
                return $result;
            }
        }

        if ($contentChanged) {
            self::updateContentText($cid, $updatedContent);
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
        $content = $content['text'] ?? null;
        if ($content === null) return 0;

        $count = 0;
        $content = preg_replace("/#vwid=\d{0,5}&vhei=\d{0,5}/i", '', $content, -1, $count);
        
        if ($count) {
            self::updateContentText($cid, $content);
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
        $meta = @getimagesize($url);
        if ($meta == false) {
            // 尝试另一种方式
            $meta = self::GetImageSizeCURL($url);
            if ($meta == false) return false;
        }

        return array('width'=>$meta[0],'height'=>$meta[1]);
    }

    /**
     * 通过 CURL 方式获取
     */
    private static function GetImageSizeCURL($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RANGE, '0-167');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, Helper::options()->siteUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $dataBlock = curl_exec($ch);
        curl_close($ch);

        if (!$dataBlock) return false;

        return getimagesize('data://image/jpeg;base64,'. base64_encode($dataBlock));
    }
}
