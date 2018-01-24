<?php
namespace app\api\controller\v1;

use app\api\api\StandardInterface;
use app\api\controller\baseApi;
use app\api\service\Order as OrderService;


use think\Exception;

class Order extends baseApi implements StandardInterface {
    public function index()
    {
        // TODO: Implement index() method.
    }

    public function create()
    {
        // TODO: Implement create() method.
    }

    public function read($id)
    {
        // TODO: Implement read() method.
    }

    public function editAction($id)
    {
        // TODO: Implement editAction() method.
    }

    /**
     * 提交订单接口
     */
    public function save()
    {

    }

    public function update($id)
    {
        // TODO: Implement update() method.
    }

    /**
     * @param $id 订单ID
     * 修改订单状态(0 未付款  1 已付款 2 已发货 3 已收货 4 已完成 5 已关闭 -1 退款中)
     */
    //todo 订单删除
    public function delete($id)
    {
        try{
            $uid = $_SERVER['uid'];
            $orderS = new OrderService();
            $res    = $orderS -> orderDelete($id,$uid);
        }catch(Exception $e){
            returnJson($e ->getMessage(),$e ->getCode());
        }
        returnJson($res);
    }

    /**
     * 订单状态修改
     */
    public function orderCloseAction()
    {
        try{
            $order_id = input('param.order_id');
            $orderS = new OrderService();
            $res    = $orderS -> orderClose($order_id);
        }catch(Exception $e){
            returnJson($e ->getMessage(),$e ->getCode());
        }
        returnJson($res);
    }

    /**
     * 获取订单详情
     */
    public function orderDetailAction()
    {
        try{
            $order_id = input('param.order_id');
//        dump($order_id);
            $orderS = new OrderService();
            $detail = $orderS->getOrderDetail($order_id);
        }catch(Exception $e){
            returnJson($e -> getMessage(),$e ->getCode());
        }
        returnJson($detail);
    }
    /**
     * 获取订单列表
     */
    public function orderListAction()
    {
        try{
            $uid = $_SERVER['uid'];
            //获取到相关的状态参数
            $param =input('param.');
            $orderS = new OrderService();
           $res = $orderS ->getOrderList($uid,$param);
        }catch( Exception $e){
            returnJson($e ->getMessage(),$e->getCode());
        }
        returnJson($res);
    }

    /**

    +     * 订单商品评论

    +     */

   public function orderGoodsCommentAction()
   {
        try{
            if (empty($_SERVER['uid'])) throw new Exception('需要登陆', 1100);
            $uid = $_SERVER['uid'];
           //获取到相关的状态参数
            $param =input('param.');
            $orderS = new OrderService();
            $res = $orderS ->orderGoodsComment($uid,$param);
      }catch( Exception $e){
                returnJson($e ->getMessage(),$e->getCode());
            }

        returnJson($res);

    }
//   public function orderWaitForPayListAction(){
//        try{
//            $uid = $_SERVER['uid'];
//            $orderS = new OrderService();
//            $res = $orderS ->getOrderWaitForPayList($uid);
//        }catch( Exception $e){
//            returnJson($e ->getMessage(),$e->getCode());
//        }
//        returnJson($res);
//    }
//
//    public function orderShippingListAction(){
//        try{
//            $uid = $_SERVER['uid'];
//            $param = input('param');
//            $orderS = new OrderService();
//            $res = $orderS ->getOrderShippingList($uid,$param);
//        }catch( Exception $e){
//            returnJson($e ->getMessage(),$e->getCode());
//        }
//        returnJson($res);
//    }
    public function orderCreateAction()
    {
        try{
            if (empty($_SERVER['uid'])) throw new Exception('需要登陆', 1100);
            $uid = $_SERVER['uid'];
            $orderInfo = input('param.');//其中包含的信息为:物流公司id(shipping_company_id)/shipping_type(物流方式:1 商家配送,2 自提)/address_id(收货地址的ID)/buyer_message(买家留言,买家选填)/支付方式(到付)/买家要求的配送时间(shipping_time)/优惠信息/商家ID/ payment_type 支付方式(0：在线支付 1：微信支付 2：支付宝 3：银联卡 4：货到付款 5：余额支付)

            //shop_id(卖家商铺信息)
            $orderS = new OrderService();
            $res = $orderS -> orderCreate($uid,$orderInfo);
        }catch(Exception $e){
            returnJson($e -> getMessage() , $e -> getCode() );
        }
        returnJson($res);
    }



}