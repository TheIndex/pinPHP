<?php

class spaceAction extends frontendAction {

    private $_user = null;

    public function _initialize() {
        parent::_initialize();
        $uid = $this->_get('uid', 'intval');
        if ($uid) {
            $user = M('user')->find($uid);
            //ship   0:互相未关注 1:我关注对方 2:互相关注
            $user['ship'] = 0;
            if ($this->visitor->is_login) {
                $ship = M('user_follow')->where(array('uid' => $this->visitor->info['id'], 'follow_uid' => $user['id']))->find();
                if ($ship) {
                    $user['ship'] = $ship['mutually'] ? 2 : 1;
                }
            }
            $this->_user = $user;
        } elseif (!$uid && $this->visitor->is_login) {
            $this->_user = $this->visitor->get();
        } else {
            $this->_404();
        }
        $this->assign('user', $this->_user);
        $this->_curr_nav(ACTION_NAME); //方法里面可以自定义
    }

    protected function _curr_nav($nav = 'index') {
        $nav_list = $this->_get_nav();
        $this->assign('space_nav_list', $nav_list);
        $this->assign('space_nav_curr', $nav);
    }

    private function _get_nav() {
        return array(
            'index' => array('text' => '封面', 'url' => U('space/index', array('uid' => $this->_user['id']))),
            'talk' => array('text' => '动态', 'url' => U('space/talk', array('uid' => $this->_user['id']))),
            'album' => array('text' => '专辑', 'url' => U('space/album', array('uid' => $this->_user['id']))),
            'item' => array('text' => '分享', 'url' => U('space/item', array('uid' => $this->_user['id']))),
            'like' => array('text' => '喜欢', 'url' => U('space/like', array('uid' => $this->_user['id'])))
        );
    }

