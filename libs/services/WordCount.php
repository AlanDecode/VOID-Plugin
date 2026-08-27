<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 字数统计相关
 * 
 * @author AlanDecode | 熊猫小A
 */

class VOID_WordCount
{
    private static function fetchAllContentIds()
    {
        $db = Typecho_Db::get();

        return $db->fetchAll($db->select('cid')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->orWhere('type = ?', 'page'));
    }

    private static function normalizeSourceText($text)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string)$text);
        $text = preg_replace('/#vwid=\d{1,5}&vhei=\d{1,5}/i', '', $text);
        $text = preg_replace('/<!--more-->/i', ' ', $text);
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/~~~[\s\S]*?~~~/u', ' ', $text);
        $text = preg_replace('/`[^`\n]+`/u', ' ', $text);
        $text = preg_replace('/!\[[^\]]*]\([^)]*\)/u', ' ', $text);
        $text = preg_replace('/\[photos[^\]]*\][\s\S]*?\[\/photos\]/iu', ' ', $text);
        $text = preg_replace('/\[links[^\]]*\][\s\S]*?\[\/links\]/iu', ' ', $text);
        $text = preg_replace('/\[notice([^\]]*)\]([\s\S]*?)\[\/notice\]/iu', '$2', $text);
        $text = preg_replace('/^\s{0,3}\[[^\]]+\]:\s+\S+(?:\s+".*?")?\s*$/mu', ' ', $text);
        $text = preg_replace('/\{\{(.+?):(.+?)\}\}/u', '$1', $text);
        $text = preg_replace('/::\((.*?)\)|:@\((.*?)\)|:&\((.*?)\)|:\$\((.*?)\)|:!\((.*?)\)/u', ' ', $text);

        return $text;
    }

    private static function sourceToHtml($text)
    {
        $text = (string)$text;
        $isHtml = preg_match('/^\s*<!--html-->/i', $text) === 1;

        if ($isHtml) {
            return preg_replace('/^\s*<!--html-->\s*/i', '', $text);
        }

        $text = preg_replace('/^\s*<!--markdown-->\s*/i', '', $text);

        if (class_exists('Markdown') && method_exists('Markdown', 'convert')) {
            return (string)Markdown::convert($text);
        }

        return $text;
    }

    private static function htmlToPlainText($html)
    {
        $html = (string)$html;
        $html = preg_replace('#<pre\b[^>]*>[\s\S]*?</pre>#iu', ' ', $html);
        $html = preg_replace('#<code\b[^>]*>[\s\S]*?</code>#iu', ' ', $html);
        $html = preg_replace('#<script\b[^>]*>[\s\S]*?</script>#iu', ' ', $html);
        $html = preg_replace('#<style\b[^>]*>[\s\S]*?</style>#iu', ' ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[\x{00AD}\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string)$text);
    }

    private static function analyzePlainText($text)
    {
        $text = (string)$text;
        $chinese = preg_match_all('/[\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $text, $matches);
        $latinWords = preg_match_all("/[A-Za-z]+(?:['’-][A-Za-z]+)*/u", $text, $matches);
        $numbers = preg_match_all('/\b\d+(?:\.\d+)?\b/u', $text, $matches);

        return array(
            'total' => (int)$chinese + (int)$latinWords + (int)$numbers,
            'chinese' => (int)$chinese,
            'latinWords' => (int)$latinWords,
            'numbers' => (int)$numbers
        );
    }

    public static function analyze($text)
    {
        $normalized = self::normalizeSourceText($text);
        $html = self::sourceToHtml($normalized);
        $plainText = self::htmlToPlainText($html);

        return self::analyzePlainText($plainText);
    }

    public static function calculate($text)
    {
        $result = self::analyze($text);
        return (int)$result['total'];
    }

    /**
     * 更新所有的字数统计
     */
    public static function updateAllWordCount()
    {
        $rows = self::fetchAllContentIds();

        foreach ($rows as $row) {
            self::wordCountByCid($row['cid']);
        }
    }

    /**
     * 根据 cid 更新字数
     */
    public static function wordCountByCid($cid){
        $db = Typecho_Db::get();
        $row = $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', $cid));
        if (!$row || !isset($row['cid'])) {
            return 0;
        }

        $text = $row['text'] ?? '';
        $count = self::calculate($text);

        $db->query($db->update('table.contents')->rows(array('wordCount' => (int)$count))->where('cid = ?', $cid));

        return (int)$count;
    }
}
