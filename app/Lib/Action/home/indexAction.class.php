<?php
class indexAction extends frontendAction {
    
    public function index() {
        //分类
        if (false === $index_cate_list = F('index_cate_list')) {
            $item_cate_mod = M('item_cate');
            //分类关系
            if (false === $cate_relate = F('cate_relate')) {
                $cate_relate = D('item_cate')->relate_cache();
            }
            //分类缓存
            if (false === $cate_data = F('cate_data')) {
                $cate_data = D('item_cate')->cate_data_cache();
            }
            //推荐到首页的大类
            $index_cate_list = $item_cate_mod->field('id,name,img')->where(array('pid'=>'0' ,'is_index'=>'1', 'status'=>'1'))->order('ordid')->select();
            foreach ($index_cate_list as $key=>$val) {
                //推荐到首页的子类
                $where = array('status'=>'1', 'is_index'=>'1', 'spid'=>array('like', $val['id'] . '|%'));
                $index_cate_list[$key]['index_sub'] = $item_cate_mod->field('id,name,img')->where($where)->order('ordid')->select();
                //普通子类
                $index_cate_list[$key]['sub'] = array();
                foreach ($cate_relate[$val['id']]['sids'] as $sid) {
                    if ($cate_data[$sid]['type'] == '0' && $cate_data[$sid]['pid'] != $val['id']) {
                        $index_cate_list[$key]['sub'][] = $cate_data[$sid];
                    }
                    if (count($index_cate_list[$key]['sub']) >= 6) {
                        break;
                    }
                }
            }
            F('index_cate_list', $index_cate_list);
        }

        //发现
        $hot_tags = explode(',', C('pin_hot_tags')); //热门标签
        $hot_tags = array_slice($hot_tags, 0, 12);
        $this->waterfall('', 'hits DESC,id DESC', '', C('pin_book_page_max'), 'book/index');

        $this->assign('index_cate_list', $index_cate_list);
        $this->assign('hot_tags', $hot_tags);
        $this->assign('nav_curr', 'index');
        $this->_config_seo();
        $this->display();
    }
}