    /**
     * 封面
     */
    public function index() {
        //用户的专辑
        $album_list = M('album')->field('id,title,items,cover_cache')->where(array('uid' => $this->_user['id'], 'status' => 1))->limit('6')->order('id DESC')->select();
        foreach ($album_list as $key => $val) {
            $album_list[$key]['cover'] = unserialize($val['cover_cache']);
        }
        $this->assign('album_list', $album_list);
        //用户的分享
        $where = array('uid' => $this->_user['id']);
        $field = 'id,uid,title,intro,img,price,likes,comments';
        $this->waterfall($where, 'id DESC', $field);
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_home_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 分享
     */
    public function item() {
        $sort = $this->_get('sort', 'trim', 'new');
        switch ($sort) {
            case 'hot':
                $order = 'likes DESC';
                break;
            case 'new':
                $order = 'id DESC';
                break;
        }
        $where = array('uid' => $this->_user['id']);
        $field = 'id,uid,title,intro,img,price,likes,comments';
        if (IS_AJAX) {
            $this->wall_ajax($where, $order, $field);
        } else {
            $this->waterfall($where, $order, $field);
            $this->assign('sort', $sort);
            $this->_config_seo(array(
                'title' => $this->_user['username'] . L('space_item_title') . '-' . C('pin_site_name'),
            ));
            $this->display();
        }
    }

    /**
     * 喜欢
     */
    public function like() {
        $db_pre = C('DB_PREFIX');
        $spage_size = C('pin_wall_spage_size');
        $spage_max = C('pin_wall_spage_max');
        $page_size = $spage_size * $spage_max;

        $item_like_mod = M('item_like');
        $where = array(
            $db_pre . 'item_like.uid' => $this->_user['id'],
            'i.status' => 1,
        );
        $field = 'i.id,i.uid,i.uname,i.title,i.price,i.intro,i.img,i.likes,i.comments';
        $order = $db_pre . 'item_like.add_time DESC';
        $join = $db_pre . 'item i ON i.id=' . $db_pre . 'item_like.item_id';

        $count = $item_like_mod->join($join)->where($where)->count();
        $pager = $this->_pager($count, $page_size);
        $item_list = $item_like_mod->field($field)->join($join)->where($where)->order($order)->limit($pager->firstRow . ',' . $spage_size)->select();
        $this->assign('item_list', $item_list);
        //当前页码
        $p = $this->_get('p', 'intval', 1);
        $this->assign('p', $p);
        //当前页面总数大于单次加载数才会执行动态加载
        if (($count - ($p - 1) * $page_size) > $spage_size) {
            $this->assign('show_load', 1);
        }
        //总数大于单页数才显示分页
        $count > $page_size && $this->assign('page_bar', $pager->fshow());
        //最后一页分页处理
        if ((count($item_list) + $page_size * ($p - 1)) == $count) {
            $this->assign('show_page', 1);
        }
        $this->assign('like_manage', true);
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_like_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    public function like_wall() {
        $db_pre = C('DB_PREFIX');
        $spage_size = C('pin_wall_spage_size'); //每次加载个数
        $spage_max = C('pin_wall_spage_max'); //加载次数
        $p = $this->_get('p', 'intval', 1); //页码
        $sp = $this->_get('sp', 'intval', 1); //子页

        $item_like_mod = M('item_like');
        //条件
        $where = array(
            $db_pre . 'item_like.uid' => $this->_user['id'],
            'i.status' => 1,
        );
        //计算开始
        $start = $spage_size * ($spage_max * ($p - 1) + $sp);
        //
        $field = 'i.id,i.uid,i.uname,i.title,i.price,i.intro,i.img,i.likes,i.comments';
        $order = $db_pre . 'item_like.add_time DESC';
        $join = $db_pre . 'item i ON i.id=' . $db_pre . 'item_like.item_id';
        $item_list = $item_like_mod->field($field)->join($join)->where($where)->order($order)->limit($start . ',' . $spage_size)->select();

        $this->assign('item_list', $item_list);
        $this->assign('like_manage', true);
        $resp = $this->fetch('public:waterfall');
        $data = array(
            'isfull' => 1,
            'html' => $resp,
        );
        $count <= $start + $spage_size && $data['isfull'] = 0;
        $this->ajaxReturn(1, '', $data);
    }

    /**
     * 微薄内容
     */
    public function feed_src() {
        $src_id = $this->_get('src_id', 'intval');
        $src_type = $this->_get('src_type', 'intval', 0);
        switch ($src_type) {
            case 0 :
                $item_mod = M('item');
                $item = $item_mod->field('id,title,img,url,price')->find($src_id);
                $this->assign('item', $item);
                break;
        }
        $resp = $this->fetch('feed_src_item');
        $this->ajaxReturn(1, '', $resp);
    }

    /**
     * 用户发表的
     */
    public function talk() {
        $topic_mod = M('topic');
        $map = array('uid' => $this->_user['id']);
        $type = $this->_get('type', 'intval', 0);
        if ($type) {
            $map['type'] = 0;
        }
        $pagesize = 20;
        $count = $topic_mod->where($map)->count('id');
        $pager = $this->_pager($count, $pagesize);
        $topic_list = $topic_mod->where($map)->order('id DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        foreach ($topic_list as $key => $val) {
            if ($val['type'] == '1') {
                $src_tid = M('topic_relation')->where(array('tid' => $val['id']))->getField('src_tid');
                $topic_list[$key]['qt'] = $topic_mod->find($src_tid);
            }
        }
        $this->assign('topic_list', $topic_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'talk');
        $this->_curr_nav('talk');
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_feed_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 我自己的动态主页
     */
    public function me() {
        !$this->visitor->is_login && $this->redirect('user/login');
        //获取动态索引
        $topic_index_mod = M('topic_index');
        $map = array('uid' => $this->_user['id']);
        $pagesize = 20;
        $count = $topic_index_mod->where($map)->count();
        $pager = $this->_pager($count, $pagesize);
        $tid_res = $topic_index_mod->field('tid')->where($map)->order('tid DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $tid_arr = array();
        foreach ($tid_res as $val) {
            $tid_arr[] = $val['tid'];
        }
        $topic_list = array();
        //获取动态内容
        if ($tid_arr) {
            $topic_mod = M('topic');
            $topic_list = $topic_mod->where(array('id' => array('in', $tid_arr)))->order('id DESC')->select();
        }
        $this->assign('topic_list', $topic_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'me');
        $this->_curr_nav('talk');
        $this->_config_seo(array(
            'title' => L('space_me_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * @我的
     */
    public function atme() {
        !$this->visitor->is_login && $this->redirect('user/login');
        $topic_at_mod = M('topic_at');
        $map = array('uid' => $this->_user['id']);
        $pagesize = 20;
        $count = $topic_at_mod->where($map)->count();
        $pager = $this->_pager($count, $pagesize);
        $tid_res = $topic_at_mod->field('tid')->where($map)->order('tid DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $tid_arr = array();
        foreach ($tid_res as $val) {
            $tid_arr[] = $val['tid'];
        }
        $topic_list = array();
        //获取动态内容
        if ($tid_arr) {
            $topic_mod = M('topic');
            $topic_list = $topic_mod->where(array('id' => array('in', $tid_arr)))->order('id DESC')->select();
        }
        foreach ($topic_list as $key => $val) {
            if ($val['type'] == '1') {
                $src_tid = M('topic_relation')->where(array('tid' => $val['id']))->getField('src_tid');
                $topic_list[$key]['qt'] = $topic_mod->find($src_tid);
            }
        }
        //清理提醒
        D('user_msgtip')->clear_tip($this->_user['id'], 2);
        $this->assign('topic_list', $topic_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'atme');
        $this->_curr_nav('talk');
        $this->_config_seo(array(
            'title' => L('space_me_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 评论我的
     */
    public function cmtme() {
        !$this->visitor->is_login && $this->redirect('user/login');
        $topic_mod = M('topic');
        $topic_comment_mod = M('topic_comment');
        $pagesize = 20;
        $map = array('author_uid' => $this->_user['id']);
        $count = $topic_comment_mod->where($map)->count('id');
        $pager = $this->_pager($count, $pagesize);
        $cmt_list = $topic_comment_mod->where($map)->order('id DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        foreach ($cmt_list as $key => $val) {
            $cmt_list[$key]['topic'] = $topic_mod->where(array('id' => $val['tid']))->getField('content');
        }
        $this->assign('cmt_list', $cmt_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'cmtme');
        $this->_curr_nav('talk');
        $this->_config_seo(array(
            'title' => L('space_me_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 专辑
     */
    public function album() {
        $sort = $this->_get('sort', 'trim', 'new');
        $type = $this->_get('type', 'trim', 'pub');
        $pagesize = 20;
        if ($type == 'followed') {
            $db_pre = C('DB_PREFIX');
            $album_follow_mod = M('album_follow');
            $count_map = array('uid' => $this->_user['id']);
            $map = array($db_pre.'album_follow.uid' => $this->_user['id']);
            $sort_order = ($sort == 'hot') ? 'a.follows DESC' : 'a.id DESC';
            $count = $album_follow_mod->where($count_map)->count();
            $pager = $this->_pager($count, $pagesize);
            //left join
            $album_list = $album_follow_mod->field('a.id,a.title,a.items,a.cover_cache')->join($db_pre . 'album a ON a.id=' . $db_pre . 'album_follow.album_id')->where($map)->order($sort_order)->limit($pager->firstRow . ',' . $pager->listRows)->select();
        } else {
            $album_mod = M('album');
            $map = array('uid' => $this->_user['id'], 'status' => 1);
            $sort_order = ($sort == 'hot') ? 'follows DESC' : 'id DESC';
            $count = $album_mod->where($map)->count('id');
            $pager = $this->_pager($count, $pagesize);
            $album_list = $album_mod->field('id,title,items,cover_cache')->where($map)->order($sort_order)->limit($pager->firstRow . ',' . $pager->listRows)->select();
        }
        foreach ($album_list as $key => $val) {
            $album_list[$key]['cover'] = unserialize($val['cover_cache']);
        }
        $this->assign('album_list', $album_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('sort', $sort);
        $this->assign('type', $type);
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_album_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 个人信息
     */
    public function info() {
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_info_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 名片
     */
    public function card() {
        $this->assign('user', $this->_user);
        $is_follow = '0';
        if ($this->visitor->is_login && M('user_follow')->where(array('uid' => $this->visitor->info['id'], 'follow_uid' => $this->_user['id']))->count()) {
            $is_follow = '1';
        }
        $this->assign('is_follow', $is_follow);
        $resp = $this->fetch('public:user_card');
        $this->ajaxReturn(1, '', $resp);
    }

    /**
     * 关注
     */
    public function follow() {
        $user_follow_mod = M('user_follow');
        $db_pre = C('DB_PREFIX');
        $uf_table = $db_pre . 'user_follow';
        $pagesize = 20;
        $count = $user_follow_mod->where(array('uid' => $this->_user['id']))->count();
        $pager = $this->_pager($count, $pagesize);
        $where = array($uf_table . '.uid' => $this->_user['id']);
        $field = 'u.id,u.username,u.fans,u.last_time,' . $uf_table . '.add_time,' . $uf_table . '.mutually';
        $join = $db_pre . 'user u ON u.id=' . $uf_table . '.follow_uid';
        $user_list = $user_follow_mod->field($field)->where($where)->join($join)->order($uf_table . '.add_time DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        $this->assign('user_list', $user_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'follow');
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_follow_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

    /**
     * 粉丝
     */
    public function fans() {
        $user_follow_mod = M('user_follow');
        $db_pre = C('DB_PREFIX');
        $uf_table = $db_pre . 'user_follow';
        $pagesize = 20;
        $count = $user_follow_mod->where(array('follow_uid' => $this->_user['id']))->count();
        $pager = $this->_pager($count, $pagesize);
        $where = array($uf_table . '.follow_uid' => $this->_user['id']);
        $field = 'u.id,u.username,u.fans,u.last_time,' . $uf_table . '.add_time,' . $uf_table . '.mutually';
        $join = $db_pre . 'user u ON u.id=' . $uf_table . '.uid';
        $user_list = $user_follow_mod->field($field)->where($where)->join($join)->order($uf_table . '.add_time DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
        if ($this->visitor->is_login) {
            D('user_msgtip')->clear_tip($this->visitor->info['id'], 1);
        }
        $this->assign('user_list', $user_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('tab_current', 'fans');
        $this->_config_seo(array(
            'title' => $this->_user['username'] . L('space_fans_title') . '-' . C('pin_site_name'),
        ));
        $this->display();
    }

}