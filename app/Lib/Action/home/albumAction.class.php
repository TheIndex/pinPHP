<?php
class albumAction extends frontendAction {

    public function _initialize() {
        parent::_initialize();
        //访问者控制
        if (!$this->visitor->is_login && in_array(ACTION_NAME, array('create_album','edit_album','delete_album','album_upload_banner','join','follow','unfollow','comment'))) {
            IS_AJAX && $this->ajaxReturn(0, L('login_please'));
            $this->redirect('user/login');
        }
        $this->assign('nav_curr', 'album');
    }

    /**
     * 专辑首页
     */
    public function index() {
        $cid = $this->_get('cid', 'intval');
        $sort = $this->_get('sort', 'trim', 'hot');
        switch ($sort) {
            case 'hot':
                $sort_order = 'follows DESC,id DESC';
                break;
            case 'new':
                $sort_order = 'id DESC';
                break;
        }
        $album_mod = M('album');
        $pagesize = 39;
        $where = array('status'=>'1');
        if ($cid) {
            $where['cate_id'] = $cid;
            $cate_info = D('album_cate')->get_info($cid);
            $this->_config_seo(C('pin_seo_config.album_cate'), array(
                'cate_name' => $cate_info['name'],
                'seo_title' => $cate_info['seo_title'],
                'seo_keywords' => $cate_info['seo_keys'],
                'seo_description' => $cate_info['seo_desc'],
            ));
        } else {
            $this->_config_seo(C('pin_seo_config.album'));
        }
        $count = $album_mod->where($where)->count('id');
        $pager = $this->_pager($count, $pagesize);
        $album_list = $album_mod->field('id,uid,uname,title,cover_cache')->where($where)->order($sort_order)->limit($pager->firstRow.','.$pager->listRows)->select();
        foreach ($album_list as $key=>$val) {
            $album_list[$key]['cover'] = unserialize($val['cover_cache']);
        }
        $this->assign('album_list', $album_list);
        $this->assign('page_bar', $pager->fshow());
        $this->assign('sort', $sort);
        $this->assign('cate_info', $cate_info);
        $this->display();
    }

