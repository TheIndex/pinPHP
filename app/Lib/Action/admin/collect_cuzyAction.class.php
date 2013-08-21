<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Lion
 * Date: 13-7-26
 * Time: 下午3:15
 * To change this template use File | Settings | File Templates.
 */

class collect_cuzyAction extends backendAction {
	private $_tbconfig = NULL;
	private $_cuzyconfig = NULL;

	public function _initialize() {
		parent::_initialize();
		$item_site = M('item_site');
		$api_config      = $item_site->where(array('code' => 'taobao'))->getField('config');
		$cuzy_config      = $item_site->where(array('code' => 'cuzy'))->getField('config');
		$this->_tbconfig = unserialize($api_config);
		$this->_cuzyconfig = unserialize($cuzy_config);
	}

	/**
	 * 阿里妈妈筛选采集
	 * 为了减少API调用次数，搜索出来的结果保存到缓存,第二次搜索清空上一次
	 */
	public function index() {
		//判断CURL
		if(! function_exists("curl_getinfo")) {
			$this->error(L('curl_not_open'));
		}
		//获取淘宝商品分类
		$item_cate = $this->_get_tbcats();
		$this->assign('item_cate', $item_cate);
		$this->display();
	}

	public function ajax_get_tbcats() {
		$cid       = $this->_get('cid', 'intval', 0);
		$item_cate = $this->_get_tbcats($cid);

		if($item_cate) {
			$this->ajaxReturn(1, '', $item_cate);
		} else {
			$this->ajaxReturn(0);
		}
	}

	private function _get_tbcats($cid = 0) {
		$cuzy_top = $this->_get_cuzy_top();
		$req      = $cuzy_top->load_api("GetListByCid");
//		$req      = new GetListByCid();
		$req->setCid($cid);
		$resp = $cuzy_top->execute($req);
		$res_cats  = (array)$resp->itemcats_get_response->item_cats;
		$item_cate = array();
		foreach ($res_cats['item_cat'] as $val) {
			$val         = (array)$val;
			$item_cate[] = $val;
		}

		//		dump($item_cate);die;
		return $item_cate;
	}


	private function _get_cuzy_top() {
		vendor('cuzySDK.CuzyApi');
		$cuzy_top         = new CuzyClient();
		$cuzy_top->appkey = $this->_cuzyconfig['app_key'];
		$cuzy_top->appsecret = $this->_cuzyconfig['app_secret'];

		return $cuzy_top;
	}

	/**
	 * 准备采集
	 */
	public function search() {
	       //搜索结果
        $cuzy_item_list = array();
        if ($this->_get('search')) {
			$map = $this->_get_params();
            if (!$map['keyword'] && !$map['cid']) {
                $this->error(L('select_cid_or_keyword'));
            }
	        $map['like_init'] = $this->_get('like_init', 'trim');
	        
            $result = $this->_get_list($map);
            //分页
	        $sum = $result['count'];
	        if($result['count'] > 400) $sum =400;
            $pager = new Page($sum, 20,"","",$result['count']);
            $page = $pager->show();
            $this->assign("page", $page);
            //列表内容
            $cuzy_item_list = $result['item_list'];
        }
        $cuzy_item_list && F('cuzy_item_list', $cuzy_item_list);
        $this->assign('list', $cuzy_item_list);
        $this->assign('list_table', true);
        $this->display();
	}

	private function _get_params() {
		$map['keyword'] = $this->_get('keyword', 'trim'); //关键词
		$map['cid'] = $this->_get('cid', 'intval'); //分类ID
		$map['page'] = $this->_get('p', 'intval'); //批量
			
		$map['sort'] = $this->_get('sort', 'trim');
		$map['start_commissionRate'] = $this->_get('start_commissionRate', 'intval');
		$map['end_commissionRate'] = $this->_get('end_commissionRate', 'intval');
		$map['start_price'] = $this->_get('start_price', 'intval');
		$map['end_price'] = $this->_get('end_price', 'intval');
		$map['start_credit'] = $this->_get('start_credit', 'intval');
		$map['end_credit'] = $this->_get('end_credit', 'intval');
		$map['itemType'] = $this->_get('itemType', 'intval');
		$map['start_commissionVolume'] = $this->_get('start_commissionVolume', 'intval');
		$map['end_commissionVolume'] = $this->_get('end_commissionVolume', 'intval');
		return $map;
	}
	
