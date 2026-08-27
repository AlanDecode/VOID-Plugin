<?php
/**
 * Action for VOID Plugin
 *
 * @author AlanDecode | 熊猫小A
 */
require_once __DIR__ . '/libs/bootstrap.php';

// 为兼容 Typecho 1.3 移除的旧式 Interface 别名
if (!interface_exists('Widget_Interface_Do') && interface_exists('\Widget\ActionInterface')) {
    class_alias('\Widget\ActionInterface', 'Widget_Interface_Do');
}

/**
 * 根据ID获取单个Widget对象
 *
 * @param string $table 表名, 支持 contents, comments, metas, users
 * @return Widget_Abstract
 */
function widgetById($table, $pkId)
{
    $table = ucfirst($table);
    if (!in_array($table, array('Contents', 'Comments', 'Metas', 'Users'))) {
        return NULL;
    }

    $keys = array(
        'Contents'  =>  class_exists('\Widget\Base\Contents') ? '\Widget\Base\Contents' : 'Widget_Abstract_Contents',
        'Comments'  =>  class_exists('\Widget\Base\Comments') ? '\Widget\Base\Comments' : 'Widget_Abstract_Comments',
        'Metas'     =>  class_exists('\Widget\Base\Metas') ? '\Widget\Base\Metas' : 'Widget_Abstract_Metas',
        'Users'     =>  class_exists('\Widget\Users\Author') ? '\Widget\Users\Author' : 'Widget_Abstract_Users'
    );

    $className = $keys[$table];
    $key = array(
        'Contents'  =>  'cid',
        'Comments'  =>  'coid',
        'Metas'     =>  'mid',
        'Users'     =>  'uid'
    )[$table];
    $db = Typecho_Db::get();
    
    // 兼容 Typecho 1.2 及 1.3 的通用获取方法
    $widget = Typecho_Widget::widget($className);
    
    $db->fetchRow(
        $widget->select()->where("{$key} = ?", $pkId)->limit(1),
            array($widget, 'push'));

    return $widget;
}

$GLOBALS['ImgParsed'] = 0;