    /**
     * 专辑详细
     */
    public function detail() {
        $id = $this->_get('id', 'intval');
        $album_mod = M('album');
        $album = $album_mod->where(array('id'=>$id, 'status'=>'1'))->find();
        !$album && $this->_404();

        //分类信息
        $cate_info = D('album_cate')->get_info($album['cate_id']);

        if ($this->visitor->is_login && M('album_follow')->where(array('uid'=>$this->visitor->info['id'], 'album_id'=>$id))->count()) {
            $this->assign('is_followed', true);
        }

        //ship   0:互相未关注 1:我关注对方 2:互相关注
        $author_ship = 0;
        if ($this->visitor->is_login) {
            $ship = M('user_follow')->where(array('uid'=>$this->visitor->info['id'], 'follow_uid'=>$album['uid']))->find();
            if ($ship) {
                $author_ship = $ship['mutually'] ? 2 : 1;
            }
        }

        $db_pre = C('DB_PREFIX');
        $ai_table = $db_pre.'album_item';
        //专辑下的商品
        $spage_size = C('pin_wall_spage_size'); //每次加载个数
        $spage_max = C('pin_wall_spage_max'); //加载次数
        $page_size = $spage_size * $spage_max; //每页显示个数
        $album_item_mod = M('album_item');
        $where = array($ai_table.'.album_id'=>$id,'i.status'=>'1');
        $join = $db_pre.'item i ON i.id='.$ai_table.'.item_id';
        $count = $album_item_mod->join($join)->where($where)->count();
        $pager = $this->_pager($count, $page_size);
        $field = $ai_table.'.intro,i.id,i.uid,i.title,i.img,i.price,i.likes,i.comments';
        $order = $ai_table.'.add_time DESC';
        $item_list = $album_item_mod->field($field)->join($join)->where($where)->order($order)->limit($pager->firstRow.','.$spage_size)->select();
        foreach ($item_list as $key=>$val) {
            isset($val['comments_cache']) && $item_list[$key]['comment_list'] = unserialize($val['comments_cache']);
        }
        $this->assign('item_list', $item_list);
        //当前页码
        $p = $this->_get('p', 'intval', 1);
        $this->assign('p', $p);
        //当前页面总数大于单次加载数才会执行动态加载
        if (($count - ($p-1) * $page_size) > $spage_size) {
            $this->assign('show_load', 1);
        }
        //总数大于单页数才显示分页
        $count > $page_size && $this->assign('page_bar', $pager->fshow());
        //最后一页分页处理
        if ((count($item_list) + $page_size * ($p-1)) == $count) {
            $this->assign('show_page', 1);
        }

        //更新专辑商品数
        $album_mod->where(array('id'=>$id))->setField('items', $count);
        $album['items'] = $count;

        //相关专辑(本分类下的最新两个)
        $album_guess = $album_mod->where(array('cate_id'=>$album['cate_id'], 'id'=>array('neq', $id)))->order('id DESC')->limit(2)->select();
        foreach ($album_guess as $key=>$val) {
            $album_guess[$key]['cover'] = unserialize($val['cover_cache']);
        }

        //第一页评论不使用AJAX
        $album_comment_mod = M('album_comment');
        $pagesize = 8;
        $map = array('album_id'=>$id);
        $count = $album_comment_mod->where($map)->count('id');
        $cmt_pager = $this->_pager($count, $pagesize);
        $cmt_pager->path = 'comment_list';
        $cmt_pager_bar = $cmt_pager->fshow();
        $cmt_list = $album_comment_mod->where($map)->order('id DESC')->limit($cmt_pager->firstRow.','.$cmt_pager->listRows)->select();

        $this->assign('album', $album);
        $this->assign('author_ship', $author_ship);
        $this->assign('album_guess', $album_guess);
        $this->assign('album_manage', true);
        $this->assign('cmt_list', $cmt_list);
        $this->assign('cmt_page_bar', $cmt_pager_bar);
        // SEO
        $this->_config_seo(C('pin_seo_config.album_detail'), array(
            'album_title' => $album['title'],
            'album_intro' => $album['intro'],
            'album_cate' => $cate_info['name'],
        ));
        $this->display();
    }

    public function detail_ajax() {
        $db_pre = C('DB_PREFIX');
        $ai_table = $db_pre.'album_item';
        $spage_size = C('pin_wall_spage_size'); //每次加载个数
        $spage_max = C('pin_wall_spage_max'); //加载次数
        $p = $this->_get('p', 'intval', 1); //页码
        $sp = $this->_get('sp', 'intval', 1); //子页
        $id = $this->_get('id', 'intval');

        $album_item_mod = M('album_item');
        $start = $spage_size * ($spage_max * ($p - 1) + $sp); //计算开始
        $where = array($ai_table.'.album_id'=>$id, 'i.status'=>'1');
        $join = $db_pre.'item i ON i.id='.$ai_table.'.item_id';
        $order = $ai_table.'.add_time DESC';
        $count = $album_item_mod->join($join)->where($where)->count();
        $item_list = $album_item_mod->field($field)->join($join)->where($where)->order($order)->limit($start.','.$spage_size)->select();
        foreach ($item_list as $key=>$val) {
            isset($val['comments_cache']) && $item_list[$key]['comment_list'] = unserialize($val['comments_cache']);
        }
        $this->assign('item_list', $item_list);
        $this->assign('album_manage', true);
        $resp = $this->fetch('public:waterfall');
        $data = array(
            'isfull' => 1,
            'html' => $resp,
        );
        $count <= $start + $spage_size && $data['isfull'] = 0;
        $this->ajaxReturn(1, '', $data);
    }