	private function _get_list($map) {
		$cuzySDK = $this->_get_cuzy_top();

		$cuzyDataBySearch =$cuzySDK->load_api("GetItemBySearch");
		$cuzyDataBySearch->setPerpage(20);
		empty($map['page']) && ($map["page"] = 1);
		$cuzyDataBySearch->setPage($map["page"]);
		$cuzyDataBySearch->setPicSize("100x100");
		
//		dump($map);die;
		$map['keyword'] && $cuzyDataBySearch->setSearchKey($map['keyword']); //关键词
		$map['cid'] && $cuzyDataBySearch->setCid($map['cid']); //分类
		
		//搜索条件，排序
		$map['sort'] && $cuzyDataBySearch->setSort($map['sort']);
		$map['start_commissionRate'] && $cuzyDataBySearch->setStartCommissionRate($map['start_commissionRate']*100); 
		$map['end_commissionRate'] && $cuzyDataBySearch->setEndCommissionRate($map['end_commissionRate']*100);
		$map['start_price'] && $cuzyDataBySearch->setStartPromotion($map['start_price']); 
		$map['end_price'] && $cuzyDataBySearch->setEndPromotion($map['end_price']); 
		$map['start_credit'] && $cuzyDataBySearch->setStartCredit($map['start_credit']); 
		$map['end_credit'] && $cuzyDataBySearch->setEndCredit($map['end_credit']); 
		$map['itemType'] && $cuzyDataBySearch->setItemType($map['itemType']); 
		$map['start_commissionVolume'] && $cuzyDataBySearch->setStartCommissionVolume($map['start_commissionVolume']); 
		$map['end_commissionVolume'] && $cuzyDataBySearch->setEndCommissionVolume($map['end_commissionVolume']); 

		$resp = $cuzySDK->execute($cuzyDataBySearch);
		$itemData = $resp->cuzy_items_get_response->cuzy_items;
		$realItemData = (array)$itemData->item;
		$count = $itemData->count;
//		dump($realItemData);die;

		//列表内容
		$itemList = array();
		foreach ($realItemData as $val) {
			$val = (array) $val;
			switch ($map['like_init']) {
				case 'volume':
				$val['likes'] = $val['volume'];
				break;
				default:
				$val['likes'] = 0;
				break;
			}
			$itemList[$val['num_iid']] = $val;
			//$itemList[$val['num_iid']]['imgs'] = $val["pic_url"];
		}
		
		//获取商品相册信息
		$tb_top = $this->_get_tb_top();
        $iids = array_keys($itemList);
        $req = $tb_top->load_api('ItemsListGetRequest');
        $req->setFields('num_iid,item_img');
        $req->setNumIids(implode(',', $iids));
        $resp = $tb_top->execute($req);
        $resp_items = (array) $resp->items;
        $resp_item_list = $resp_items['item'];
        foreach ($resp_item_list as $val) {
            $imgs = array();
            $val = (array) $val;
            $item_imgs = (array) $val['item_imgs'];
            $item_imgs = (array) $item_imgs['item_img'];
            foreach ($item_imgs as $_img) {
                $_img = (array) $_img;
                if ($_img['url']) {
                    $imgs[] = array(
                        'url' => $_img['url'],
                        'surl' => $_img['url'] . '_100x100.jpg',
                        'ordid' => $_img['position']
                    );
                }
            }
			$itemList[$val['num_iid']]['imgs'] = $imgs;
        }
		
		//返回
		return array(
			'count' => intval($count),
			'item_list' => $itemList,
		);
  }
  
	public function publish() {
     if (IS_POST) {
         $ids = $this->_post('ids', 'trim');
         $cate_id = $this->_post('cate_id', 'intval');
         !$cate_id && $this->ajaxReturn(0, L('please_select') . L('publish_item_cate'));
         $auid = $this->_post('auid', 'intval');
         //必须指定用户
         !$auid && $this->ajaxReturn(0, L('please_select') . L('auto_user'));
         //获取马甲
         $auto_user_mod = M('auto_user');
         $user_mod = M('user');
         $unames = $auto_user_mod->where(array('id' => $auid))->getField('users');
         $unamea = explode(',', $unames);
         $users = $user_mod->field('id,username')->where(array('username' => array('in', $unamea)))->select();
         !$users && $this->ajaxReturn(0, L('auto_user_error'));
         //从缓存中获取本页商品数据
         $ids_arr = explode(',', $ids);
         $cuzy_item_list = F('cuzy_item_list');//print_r($cuzy_item_list);die;
		
         foreach ($cuzy_item_list as $key => $val) {
             if (in_array($key, $ids_arr)) {
                 $this->_publish_insert($val, $cate_id, $users);
             }
         }
         $this->ajaxReturn(1, L('operation_success'), '', 'publish');
     } else {
         $ids = trim($this->_get('id'), ',');
         $this->assign('ids', $ids);
         //采集马甲
         $auto_user = M('auto_user')->select();
         $this->assign('auto_user', $auto_user);
         $response = $this->fetch();
         $this->ajaxReturn(1, '', $response);
     }
 }