class VOID_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $body = null;

    private function securityWidget()
    {
        return Typecho_Widget::widget('Widget_Security');
    }

    private function require_admin_user()
    {
        Typecho_Widget::widget('Widget_User')->to($user);
        return $user->have() && $user->hasLogin() && $user->pass('administrator', true);
    }

    private function vote_json_response($code, $msg, $extra = array())
    {
        $payload = array_merge(array(
            'code' => (int)$code,
            'msg' => (string)$msg
        ), is_array($extra) ? $extra : array());

        echo json_encode($payload);
    }

    private function vote_request_id()
    {
        if (!is_array($this->body) || !array_key_exists('id', $this->body)) {
            return 0;
        }

        $id = filter_var($this->body['id'], FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1)
        ));

        return $id ? (int)$id : 0;
    }

    private function vote_request_type($allowed = array('up'))
    {
        if (!is_array($this->body) || !array_key_exists('type', $this->body)) {
            return '';
        }

        $type = strtolower(trim((string)$this->body['type']));
        return in_array($type, $allowed, true) ? $type : '';
    }

    public function action()
    {
        $raw = file_get_contents('php://input');
        $this->body = $raw ? json_decode($raw, true) : null;

        $this->on(isset($_GET['content']) || isset($_POST['content']))->vote_content();
        $this->on(isset($_GET['comment']) || isset($_POST['comment']))->vote_comment();
        $this->on(isset($_GET['show']) || isset($_POST['show']))->vote_show();
        $this->on(isset($_GET['getimginfo']) || isset($_POST['getimginfo']))->void_img_info();
        $this->on(isset($_GET['getsingleimginfo']) || isset($_POST['getsingleimginfo']))->void_single_img_info();
        $this->on(isset($_GET['cleanimginfo']) || isset($_POST['cleanimginfo']))->void_clean_img_info();
        $this->on(isset($_GET['wordcount_preview']) || isset($_POST['wordcount_preview']))->wordcount_preview();
        
        //$this->response->goBack();
    }

    // 为图片获取长宽信息，并替换原src
    private function void_single_img_info()
    {
        // 要求先登录
        if (!$this->require_admin_user()) {
            echo 'Invalid Request';
            exit;
        }
        
        $cid = $_GET['cid'] ?? null;
        if (!$cid) return;
        print_r(VOID_ParseImgInfo::parse($cid));
    }

    // 清理图片长宽信息，替换 src
    private function void_clean_img_info()
    {
        // 要求先登录
        if (!$this->require_admin_user()) {
            echo 'Invalid Request';
            exit;
        }

        $db = Typecho_Db::get();

        // 文章内容
        $rows = $db->fetchAll($db->select('cid')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->orWhere('type = ?', 'page')
            ->order('created', Typecho_Db::SORT_DESC)); // 从最近的开始
        
        echo '共 ' .count($rows). ' 篇文章<br>'.PHP_EOL;

        for ($index=0; $index < count($rows); $index++) { 
            $row = $rows[$index];
            $ret = VOID_ParseImgInfo::clean($row['cid']);

            echo '第 '.($index+1).' 篇文章...共清理 '.$ret.' 张图片<br>'.PHP_EOL;
        }
    }

    // 为图片获取长宽信息，并替换原src
    private function void_img_info()
    {
        // 要求先登录
        if (!$this->require_admin_user()) {
            echo 'Invalid Request';
            exit;
        }

        $db = Typecho_Db::get();

        // 文章内容
        $rows = $db->fetchAll($db->select('cid')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->orWhere('type = ?', 'page')
            ->order('created', Typecho_Db::SORT_DESC)); // 从最近的开始
        
        echo '共 ' .count($rows). ' 篇文章<br>'.PHP_EOL;

        $limit = Helper::options()->plugin('VOID')->parseImgLimit;
        if (empty($limit)) $limit = 10;

        $total = 0; // 所有的图片数
        $success = 0; // 解析成功的图片数
        $bad = 0; // 解析失败的图片数
        $jump = 0;
        $index = 0;
        for (; $index < count($rows); $index++) { 
            echo '开始处理第 '.($index+1).' 篇文章...<br>'.PHP_EOL;

            $row = $rows[$index];
            $ret = VOID_ParseImgInfo::parse($row['cid']);

            $total += $ret[0];
            $success += $ret[1];
            $jump += $ret[2];
            $bad += $ret[3];

            if ($GLOBALS['ImgParsed'] >= $limit)
                break;
        }

        // 输出本次处理情况
        echo '本次共解析 '.$success.' 张图片，跳过 '.$jump.' 张图片。'.$bad.' 张图片处理失败。<br>';

        // 若全部处理完成
        if ($total == ($success + $jump + $bad))
            echo '处理完毕。<br>';
        else
            echo '解析尚未完成，请刷新继续处理...<br>';
    }

    private function vote_comment()
    {
        if (!is_array($this->body)) return;

        $id = $this->vote_request_id();
        $type = $this->vote_request_type(array('up', 'down'));
        if (!$id || $type === '') {
            header("Content-type:application/json");
            $this->vote_json_response(400, 'invalid vote payload');
            return;
        }

        if ($type === 'up') {
            $this->vote_excute('comments', 'coid', $id, 'likes', 'up');
        } else {
            $this->vote_excute('comments', 'coid', $id, 'dislikes', 'down');
        }
    }

    private function vote_content()
    {
        if (!is_array($this->body)) return;

        $id = $this->vote_request_id();
        $type = $this->vote_request_type(array('up'));
        if (!$id || $type === '') {
            header("Content-type:application/json");
            $this->vote_json_response(400, 'invalid vote payload');
            return;
        }

        $this->vote_excute('contents', 'cid', $id, 'likes', 'up');
    }

    private function vote_show ()
    {
        $db = Typecho_Db::get();
        $pageSize = 10;

        if (!$this->require_admin_user()) {
            echo 'Invalid Request';
            exit;
        }

        header("Content-type:application/json");
        $older_than = null;
        if (array_key_exists('older_than', $_GET))
            $older_than = $_GET['older_than'];
        
        $query = $db->select()
                    ->from('table.votes')
                    ->order('table.votes.created', Typecho_Db::SORT_DESC)
                    ->limit($pageSize);
        if ($older_than)
            $query = $query->where('table.votes.created < ?', $older_than);
        
        $rows = $db->fetchAll($query);

        if (!count($rows)) {
            echo json_encode(array(
                'stamp' => -1,
                'data' => array()
            ));
            exit;
        }

        $arr = array(
            'stamp' => $rows[count($rows) - 1]['created'],
            'data' => array()
        );
        foreach ($rows as $row) {
            $instance = widgetById($row['table'], $row['id']);
            if (!$instance->have()) continue;

            $content = '';
            if ($row['table'] == 'comments') {
                $content = $instance->content;
                $content = Typecho_Common::stripTags($content ?? '');
                $content = mb_substr($content ?? '', 0, 12);
                $content .= '...';
            } else {
                $content = $instance->title;
            }

            $item = array(
                'vid' => $row['vid'],
                'url' => $instance->permalink,
                'from' => $row['table'],
                'content' => $content,
                'type' => $row['type'],
                'created' => $row['created'],
                'created_format' => date('Y-m-d H:i', $row['created']),
                'os' => ParseAgent::getOs($row['agent']),
                'browser' => ParseAgent::getBrowser($row['agent']),
                'location' => VOID_IpDb::locate($row['ip'])
            );
            $arr['data'][] = $item;
        }

        echo json_encode($arr);
    }

    private function wordcount_preview()
    {
        header("Content-type:application/json");

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->require_admin_user()) {
            $this->vote_json_response(403, 'invalid request');
            return;
        }

        $text = '';
        if (is_array($this->body) && array_key_exists('text', $this->body)) {
            $text = (string)$this->body['text'];
        }

        $result = VOID_WordCount::analyze($text);
        echo json_encode($result);
    }

    private function vote_verify_source()
    {
        $site_url = Helper::options()->siteUrl;
        $site = parse_url($site_url);
        $site_host = strtolower($site['host'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        if (!$site_host) return false;

        $site_scheme = strtolower($site['scheme'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
        $site_port = isset($site['port']) ? intval($site['port']) : ($site_scheme === 'https' ? 443 : 80);

        $sources = array();
        if (!empty($_SERVER['HTTP_ORIGIN'])) $sources[] = $_SERVER['HTTP_ORIGIN'];
        if (!empty($_SERVER['HTTP_REFERER'])) $sources[] = $_SERVER['HTTP_REFERER'];
        if (!count($sources)) return false;

        foreach ($sources as $source) {
            $parts = parse_url($source);
            if (!is_array($parts) || !array_key_exists('host', $parts)) continue;

            $host = strtolower($parts['host']);
            $scheme = strtolower($parts['scheme'] ?? 'http');
            $port = isset($parts['port']) ? intval($parts['port']) : ($scheme === 'https' ? 443 : 80);
            if ($host === $site_host && $port === $site_port) {
                return true;
            }
        }

        return false;
    }

    private function vote_verify_request()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $token = null;
        if (is_array($this->body) && array_key_exists('_', $this->body)) {
            $token = $this->body['_'];
        } elseif (isset($_REQUEST['_'])) {
            $token = $_REQUEST['_'];
        }

        // 兼容 Typecho 安全 token：前端若已携带则优先校验
        if (is_string($token) && $token !== '') {
            $referer = $this->request->getReferer();
            if ($token === $this->securityWidget()->getToken($referer)) {
                return true;
            }
        }

        // 不改现有前端协议：未携带 token 时走同源来源校验
        return $this->vote_verify_source();
    }

    private function vote_excute($table, $key, $id, $field, $type)
    {
        header("Content-type:application/json");
        $db = Typecho_Db::get();

        if (!$this->vote_verify_request()) {
            $this->vote_json_response(403, 'invalid request');
            return;
        }

        // 检测重复 IP
        $ip = $_SERVER['REMOTE_ADDR'];

        $target = null;
        try {
            $target = $db->fetchRow($db->select($key)
                        ->from('table.' . $table)
                        ->where($key . ' = ?', $id)
                        ->limit(1));
        } catch (Typecho_Db_Query_Exception $th) {
            $this->vote_json_response(500, $th->getMessage());
            return;
        }

        if (!$target || !isset($target[$key])) {
            $this->vote_json_response(404, 'target not found');
            return;
        }

        $row = null;
        try {
            $row = $db->fetchRow($db->select('type')
                        ->from('table.votes')
                        ->where('ip = ?', $ip)
                        ->where('id = ?', $id)
                        ->where('table = ?', $table)
                        ->limit(1));
        } catch (Typecho_Db_Query_Exception $th) {
            $this->vote_json_response(500, $th->getMessage());
            return;
        }

        if (is_array($row) && count($row)) {
            if ($row['type'] != $type) {
                // 不允许改变投票类型
                $this->vote_json_response(403, 'can\'t change vote');
            } else {
                $this->vote_json_response(302, 'done');
            }
        } else {
            try {
                // 更新表
                $row = $db->fetchRow($db->select($field)
                            ->from('table.'.$table)
                            ->where($key.' = ?', $id));
                $newValue = (int)$row[$field] + 1;
                $db->query($db->update('table.'.$table)
                    ->rows(array($field => $newValue))
                    ->where($key.' = ?', $id));
            
                // 插入新投票记录
                $db->query($db->insert('table.votes')->rows(array(
                    'id' => $id,
                    'table' => $table,
                    'type' => $type,
                    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $ip,
                    'created' => time()
                )));

                $this->vote_json_response(200, 'done');
            } catch (Typecho_Db_Query_Exception $th) {
                $this->vote_json_response(500, $th->getMessage());
            }
        }
    }

}