    //创建专辑
    public function create_album() {
        if (IS_POST) {
            foreach ($_POST as $key=>$val) {
                $_POST[$key] = Input::deleteHtmlTags($val);
            }
            $album_mod = D('album');
            $data['title'] = $this->_post('title', 'trim');
            $data['intro'] = $this->_post('intro', 'trim');
            $data['cate_id'] = $this->_post('cate_id', 'intval');
            $data['banner'] = $this->_post('banner', 'trim');
            $data['uid'] = $this->visitor->info['id'];
            $data['uname'] = $this->visitor->info['username'];
            !$data['title'] && $this->ajaxReturn(0, L('please_input').L('album_title'));

            $badword_mod = D('badword');
            //检测敏感词--标题
            $check_result = $badword_mod->check($data['title']);
            switch ($check_result['code']) {
                case 1: //禁用。直接返回
                    $this->ajaxReturn(0, L('has_badword'));
                    break;
                case 3: //需要审核
                    $data['status'] = 0;
                    break;
            }
            $data['title'] = $check_result['content'];
            if ($data['intro']) {
                //检测敏感词--说明
                $check_result = $badword_mod->check($data['intro']);
                switch ($check_result['code']) {
                    case 1: //禁用。直接返回
                        $this->ajaxReturn(0, L('has_badword'));
                        break;
                    case 3: //需要审核
                        $data['status'] = 0;
                        break;
                }
                $data['intro'] = $check_result['content'];
            }
            if (false ===  $album_mod->create($data)) {
                $this->ajaxReturn(0, $album_mod->getError());
            }
            if (false === $result = $album_mod->add()) {
                $this->ajaxReturn(0, L('create_album_failed'), $result);
            } else {
                //增加用户专辑数
                M('user')->where(array('id'=>$data['uid']))->setInc('albums');
                $this->ajaxReturn(1, L('create_album_success'), $result);
            }
        } else {
            //专辑分类
            $cate_list = M('album_cate')->field('id,name')->select();
            $this->assign('cate_list', $cate_list);
            $resp = $this->fetch('dialog:create_album');
            $this->ajaxReturn(1, '', $resp);
        }
    }

    //修改专辑
    public function edit_album() {
        if (IS_POST) {
            foreach ($_POST as $key=>$val) {
                $_POST[$key] = Input::deleteHtmlTags($val);
            }
            $album_mod = D('album');
            $data['id'] = $this->_post('id', 'intval');
            $data['title'] = $this->_post('title', 'trim');
            $data['cate_id'] = $this->_post('cate_id', 'intval');
            $data['intro'] = $this->_post('intro', 'trim');
            $data['banner'] = $this->_post('banner', 'trim');
            !$data['title'] && $this->ajaxReturn(0, L('please_input').L('album_title'));

            $badword_mod = D('badword');
            //检测敏感词--标题
            $check_result = $badword_mod->check($data['title']);
            switch ($check_result['code']) {
                case 1: //禁用。直接返回
                    $this->ajaxReturn(0, L('has_badword'));
                    break;
                case 3: //需要审核
                    $data['status'] = 0;
                    break;
            }
            $data['title'] = $check_result['content'];
            //检测敏感词--说明
            $check_result = $badword_mod->check($data['intro']);
            switch ($check_result['code']) {
                case 1: //禁用。直接返回
                    $this->ajaxReturn(0, L('has_badword'));
                    break;
                case 3: //需要审核
                    $data['status'] = 0;
                    break;
            }
            $data['intro'] = $check_result['content'];

            if (!$album_mod->create($data)) {
                $this->ajaxReturn(0, $album_mod->getError());
            }
            if (!$album_mod->where(array('id'=>$data['id'], 'uid'=>$this->visitor->info['id']))->save()) {
                $this->ajaxReturn(0, L('edit_album_failed'));
            } else {
                $this->ajaxReturn(1, L('edit_album_success'));
            }
        } else {
            $aid = $this->_get('aid', 'intval');
            !$aid && $this->ajaxReturn(0, L('illegal_parameters'));
            $info = M('album')->field('id,cate_id,title,intro,banner')->where(array('uid'=>$this->visitor->info['id'], 'id'=>$aid))->find();
            !$info && $this->ajaxReturn(0, L('invalid_album'));
            $this->assign('info', $info);
            //专辑分类
            $cate_list = M('album_cate')->field('id,name')->select();
            $this->assign('cate_list', $cate_list);
            $resp = $this->fetch('dialog:edit_album');
            $this->ajaxReturn(1, '', $resp);
        }
    }

