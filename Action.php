<?php
/**
 * Action for VOID Plugin
 *
 * @author AlanDecode | 熊猫小A
 */

class VOID_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $body = null;
    public function action()
    {
        $this->body = json_decode(file_get_contents('php://input'), true);

        $this->on($this->request->is('content'))->vote_content();
        $this->on($this->request->is('comment'))->vote_comment();

        //$this->response->goBack();
    }

    private function vote_comment()
    {
        if($this->body['type'] == 'up') {
            $this->vote_excute('comments', 'coid', $this->body['id'], 'likes', 'up');
        } else {
            $this->vote_excute('comments', 'coid', $this->body['id'], 'dislikes', 'down');
        }
    }

    private function vote_content()
    {
        $this->vote_excute('contents', 'cid', $this->body['id'], 'likes', 'up');
    }

    private function vote_excute($table, $key, $id, $field, $type)
    {
        header("Content-type:application/json");
        $db = Typecho_Db::get();

        // 检测重复 IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $rows = null;
        try {
            $rows = $db->fetchAll($db->select('type')
                        ->from('table.votes')
                        ->where('ip = ?', $ip)
                        ->where('id = ?', $id)
                        ->where('table = ?', $table));
        } catch (Typecho_Db_Query_Exception $th) {
            echo json_encode(array(
                'code'=> 500,
                'msg'=> $th->getMessage()
            ));
        }

        if(count($rows)) {
            $row = $rows[0];
            if ($row['type'] != $type) {
                // 不允许改变投票类型
                echo json_encode(array(
                    'code'=> 403,
                    'msg'=> 'can\'t change vote'
                ));
            } else {
                echo json_encode(array(
                    'code'=> 302,
                    'msg' => 'done'
                ));
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
                    'type' => $this->body['type'],
                    'agent' => $_SERVER['HTTP_USER_AGENT'],
                    'ip' => $ip
                )));

                echo json_encode(array(
                    'code'=> 200,
                    'msg'=> 'done'
                ));
            } catch (Typecho_Db_Query_Exception $th) {
                echo json_encode(array(
                    'code'=> 500,
                    'msg'=> $th->getMessage()
                ));
            }
        }
    }
}
