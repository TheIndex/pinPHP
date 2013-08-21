<?php

class topicAction extends frontendAction {

    public function _initialize(){
        parent::_initialize();
        //访问者控制
        if (!$this->visitor->is_login) {
            IS_AJAX && $this->ajaxReturn(0, L('login_please'));
            $this->redirect('user/login');
        }
    }

    /**
     * 转发微博
     */
    public function forward() {
        if (IS_POST) {
            foreach ($_POST as $key=>$val) {
                $_POST[$key] = Input::deleteHtmlTags($val);
            }
            $tid = $this->_post('tid', 'intval');
            $content = $this->_post('content', 'trim');
            $topic_mod = M('topic');
            //微薄是否有效
            $topic_info = $topic_mod->field('id,uid')->find($tid);
            !$topic_info && $this->ajaxReturn(0, L('forward_invalid_topic'));
            $data = array(
                'uid' => $this->visitor->info['id'],
                'uname' => $this->visitor->info['username'],
                'content' => $content,
                'type' => 1,
            );
            if (false !== $topic_mod->create($data)) {
                $forward_tid = $topic_mod->add();
                if ($forward_tid) {
                    //添加微博关系
                    M('topic_relation')->add(array(
                        'tid' => $forward_tid,
                        'src_tid' => $topic_info['id'],
                        'author_uid' => $topic_info['uid'],
                        'type' => 1,
                    ));
                    //添加at记录
                    M('topic_at')->add(array(
                        'uid' => $topic_info['uid'],
                        'tid' => $forward_tid,
                    ));
                    //提示被提到的用户
                    D('user_msgtip')->add_tip($topic_info['uid'], 2);
                    //转发分享钩子
                    $tag_arg = array('uid'=>$this->visitor->info['id'], 'uname'=>$this->visitor->info['username'], 'action'=>'fwitem');
                    tag('fwitem_end', $tag_arg);
                    $this->ajaxReturn(1, L('forward_success'));
                } else {
                    $this->ajaxReturn(0, L('forward_failed'));
                }
            } else {
                $this->ajaxReturn(0, L('forward_failed'));
            }
        } else {
            $tid = $this->_get('tid', 'intval');
            !$tid && $this->ajaxReturn(0, L('forward_invalid_topic'));
            $topic = M('topic')->field('id,content')->find($tid);
            $this->assign('topic', $topic);
            $resp = $this->fetch('dialog:topic_forward');
            $this->ajaxReturn(1, '', $resp);
        }
    }

    /**
     * 评论微博
     */
    public function comment() {
        if (IS_POST) {
            foreach ($_POST as $key=>$val) {
                $_POST[$key] = Input::deleteHtmlTags($val);
            }
            $tid = $this->_post('tid', 'intval');
            $content = $this->_post('content', 'trim');
            if ($content == '') {
                $this->ajaxReturn(0, L('please_input_comment_info'));
            }
            $topic_mod = M('topic');
            //微薄是否有效
            $topic_info = $topic_mod->field('id,uid')->find($tid);
            !$topic_info && $this->ajaxReturn(0, L('comment_invalid_topic'));
            $data = array(
                'uid' => $this->visitor->info['id'],
                'uname' => $this->visitor->info['username'],
                'tid' => $tid,
                'content' => $content,
                'author_uid' => $topic_info['uid'],
            );
            if (D('topic_comment')->publish($data)) {
                $this->ajaxReturn(1, L('comment_success'));
            } else {
                $this->ajaxReturn(0, L('comment_failed'));
            }
        } else {
            $tid = $this->_get('tid', 'intval');
            !$tid && $this->ajaxReturn(0, L('comment_invalid_topic'));
            $topic_comment_mod = M('topic_comment');
            $map = array('tid'=>$tid);
            $pagesize = 8;
            $count = $topic_comment_mod->where($map)->count('id');
            $pager = $this->_pager($count, $pagesize);
            $comment_list = M('topic_comment')->where($map)->order('id DESC')->limit($pager->firstRow.','.$pager->listRows)->select();
            $this->assign('comment_list', $comment_list);
            $this->assign('page_bar', $pager->fshow());
            $resp = $this->fetch('space:cmt_list');
            $this->ajaxReturn(1, '', $resp);
        }
    }

    /**
     * 删除微薄评论
     */
    public function comment_del() {
        $cid = $this->_get('cid', 'intval');
        !$cid && $this->ajaxReturn(0, L('please_select_comment'));
        $topic_comment_mod = M('topic_comment');
        $tid = $topic_comment_mod->where(array('id'=>$cid))->getField('tid');
        if ($topic_comment_mod->where(array('id'=>$cid, 'uid'=>$this->visitor->info['id']))->delete()) {
            M('topic')->where(array('id'=>$tid))->setDec('comments');
            $this->ajaxReturn(1, L('delete_comment_success'));
        } else {
            $this->ajaxReturn(0, L('delete_comment_failed'));
        }
    }

    /**
     * 删除微博
     */
    public function delete() {
        $tid = $this->_get('tid', 'intval');
        !$tid && $this->ajaxReturn(0, L('please_select_delete_topic'));
        if (M('topic')->where(array('id'=>$tid, 'uid'=>$this->visitor->info['id']))->delete()) {
            $this->ajaxReturn(1, L('delete_topic_success'));
        } else {
            $this->ajaxReturn(0, L('delete_topic_failed'));
        }
    }
}