    //删除专辑
    public function delete_album() {
        $aid = $this->_get('aid', 'intval');
        !$aid && $this->ajaxReturn(0, L('illegal_parameters'));
        D('album')->where(array('uid'=>$this->visitor->info['id'], 'id'=>$aid))->delete();
        $this->ajaxReturn(1, L('del_album_success'));
    }

    //上传封面图片
    public function album_upload_banner() {
        //上传图片
        if (!empty($_FILES['banner']['name'])) {
            $data_dir = date('ym/d');
            $result = $this->_upload($_FILES['banner'], 'album/' . $data_dir, array('width'=>'960', 'height'=>'130'));
            if ($result['error']) {
                $this->ajaxReturn(0, $result['info']);
            } else {
                $ext = array_pop(explode('.', $result['info'][0]['savename']));
                $data['banner'] = $data_dir . '/' . str_replace('.' . $ext, '_thumb.' . $ext, $result['info'][0]['savename']);
                $data['src'] = './data/upload/album/' . $data['banner'];
                $this->ajaxReturn(1, '上传成功', $data);
            }
        } else {
            $this->ajaxReturn(0, L('illegal_parameters'));
        }
    }

    /**
     * 添加到专辑
     */
    public function join() {
        if (IS_POST) {
            foreach ($_POST as $key=>$val) {
                $_POST[$key] = Input::deleteHtmlTags($val);
            }
            $album_id = $this->_post('album_id', 'intval', 0);
            $ac_id = $this->_post('ac_id', 'intval', 0);
            $item_id = $this->_post('item_id', 'intval', 0);
            $intro = $this->_post('intro', 'trim', '');
            !$item_id && $this->ajaxReturn(0, L('illegal_parameters'));
            //敏感词处理
            $check_result = D('badword')->check($intro);
            if ($check_result['code'] == 1) {
                $this->ajaxReturn(0, L('has_badword'));
            }
            $intro = $check_result['content'];

            $album_mod = D('album');
            $album_item_mod = M('album_item');
            //处理默认专辑
            !$album_id && $album_id = $album_mod->default_album(array(
                'id' => $this->visitor->info['id'],
                'name' => $this->visitor->info['username'],
            ), $ac_id);
            //是否已经添加过
            if ($album_item_mod->where(array('item_id'=>$item_id, 'album_id'=>$album_id))->count()) {
                $this->ajaxReturn(0, L('item_was_in_album'));
            }
            //处理添加
            if ($album_mod->add_item($item_id, $album_id, $intro)) {
                //添加到专辑钩子
                $tag_arg = array('uid'=>$this->visitor->info['id'], 'uname'=>$this->visitor->info['username'], 'action'=>'joinalbum');
                tag('joinalbum_end', $tag_arg);
                $this->ajaxReturn(1, L('join_album_success'));
            } else {
                $this->ajaxReturn(0, L('join_album_failed'));
            }
        } else {
            $id = $this->_get('id', 'intval', 0);
            !$id && $this->ajaxReturn(0, L('illegal_parameters'));
            $item_mod = M('item');
            $item = $item_mod->field('id,title,intro,img')->where(array('id'=>$id, 'status'=>'1'))->find();
            !$item && $this->ajaxReturn(0, L('invalid_item'));
            //获取用户的专辑
            $album_list = M('album')->field('id,title')->where(array('uid'=>$this->visitor->info['id']))->select();
            //专辑分类
            if (false === $album_cate_list = F('album_cate_list')) {
                $album_cate_list = D('album_cate')->cate_cache();
            }
            $this->assign('album_cate_list', $album_cate_list);
            $this->assign('album_list', $album_list);
            $this->assign('item', $item);
            $resp = $this->fetch('dialog:join_album');
            $this->ajaxReturn(1, '', $resp);
        }
    }

