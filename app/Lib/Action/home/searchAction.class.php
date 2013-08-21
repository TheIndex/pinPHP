<?php

/**
 * 搜索页面
 */
class searchAction extends frontendAction {

    public function index() {
        $q = $this->_get('q', 'trim');
        $t = $this->_get('t', 'trim', 'item');
        $action = '_search_' . $t;
        $this->$action($q);
        //搜索记录
        $search_history = (array) cookie('search_history');
        if (!$search_history) {
            $q && $search_history = array(array('q' => urlencode($q), 't' => $t));
        } else {
            foreach ($search_history as $key => $val) {
                $search_history[$key] = $val = (array) $val;
                if ($val['q'] == urlencode($q) && $val['t'] == $t) {
                    unset($search_history[$key]);
                }
            }
            $q && array_unshift($search_history, array('q' => urlencode($q), 't' => $t));
            $search_history = array_slice($search_history, 0, 10, true);
        }
        cookie('search_history', $search_history);
        $this->assign('q', $q);
        $this->assign('t', $t);
        $this->assign('search_history', $search_history);
        $this->display($t);
    }

    public function clear_history() {
        cookie('search_history', NULL);
        $this->redirect('search/index');
    }

    /**
     * 搜宝贝
     */
    private function _search_item($q) {
        $sort = $this->_get('sort', 'trim', 'hot'); //排序
        if ($q) {
            $where = array('status' => '1');
            $where['intro'] = array('like', '%' . $q . '%');
            switch ($sort) {
                case 'hot':
                    $order = 'hits DESC,id DESC';
                    break;
                case 'new':
                    $order = 'id DESC';
                    break;
            }
            IS_AJAX && $this->wall_ajax($where, $order);
            $this->waterfall($where, $order);
        }
        $this->assign('sort', $sort);
        $this->assign('nav_curr', 'book');
        $this->_config_seo(array(
            'title' => sprintf(L('search_item_title'), $q) . '-' . C('pin_site_name'),
        ));
    }

    /**
     * 搜专辑
     */
    private function _search_album($q) {
        $sort = $this->_get('sort', 'trim', 'hot'); //排序
        if ($q) {
            $album_mod = M('album');
            $pagesize = 39;
            $where = array('status' => '1');
            $where['title'] = array('like', '%' . $q . '%');
            switch ($sort) {
                case 'hot':
                    $order = 'follows DESC,id DESC';
                    break;
                case 'new':
                    $order = 'id DESC';
                    break;
            }
            $count = $album_mod->where($where)->count('id');
            $pager = $this->_pager($count, $pagesize);
            $album_list = $album_mod->field('id,uid,uname,title,cover_cache')->where($where)->order($order)->limit($pager->firstRow . ',' . $pager->listRows)->select();
            foreach ($album_list as $key => $val) {
                $album_list[$key]['cover'] = unserialize($val['cover_cache']);
            }
            $this->assign('album_list', $album_list);
            $this->assign('page_bar', $pager->fshow());
        }
        $this->assign('sort', $sort);
        $this->assign('nav_curr', 'album');
        $this->_config_seo(array(
            'title' => sprintf(L('search_album_title'), $q) . '-' . C('pin_site_name'),
        ));
    }

    /**
     * 搜用户
     */
    private function _search_user($q) {
        if ($q) {
            $user_mod = M('user');
            $where = array('status' => '1');
            $where['username'] = array('like', '%' . $q . '%');
            $count = $user_mod->where($where)->count('id');
            $pager = $this->_pager($count, $pagesize);
            $user_list = $user_mod->field('id,username,province,city,fans,tags,intro')->where($where)->order('id DESC')->limit($pager->firstRow . ',' . $pager->listRows)->select();
            $this->assign('count', $count);
            $this->assign('user_list', $user_list);
            $this->assign('page_bar', $pager->fshow());
        }
         $this->_config_seo(array(
            'title' => sprintf(L('search_user_title'), $q) . '-' . C('pin_site_name'),
        ));
    }

}