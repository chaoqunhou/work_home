<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/10
 * Time: 15:51
 */

namespace app\common\validate;


use think\Validate;

class Order extends Validate
{
    protected $rule = [
        'id'	        => 'require|number|>:0',
        'goods_id'	    => 'require|number|>:0',
        'access_token' 	=> 'require|alphaDash',
        'address_id'    => 'require|number',
        'shop_id'       => 'require|number',
        'order_id'      => 'require|number',
        'order_status'      => 'require|number',
        'mansong_id'    => 'number',
        'buyer_message' => 'chsDash',
        'payment_type'  => 'number',
        'is_fast_buy'  => 'in:0,1',
        'order_goods_id' => 'require|number',
        'refund_shipping_company' => 'require',
        'refund_shipping_code' => 'require',
    ];

    protected $scene = [
        'save'              =>  ['access_token','address_id','shop_id','mansong_id'],
        'update'            =>  ['access_token'],
        'delete'            =>  ['access_token'],
        'orderListAction'   =>  ['access_token'],
        'orderDetailAction' =>  ['access_token','order_id'],
        'orderCreateAction' =>  ['access_token','buyer_message','address_id','address_id','payment_type','is_fast_buy'],
        'orderCloseAction' =>  ['access_token','order_id'],
        'affirmOrderAction' =>  ['access_token','order_id'],
        'askForRefundAction' =>  ['access_token','','order_goods_id','is_fast_buy'],
        'addRefundShippingAction' =>  ['access_token','','order_goods_id','refund_shipping_code','refund_shipping_company'],
        'orderGoodsCommentAction' =>  ['access_token','order_id']
//        'refundApprovalAction' =>  ['access_token','','order_goods_id','refund_shipping_code','refund_shipping_company'],
    ];
}