    /**
     * 关注专辑
     */
    public function follow() {
        $aid = $this->_get('aid', 'intval');
        !$aid && $this->ajaxReturn(0, L('illegal_parameters'));
        $uid = $this->visitor->info['id'];
        $album_mod = M('album');
        $album = $album_mod->field('id,uid')->find($aid);
        !$album && $this->ajaxReturn(0, L('invalid_album'));
        //是否自己的？
        $album['uid'] == $uid && $this->ajaxReturn(0, L('follow_own_album'));
        //是否关注过？
        $album_follow_mod = M('album_follow');
        $is_followed = $album_follow_mod->where(array('uid'=>$uid, 'album_id'=>$aid))->count();
        $is_followed && $this->ajaxReturn(0, L('you_was_followed'));
        if ($album_follow_mod->add(array('uid'=>$uid, 'album_id'=>$aid, 'add_time'=>time()))) {
            $album_mod->where(array('id'=>$aid))->setInc('follows'); //关注数
            $this->ajaxReturn(1, L('follow_album_success'));
        } else {
            $this->ajaxReturn(0, L('follow_album_failed'));
        }
    }

    /**
     * 取消关注
     */
    public function unfollow() {
        $aid = $this->_get('aid', 'intval');
        !$aid && $this->ajaxReturn(0, L('illegal_parameters'));
        $uid = $this->visitor->info['id'];
        if (M('album_follow')->where(array('uid'=>$uid, 'album_id'=>$aid))->delete()) {
            M('album')->where(array('id'=>$aid))->setDec('follows'); //关注数
            $this->ajaxReturn(1, L('unfollow_album_success'));
        } else {
            $this->ajaxReturn(0, L('unfollow_album_failed'));
        }
    }

    /**
     * 评论列表
     */
    public function comment_list() {
        $id = $this->_get('id', 'intval');
        !$id && $this->ajaxReturn(0, L('invalid_album'));
        $album_mod = M('album');
        $album = $album_mod->where(array('id'=>$id, 'status'=>'1'))->count('id');
        !$album && $this->ajaxReturn(0, L('invalid_album'));
        $album_comment_mod = M('album_comment');
        $pagesize = 8;
        $map = array('item_id'=>$id);
        $count = $album_comment_mod->where($map)->count('id');
        $pager = $this->_pager($count, $pagesize);
        $pager->path = 'comment_list';
        $cmt_list = $album_comment_mod->where($map)->order('id DESC')->limit($pager->firstRow.','.$pager->listRows)->select();
        $this->assign('cmt_list', $cmt_list);
        $data = array();
        $data['list'] = $this->fetch('comment_list');
        $data['page'] = $pager->fshow();
        $this->ajaxReturn(1, '', $data);
    }

    /**
     * 发布评论
     */
    public function comment() {
        foreach ($_POST as $key=>$val) {
            $_POST[$key] = Input::deleteHtmlTags($val);
        }
        $data['album_id'] = $this->_post('id', 'intval');
        !$data['album_id'] && $this->ajaxReturn(0, L('invalid_album'));
        $data['info'] = $this->_post('content', 'trim');
        !$data['info'] && $this->ajaxReturn(0, L('please_input').L('comment_content'));
        //敏感词处理
        $check_result = D('badword')->check($data['info']);
        switch ($check_result['code']) {
            case 1: //禁用。直接返回
                $this->ajaxReturn(0, L('has_badword'));
                break;
            case 3: //需要审核
                $data['status'] = 0;
                break;
        }
        $data['info'] = $check_result['content'];
        $data['uid'] = $this->visitor->info['id'];
        $data['uname'] = $this->visitor->info['username'];
        $data['add_time'] = time();

        //验证商品
        $album_mod = M('album');
        $album = $album_mod->field('id,uid,uname')->where(array('id'=>$data['album_id'], 'status'=>'1'))->find();
        !$album && $this->ajaxReturn(0, L('invalid_album'));
        //写入评论
        $album_comment_mod = D('album_comment');
        if ($comment_id = $album_comment_mod->add($data)) {
            $this->assign('cmt_list', array(
                array(
                    'uid' => $data['uid'],
                    'uname' => $data['uname'],
                    'info' => $data['info'],
                    'add_time' => $data['add_time'],
                )
            ));
            $resp = $this->fetch('comment_list');
            $this->ajaxReturn(1, L('comment_success'), $resp);
        } else {
            $this->ajaxReturn(0, L('comment_failed'));
        }
    }
}