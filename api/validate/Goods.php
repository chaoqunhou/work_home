<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/10
 * Time: 15:51
 */

namespace app\common\validate;


use think\Validate;

class Goods	extends Validate
{
	protected $rule     = [
		'goods_id'	    => 'require|number|>:0',
		'shop_id'	    => 'require',
        'client_id'		=> 'require|between:1,10',
        'device_open_id'=> 'require|alphaDash',
        'access_token' 	=> 'require|alphaDash',
        'fav_type' 	=> 'require|alphaDash',
        'page_index' 	=> 'require|number|>:0',
        'group_id' 	=> 'require|number|>:0',
	];

	protected $message  =   [

	];

	protected $scene = [
		'save'          =>  [''],
		'update'        =>  [''],
		'aaAction'      =>  [''],
        'collectsAction'  =>  ['goods_id','client_id','device_open_id','access_token','fav_type'],
        'collectsListAction'  =>  ['client_id','device_open_id','access_token','fav_type'],
        'goodsGroupListAction' => ['page_index'],
        'groupGoodsListAction' => ['page_index','group_id']
	];
}