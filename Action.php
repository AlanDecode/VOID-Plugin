<?php
/**
 * Action for VOID Plugin
 * 
 * @author AlanDecode | 熊猫小A
 */

class VOID_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 点赞自增
     */
    public function up(){
        $db = Typecho_Db::get();
        $cid=$this->request->filter('int')->cid;
        if($cid){
            try {
                $row = $db->fetchRow($db->select('likes')
                    ->from('table.contents')
                    ->where('cid = ?', $cid));
                $db->query($db->update('table.contents')
                    ->rows(array('likes' => (int)$row['likes']+1))
                    ->where('cid = ?', $cid));
                $this->response->throwJson("success");
            } catch (Exception $ex) {
               echo $ex->getCode(); 
            }
        }  else {
            echo "error";
        }
    }

    public function comment_execute(){
        $db = Typecho_Db::get();
        if($this->coid){
            try {
                $row = $db->fetchRow($db->select($this->col)
                    ->from('table.comments')
                    ->where('coid = ?', $this->coid));
                $db->query($db->update('table.comments')
                    ->rows(array($this->col => (int)$row[$this->col]+1))
                    ->where('coid = ?', $this->coid));
                $this->response->throwJson("success");
            } catch (Exception $ex) {
               echo $ex->getCode(); 
            }
        }  else {
            echo "error";
        }
    }

    private $coid = 0;
    private $col = '';
    public function comment_like () {
        $this->coid=$this->request->filter('int')->coid;
        $this->col='likes';
        $this->comment_execute();
    }

    public function comment_dislike () {
        $this->coid=$this->request->filter('int')->coid;
        $this->col='dislikes';
        $this->comment_execute();
    }

    public function action(){
        $this->on($this->request->is('post_up'))->up();
        $this->on($this->request->is('comment_like'))->comment_like();
        $this->on($this->request->is('comment_dislike'))->comment_dislike();
        $this->response->goBack();
    }
}