	private function _publish_insert($item, $cate_id, $users) {
     //随机取一个用户
     $user_rand = array_rand($users);
     $item['title'] = strip_tags($item['title']);
	 $item['pic_url']= rtrim(rtrim($item['pic_url'], '100x100.jpg'), '_');
    // $item['click_url'] = Url::replace($item['click_url'], array('spm' => '2014.21069764.' . $this->_tbconfig['app_key'] . '.0'));
     $insert_item = array(
         'key_id' => 'taobao_' . $item['num_iid'],
         'taobao_sid' => $item['taobao_sid'],
         'cate_id' => $cate_id,
         'uid' => $users[$user_rand]['id'],
         'uname' => $users[$user_rand]['username'],
         'title' => $item['title'],
         'intro' => $item['title'],
         'img' => $item['pic_url'],
         'price' => $item['promotion_price'],
         'url' => $item['click_url'],
         'rates' => $item['commission_rate'] / 100,
         'likes' => $item['likes'],
         'imgs' => $item['imgs']
     );
     //如果多图为空
     if (empty($item['imgs'])) {
         $insert_item['imgs'] = array(array(
                 'url' => $insert_item['img'],
                 ));
     }
     $result = D('item')->publish($insert_item);
     return $result;
 }


	/**
  * 直接入库准备
  */
 public function batch_publish() {

     if (IS_POST) {
         $cate_id = $this->_post('cate_id', 'intval');
         !$cate_id && $this->ajaxReturn(0, L('please_select') . L('publish_item_cate'));
         $auid = $this->_post('auid', 'intval');
         //必须指定用户
         !$auid && $this->ajaxReturn(0, L('please_select') . L('auto_user'));
         //采集页数
         $page_num = $this->_post('page_num', 'intval', 10);
         //获取马甲
         $auto_user_mod = M('auto_user');
         $user_mod = M('user');
         $unames = $auto_user_mod->where(array('id' => $auid))->getField('users');
         $unamea = explode(',', $unames);
         $users = $user_mod->field('id,username')->where(array('username' => array('in', $unamea)))->select();
         !$users && $this->ajaxReturn(0, L('auto_user_error'));
         //搜索条件
         $form_data = $this->_post('form_data', 'urldecode');
         parse_str($form_data, $form_data);
         //把采集信息写入缓存
         F('batch_publish_cache', array(
             'cate_id' => $cate_id,
             'users' => $users,
             'page_num' => $page_num,
             'form_data' => $form_data,
         ));
         $this->ajaxReturn(1);
     } else {
         $auto_user = M('auto_user')->select(); //采集马甲
         $this->assign('auto_user', $auto_user);
         $response = $this->fetch();
         $this->ajaxReturn(1, '', $response);
     }
 }

	/**
	* 开始入库
	*/
	public function batch_publish_do() {
		if (false === $batch_publish_cache = F('batch_publish_cache')) {
			$this->ajaxReturn(0, L('illegal_parameters'));
		}
		$p = $this->_get('p', 'intval', 1);
		if ($p > $batch_publish_cache['page_num']) {
			$this->ajaxReturn(0, L('import_success'));
		}
		$result = $this->_get_list($batch_publish_cache['form_data'], $p);

		if ($result['item_list']) {
		 foreach ($result['item_list'] as $val) {
			$this->_publish_insert($val, $batch_publish_cache['cate_id'], $batch_publish_cache['users']);
		 }
			$this->ajaxReturn(1);
		} else {
			$this->ajaxReturn(0, L('import_success'));
		}
	}
	
	private function _get_tb_top() {
        vendor('Taobaotop.TopClient');
        vendor('Taobaotop.RequestCheckUtil');
        vendor('Taobaotop.Logger');
        $tb_top = new TopClient;
        $tb_top->appkey = $this->_tbconfig['app_key'];
        $tb_top->secretKey = $this->_tbconfig['app_secret'];
        return $tb_top;
    }
}