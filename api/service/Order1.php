<?php
namespace app\api\service;

use data\model\NsOrderGoodsModel as dataOrderGoods;
use data\model\NsOrderModel as dataOrder;
use data\model\NsOrderGoodsCommentModel as dataOrderGoodsComment;
use data\model\NsOrderActionModel as dataOrderAction;
use data\model\NsOrderPromotionDetailsModel as dataOrderPromotionDetail;
use data\model\NsOrderGoodsPromotionDetailsModel as dataGoodsPromotionDetail;
use data\model\NsPromotionMansongModel;
use data\model\NsPromotionMansongRuleModel as dataMansongRule;

use data\model\NsOrderRefundModel as dataOrderRefund;

use think\Exception;
use app\api\service\Cart as CartService;
use app\api\service\Promotion as PromotionService;
use app\api\service\Picture as PictureS;
class Order extends BaseService{
    /**
     * @param bool $shop_id
     * @return array
     * @throws Exception
     * @todo完成订单后可删除本方法
     */
    public function orderPrice($shop_id = false)
    {
        if (!($uid = $_SERVER['uid'])) throw new Exception('请先登录', '1100');

        $cartS = new CartService();
        $cartData   = $cartS -> getCartData($uid,1);
        //如果传了shop_id 则是计算某个店铺下
        if ($shop_id !== false){
            $tem = [];
            foreach ($cartData as $data){
                if ($data['shop_id'] == $shop_id){
                    $tem[] = $data;
                }
            }
            $cartData = $tem;
        }

        $promotionS = new PromotionService();
        //这里计算了单品折扣

        $cartRes  = $promotionS -> getGoodsPromotion($cartData);

        $totalPrice = 0;
        $realPrice  = 0;
        foreach ($cartRes as $goods){
            $totalPrice += $goods['price'] * $goods['num'];
            $realPrice  += $goods['real_price']  * $goods['num'];
        }
        $totalPrice = round($totalPrice,2);
        $realPrice  = round($realPrice,2);

        //----------------------计算优惠折扣----------------------------------
        $msRulesP = [];
        $msRules  = [];
        //遍历规则
        foreach ($cartRes as $goodsK => $goodsV ){
            if (empty($goodsV['promotion'])){
                continue;
            }
            if ($goodsV['promotion_rule_id'] != 0 && !empty($goodsV['promotion']['mansong'])){
                $msRulesP[$goodsV['promotion_rule_id']] += $goodsV['real_price'];
                foreach ($goodsV['promotion']['mansong'] as $mansong){
                    foreach ($mansong['manSongRule'] as $rule){
                        if (!empty($rule['rule_id'])){
                            $msRules[$rule['rule_id']] = $rule;
                        }
                    }
                }
            }
        }
        $PictureS   = new PictureS;
        $cartRes   = $PictureS->transPic($cartRes,'goods_picture');


        $discont = 0;
        $useRule = [];
        $unUseRule = [];
        foreach ($msRulesP as $rk => $p){
            if (!isset($msRules[$rk])){
                //  选择了优惠但优惠数组中没有
                continue;
            }
            $msRules[$rk]['rule_real_price'] = $p;
            if ($p >= $msRules[$rk]['price']){
                $useRule[]=  $msRules[$rk];
                if(!empty($msRules[$rk]['discount'])){
                    $discont += $msRules[$rk]['discount'];
                }
            }else{
                $unUseRule[] = $msRules[$rk];
            }
        }
        //=========---------------计算优惠折扣-------------=========================
        return [
            'total_price'   => $totalPrice, //原总价
            'price'         => $realPrice,  //折后价
            'discount'      => $discont,    //优惠的额价格
            'real_price'    => $realPrice - $discont, //实际价格
            'useRule'       => $useRule,    //使用上的优惠
            'unUseRule'     => $unUseRule,    //使用上的优惠
            'promoted_goods_list' => $cartRes,
        ];
    }


    protected function orderNo()
    {
        $orderNo = date('YmdHis',time());
        $orderNo = $orderNo . mt_rand(10000,99999);
        return $orderNo;
    }
    protected function outOrderTradeNo(){
        $outOrderTradeNo = time() . mt_rand(10000,99999);
        return $outOrderTradeNo;
    }

    //创建订单
    public function orderCreate($uid,$orderInfo)
    {
        $orderM = new dataOrder();
        $orderM -> startTrans();

        $paymentType = isset($orderInfo['payment_type']) ? $orderInfo['payment_type'] : 4;
        if ($paymentType != 4 ) throw new Exception('只支持货到付款', 411);
        $order_from = $orderInfo['client_id'];//订单来源 1 安卓 2 ios 3 网页 4 微信
        $addressS    = new Address();
        $addressInfo = $addressS -> readOne($orderInfo['address_id']);//收货地址信息
        $cartS = new CartService();
        $cartDataList = $cartS ->read($uid,1,$orderInfo['is_fast_buy']);//获取所有购物车中所有的商品
        if (!count($cartDataList) > 0)throw new Exception('cartData为空',500);
        $userS      = new User();
        $userInfo   = $userS ->read($uid);//买家信息

        $orderInfo['buyer_id'] = $uid;
        $orderNo = $this ->orderNo();//订单编号
        $outTradeNo = $this -> outOrderTradeNo();

        $createTime = time();
        $orderM = new dataOrder();

        $shopS      = new Shop();
        //---------------------------通过购物车内商品信息获取shop_id---------------------------------
        $cartDataShop   = $this->sdfCartGoodsBYShop($cartDataList);//根据商铺id将数据分组，用于创建不同商铺的订单
        if (count($cartDataShop) != 1)throw new Exception('只能单店支付',500);
        foreach ($cartDataShop as $shop_id => $cartData){
            //获取商铺的信息
            $shopInfo   = $shopS -> read($shop_id);
            //========================================================================================
//          当买家选择自提时,将不用支付物流费用 shipping_type(物流方式 0:商家配送 1:自提) //
//          if ($orderInfo['shipping_type'] == 1){
//              //暂时定为默认商家配送
//          }elseif ($orderInfo['shipping_type'] == 0){
//              //当买家选择商铺配送时,需要获取物流费用
//              $shippingS = new ShippingService();
//              $shippingInfo = $shippingS -> getShippingInfo($orderInfo['shipping_id']);
//          }
//            $priceInfo = $this ->orderPrice($shop_id);//此处需要计算当前订单价格
            $priceInfo = $this ->getOrderPriceByCartDataList($cartData);//根据查出的购物车商品计算订单金额
            $goodsMoney = $priceInfo['total_price']; //商品折扣前销售总价
            /**
             * 订单总价即订单金额，不等于支付金额
             * 订单金额($orderMoney)包含=（支付金额（实际支付金额$pay_money【包含用户余额支付金额】）+折扣金额+优惠金额+积分折扣金额+代金券金额+购物币金额）
             */
            $pay_money = $priceInfo['real_price']; //商品折后销售总价，订单支付金额，不等于订单金额，支付
            $orderMoney = $priceInfo['total_price']; //订单总价即订单金额
            $discount_money = $priceInfo['discount_price']; //商品实际折扣掉的金额（折扣前金额*（1-折扣）%）
            $promotionMoney = $priceInfo['full_reduction_price']; //优惠金额(打折的优惠和满减优惠和)2018.1.18调整为满减优惠的金额
            $cartNewData = $priceInfo['promoted_goods_list'];//使用此处数据，是同步获取了最新的商品价格
            $orderM ->data([
                'shop_id'           => $shop_id,
                'shop_name'         => $shopInfo['shop_name'],
                'order_no'          => $orderNo,
                'out_trade_no'      => $outTradeNo ,
                'order_type'        => 1 ,//订单类型,都有什么类型？
                'payment_type'      => $paymentType ,// 支付方式  0：在线支付 1：微信支付 2：支付宝 3：银联卡 4：货到付款 5：余额支付
                'order_from'        => $order_from,
                'buyer_id'          => $uid ,
                'user_name'         => $userInfo['user_name'],
                'buyer_ip'          => $userInfo['current_login_ip'],
                'buyer_message'     => $orderInfo['buyer_message'],//买家留言
                'buyer_invoice'     => '',
                'create_time'       => $createTime ,
                'shipping_type'     => 1, //配送方式 0 买家自提 1 商家配送
                'shipping_company_id' => 1, //物流配送公司ID
                'receiver_mobile'   => $addressInfo['mobile'],
                'receiver_name'     => $addressInfo['consigner'],
                'receiver_province' => $addressInfo['province'],
                'receiver_city'     => $addressInfo['city'],
                'receiver_district' => $addressInfo['district'],
                'receiver_address'  => $addressInfo['address'],
                'receiver_zip'      => $addressInfo['district'],
                'order_money'       => $orderMoney,
                'goods_money'       => $goodsMoney,
                'promotion_money'   => $promotionMoney,//满减优惠金额
                'discount_money'   => $discount_money,//打折活动优惠金额
                'pay_money'         => $pay_money,//实际订单支付金额
                'point'             =>0,//消耗的积分
                'point_money'       =>0,//积分抵用的金额
                'coupon_money'      =>0,//优惠券抵用的价格
                'coupon_id'         =>0,//订单代金券ID
                'shipping_money'    =>0,//订单运费
                'refund_money'      =>0,//退款金额
                'coin_money'        =>0,//购物币金额
                'give_point'        =>0,//赠送的积分
                'give_coin'         =>0,//订单成功后反购物币
                'order_status'      =>0,//订单状态 0 未完成 1 已完成
                'pay_status'        =>0,//付款状态 0 未付款  1 已付款
                'shipping_status'   =>0,//0 未发货 1 已发货
                'review_status'     =>0,//订单评价状态 0: 未评价 1:已评价 2:已追评
                'feedback_status'   =>0,//维权状态 0:无维权 1 正在维权
            ]);
            $res = $orderM -> save();
            if ($res < 0){
                $orderM ->rollback();
                throw new \Exception('创建订单失败[1]',500);
            }
            //订单id
            $order_id = $res;
            //================================订单商品明细表================================
            $this ->orderGoods($order_id,$cartNewData);
            //================================打折优惠================================
            $this -> orderGoodsPromotion($order_id,$cartNewData);//存储折扣信息 ns_order_goods_promotion_details
            //===============================满减优惠================================
            //根据选择的优惠信息，存储数据到 ns_order_promotion_details,这个地方可仅用于记录数据，不再计算满减的优惠
            $this -> orderPromotionDetails($order_id,$priceInfo['promotion']);
            //获取优惠总额度
            //================================订单操作日志记录================================
            $action = '创建订单';//记录订单操作日志
            $res_orderAction = $this ->orderAction($order_id,$action,$userInfo);//修复$res为$res_orderAction
            if ($res_orderAction < 0){
                $orderM ->rollback();
                throw new \Exception('创建订单失败[2]',500);
            }
            //================================订单金额更新================================
            //第一步计算出满减优惠额度的话，这个地方可以去掉了不用再更新订单信息
//                $orderM->save([
//                    'promotion_money'=>$total_promotion_price,
//                    'pay_money'=>$pay_money-$total_promotion_price,
//                ],['id'=>$order_id]);
            //================================支付状态表================================
            //完成订单写入后，执行购物车清空操作
            $orderM ->commit();
        }
    }

    /**
     * 根据查出的购物车商品计算订单金额
     * @param $cartData
     */
    public function getOrderPriceByCartDataList($cartData){
        $promotionS = new PromotionService();
        //这里计算了单品折扣
        $order_discount  = $promotionS -> getOrderGoodsDiscount($cartData);
        //这里计算满减
        $order_promotion  = $promotionS -> getOrderGoodsPromotion($order_discount);

//        $order_array = array(
//            'discount'=>$order_discount,
//            'promoted_goods_list'=>$order_discount['promoted_goods_list'],
//            'promotion'=>$order_promotion
//        );

        $order_array = array(
            'total_price'=>$order_discount['total_price'],//总金额（未去掉折扣）@todo后期加运费等金额
            'price'=>$order_discount['price'],//未优惠时的总金额，仅商品本身金额
            'discount_price'=>$order_discount['discount_price'],//折扣优惠金额
            'real_price'=>$order_discount['real_price']-$order_promotion['total_manjian_discount_price'],//实际支出金额，总金额-折扣总金额-优惠总金额
            'full_reduction_price'=>$order_promotion['total_manjian_discount_price'],//满减优惠总金额
            'promoted_goods_list'=>$order_discount['promoted_goods_list'],
            'promotion'=>$order_promotion,
            'shipping_money'=>0,//物流费用
        );
//        print_r($order_array);
//        exit;
        return $order_array;
    }


    /**
     * @param $order_id
     * @param $action
     * @param string $userInfo
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 订单操作表添加
     */
    public function orderAction($order_id,$action,$userInfo =''){
        $orderActionM = new dataOrderAction();
        $orderActionM -> startTrans();
        //如果没有用户信息则到用户表内进行取出用户信息
        if (empty($userInfo)){
            $uid =$_SERVER['uid'];
            $userS      = new User();
            $userInfo   = $userS ->read($uid);//买家信息
        }
        $data['user_name'] = $userInfo['user_name'];
        $orderM         = new dataOrder();
        $orderInfo      = $orderM -> where(['order_id' => $order_id]) ->find();
        $order_status   = $orderInfo -> order_status;
        $order_status_text          = $this ->getOrderStatus($order_id,$order_status);
        $data['order_status']       = $order_status;
        $data['order_id']       = $order_id;
        $data['action']       = $action;
        $data['order_status_text']  = $order_status_text;
        $data['action_time']        = time();
        $res = $orderActionM -> save($data);
        $orderActionM ->commit();
        if ($res < 0){
            $orderActionM -> rollback();
            throw new Exception('异常','500');
        }
        return $res;
    }

    /**
     * 订单商品明细表
     * @param $order_id
     * @param $cartData
     * @throws \Exception
     */
    public function orderGoods($order_id,$cartData)
    {
        $orderGoodsM =new dataOrderGoods();
        $orderGoodsM -> startTrans();
        foreach($cartData as $promotion_key => $promotion_value){
            $orderGoods['order_id'] = $order_id;
            $orderGoods['goods_id'] = $promotion_value['goods_id'];
            $orderGoods['goods_name'] = $promotion_value['goods_name'];
            $orderGoods['sku_id'] = $promotion_value['sku_id'];
            $orderGoods['sku_name'] = $promotion_value['sku_name'];
            $orderGoods['price'] = $promotion_value['single_sale_price'];//折扣前的价格
            $orderGoods['cost_price'] = $promotion_value['price'];//商品成本@todo 获取数据
            $orderGoods['num'] = $promotion_value['num'];
            $orderGoods['goods_money'] = $promotion_value['sale_price'];//折后总价
//            $orderGoods['goods_picture'] = $promotion_value['goods_picture'];//商品图片@todo 获取数据
            $orderGoods['shop_id'] = $promotion_value['shop_id'];
            $orderGoods['promotion_id'] = $promotion_value['promotion_rule_id'];//这个是在购物车选择的满减优惠规则ID
            $orderGoods['goods_type'] = '';//商品类型@todo 获取数据
            $orderGoods['order_type'] = 1;//订单类型
            $orderGoods['order_status'] = 0;//订单状态
            $orderGoodsData[] = $orderGoods;
        }
        $orderGoodsM ->saveAll($orderGoodsData);
        $orderGoodsM ->commit();
    }

    /**
     * 将满减活动根据订单信息写入《订单优惠详情》数据表
     * @param $order_id
     * @param $orderData
     * @throws \Exception
     */
    public function orderPromotionDetails($order_id,$cartNewData){
        /**
         * 这里的$cartNewData，已根据商家进行区分
         * 根据写入的商家订单进行处理
         */
        $orderPromotionM = new dataOrderPromotionDetail();
        $orderPromotionM -> startTrans();
        $promotionData['order_id']  = $order_id;
        $total_promotion_price = 0;
        foreach($cartNewData as $key => $value){
            $promotionData['promotion_type_id']     = $value['promotion_rule_id'];//优惠类型规则ID（满减对应规则）
            $promotionData['promotion_id']          = $value['rule_id'];//优惠ID
            $promotionData['promotion_type']        = 'MANJIAN';//优惠类型
            $promotionData['promotion_name']        = $value['mansong_name'];//该优惠活动的名称
            $promotionData['promotion_condition']   = '满' .$value['manzu_price'] .'减' . $value['manjian_discount_price'];//优惠使用条件说明
            $promotionData['discount_money']        = $value['manjian_discount_price'];//优惠的金额，单位：元，精确到小数点后两位
            $promotionData['used_time']             = time();
            $promotion[] =$promotionData;
        }
        $orderPromotionM ->saveAll($promotion);
        $orderPromotionM -> commit();
        return $total_promotion_price;
    }

    /**
     * 将打折活动根据订单信息写入《订单商品折扣优惠详情》数据表
     * @param $order_id
     * @param $orderData
     * @throws \Exception
     * @todo校验打折信息
     */

    public function orderGoodsPromotion($order_id,$orderData){

        $goodsPromotionM    = new dataGoodsPromotionDetail();
        $goodsPromotionM    ->startTrans();
        $total_discount_price = 0;
        foreach ($orderData as $k=>$v){
            $discountData['order_id'] = $order_id;
            $discountData['sku_id'] = $v['sku_id'];
            $discountData['promotion_type'] = 'ZHEKOU';//折扣
            $discountData['promotion_id'] = 0;
            $discountData['discount_money'] = $v['sale_price']-$v['real_price'];//优惠掉的部分
            $discountData['used_time'] = time();
            $discount[] = $discountData;
        }
        if ($discount){
            $goodsPromotionM ->saveAll($discount);
            $goodsPromotionM ->commit();
        }
        return $total_discount_price;
    }

    /**
     * @param $order_id 获取订单状态
     * @param string $order_status
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function getOrderStatus($order_id,$order_status = '')
    {
        if (!$order_status){
            $orderM = new dataOrder();
            $orderInfo = $orderM -> where(['order_id' => $order_id]) ->find();
        }
        $statusArray = OrderStatus::getOrderCommonStatus();
        foreach($statusArray as $status_key => $status_value){
            if ($status_value['status_id'] ==$order_status){
                return $status_value['status_name'];
            }
        }
        return false;
    }
    public function getOrderDetail($order_id){
        $detail   =  $this -> getDetail($order_id);
        $addressS = new Address();
        $province = $addressS ->getProvince($detail['receiver_province']);
        $city     = $addressS ->getCity($detail['receiver_city']);
        $district = $addressS -> getDistrict($detail['receiver_district']);
        $receiverInfo = '收件人:' . $detail['receiver_name'] . ' 联系电话:'. $detail['receiver_mobile'] . ' 收货地址:'.$province['province_name'] . $city['city_name'] . $district['district_name'] . $detail['receiver_address'];
        $detail['receiver_info']      =  $receiverInfo ;
        $detail['receiver_province']  = $province['province_name'];
        $detail['receiver_city']      = $city['city_name'];
        $detail['receiver_district']  = $district['district_name'];
        $orderGoodsDetail   = $this ->getOrderGoodsDetail($order_id);

        $detail['goods_info'] = $orderGoodsDetail;
        return $detail;
    }

    public function getDetail($order_id){

        $orderM         = new dataOrder();
        $orderDetail    = $orderM -> where( [ 'order_id'=>$order_id ,'is_deleted' => 0 ] ) -> find();
        if (empty($orderDetail)){
            throw new Exception('订单已经删除','404');
        }

        $orderDetail = $orderDetail -> toArray();
        return $orderDetail;
    }

    /*
     *  查询order_goods
     */
    public function getOrderGoodsDetail($order_id){
        $orderGoodsM        = new dataOrderGoods();
        $orderGoods = [];
        //判断是否是多个订单
        if (is_in_str($order_id)){
            $orderGoodsDetail   = $orderGoodsM -> where('order_id' ,'in', $order_id) -> select();
        }else{
            $orderGoodsDetail   = $orderGoodsM -> where('order_id' ,'eq', $order_id) -> select();
        }
        $orderGoodsDetail       = collection($orderGoodsDetail) ->toArray();
        if (empty($orderGoodsDetail)){
            throw new Exception('没有相关商品信息','404');
        }
        $PictureS   = new PictureS;
        $orderGoodsDetail   = $PictureS->transPic($orderGoodsDetail,'goods_picture');

        ###订单商品应展示订单信息 不去查原信息
        //获取商品的具体规格信息
        $goods_sku              = array_column($orderGoodsDetail,'sku_id');
        $goodsS                 = new Goods();
        $goodsDetail            = $goodsS -> getGoodsDetail($goods_sku);


        //$picture                = implode(',' , array_column($orderGoodsDetail,'picture'));
        //如果是多个订单,对订单数据进行重新构建
        if (is_in_str($order_id)){
            $spec_info =[];

            //对商品进行循环判断是否具有相关规格
            foreach($goodsDetail as $goods_detail_key => $goods_detail_value){
                //如果有相关规格则将规格的json传进行转成数组加入至商品列表内
                if (!empty(substr($goods_detail_value['goods_spec_format'],1,-1))){

                    $spec = json_decode($goods_detail_value['goods_spec_format'],1);
                    $goodsDetail[$goods_detail_key]['sku_detail']['spec_format'] = $spec;
                    //将规格字符串炸成数组
                    $specArr = explode(';',$goods_detail_value['attr_value_items']);

                    foreach($specArr as $arr_key => $arr_value){
                        $spec_id = substr($arr_value,0,strpos($arr_value,':'));
                        $spec_value_id = substr($arr_value,strpos($arr_value,':')+1);
                        array_push($spec_info,[$spec_id => $spec_value_id]);
                        $spec_info[$spec_id] =$spec_value_id;
                    }
                    foreach($spec as $spec_key => $spec_value){
                        foreach($spec_value['value'] as $sku_spec_value){
                            foreach($spec_info as $spec_info_key => $spec_info_value){
                                if ($spec_info_key == $sku_spec_value['spec_id'] && $spec_info_value == $sku_spec_value['spec_value_id']) {
                                    $specData['name'] = $sku_spec_value['spec_name'];
                                    $specData['value'] = $sku_spec_value['spec_value_name'];//
                                    $specInfo[] = $specData;
                                }
                            }
                        }

                    }

                    $goodsDetail[$goods_detail_key]['sku_detail']['spec_format'] = $spec;
                    $goodsDetail[$goods_detail_key]['sku_name_array'] = $specInfo;

                    $specInfo =[];

                }
            }
            foreach($orderGoodsDetail as $order_goods_key => $order_goods_value){
                foreach($goodsDetail as $goods_detail_key => $goods_detail_value){
                    if ($order_goods_value['sku_id'] == $goods_detail_value['sku_id']){
                        $orderGoodsDetail[$order_goods_key]['sku_detail'] = $goods_detail_value;
                    }
                }
            }
        }


        return $orderGoodsDetail;
    }

    /**
     * @param $uid 用户ID
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderList($uid,$param){
        $orderM = new dataOrder();
        //$orderList = $orderM -> where(['buyer_id' => $uid ,$key => $value ,'is_deleted' => 0]) -> select();
        $where = ['buyer_id' => $uid,'is_deleted' => 0];
        if (isset($param['order_status'])){
            $where = array_merge($where,['order_status'=>$param['order_status']]);
        }
        if (isset($param['pay_status'])){
            $where = array_merge($where,['pay_status'=>$param['pay_status']]);
        }
        if (isset($param['shipping_status'])){
            $where = array_merge($where,['shipping_status'=>$param['shipping_status']]);
        }
        if (isset($param['feedback_status'])){
            $where = array_merge($where,['feedback_status'=>$param['feedback_status']]);
        }
        if (isset($param['page'])){
            $page = $param['page'];
            $offset = ($page - 1)*10;
        }else{
            $offset = 0;
        }
        $orderList = $orderM -> where($where)->limit($offset,10) -> select();

        if (empty($orderList)){
            throw new Exception('您还没有相关订单','404');
        }
        $orderList     = collection($orderList) -> toArray();
        $orderIdArr    = array_column($orderList,'order_id');
        $orderId       = implode(',',$orderIdArr);
        $orderGoodsData     = $this -> getOrderGoodsDetail($orderId);
        //根据不同订单将相应的商品放入订单树下
        foreach($orderList as $orderList_key => $orderList_value){
            foreach($orderGoodsData as $goods_key => $goods_value){
                if ($orderList_value['order_id'] == $goods_value['order_id']){
                    $orderList[$orderList_key]['goods_list'][] = $goods_value;
                }
            }
        }

        return $orderList;
    }

    public function orderGoodsComment($uid,$param){
        $orderGoodsM = new dataOrderGoods();
        $orderGoods = $orderGoodsM ->where(['order_id'=>$param['order_id']])->field('order_goods_id,order_id,buyer_id,shop_id,goods_id,goods_name')-> select();

        $orderGoods = collection($orderGoods) ->toArray();
//        foreach ($orderGoods as $k => $v){
//            $ogcid_arr[] = $v['order_goods_id'];
//        }
//        $arr = array_unique($arr);
//        $ordergoods   = $orderGoodsM -> where('order_id' ,'in', $arr)->field('goods_id') -> select();
//        print_r($arr);die;
        $orderGoodsComment = new dataOrderGoodsComment();
        $comment = $orderGoodsComment->where(['uid'=>$uid,'order_id'=>$param['order_id']])->field('order_goods_id')->select();

        foreach ($comment as $k => $v){
            $comment_arr[] = $v['order_goods_id'];
        }

        foreach ($orderGoods as $k => $v){
            if (empty($comment_arr) ||(!empty($comment_arr) && !in_array($v['order_goods_id'],$comment_arr))){
                $time = time();
                $data = array(
                    'uid' => $v['buyer_id'],
                    'order_id' => $v['order_id'],
                    'goods_id' => $v['goods_id'],
                    'order_goods_id' =>$v['order_goods_id'],
                    'create_time' => $time
                );
                $insert[] = $data;
            }
        }
           if(!empty($insert)){
               $res = $orderGoodsComment->saveAll($insert);
           }else{
               throw new Exception('已有评论记录',501);
           }

//        $orderM = new dataOrder();
//        $where = ['buyer_id' => $uid,'is_deleted' => 0,'order_status'=>$param['order_status']];//
//        $where = array_merge($where,['order_status'=>5]);

//        $orderList = $orderM -> where($where)-> select();
//        print_r($orderList);die;
//        $orderGoodsM = new dataOrderGoods();
//        $orderGoods = [];
//        $orderList = collection($orderList) -> toArray();
//        $orderIds = array_column($orderList,'order_id');
//        $orderId = implode(',',$orderIds);

        //判断是否是多个订单
//        if (is_in_str($orderId)){
//            $orderGoods = $orderGoodsM -> where('order_id' ,'in', $orderId)->field('order_goods_id,order_id,buyer_id,shop_id,goods_id,goods_name') -> select();
//        }else{
//            $orderGoods = $orderGoodsM -> where('order_id' ,'eq', $orderId) ->field('order_goods_id,order_id,buyer_id,shop_id,goods_id,goods_name')-> select();
//        }
//        $orderGoods = collection($orderGoods) ->toArray();
//        foreach ($orderGoods as $k => $v){
//            $arr[] = $v['order_id'];
//        }
//        $arr = array_unique($arr);
//        $ordergoods   = $orderGoodsM -> where('order_id' ,'in', $arr)->field('goods_id') -> select();
//        if (isset($param['page'])){
//            $page = $param['page'];
//            $offset = ($page - 1)*10;
//        }else{
//            $offset = 0;
//        }
//        $orderList = $orderM -> where($where)->limit($offset,10) -> select();
//        $orderGoodsData     = $this -> getOrderGoodsDetail($orderId);
        //根据不同订单将相应的商品放入订单树下
//        foreach($orderList as $orderList_key => $orderList_value){
//            print_r($orderList_value);
//            foreach($orderGoodsData as $goods_key => $goods_value){
//                if ($orderList_value['order_id'] == $goods_value['order_id']){
//                    $orderList[$orderList_key]['goods_list'][] = $goods_value;
//                }
//            }
//        }
//        die;
//        $res = $orderList[$orderList_key]['goods_list']['goods_id'];
////        return $res;
        return $res;
    }
//    public function getOrderWaitForPayList($uid){
//        $orderM = new dataOrder();
//        $orderList = $orderM -> where(['buyer_id' => $uid ,'pay_status' => 0]) -> select();
//        if (empty($orderList)){
//            throw new Exception('您没有待付款订单','404');
//        }
//        $orderList = collection($orderList) -> toArray();
//        return $orderList;
//    }
//    public function getOrderShippingList($uid,$param){
//
//        $orderM = new dataOrder();
//        $orderList = $orderM -> where(['buyer_id' => $uid ,'shipping_status' => $param['shipping_status']]) -> select();//0是待收货,1是已收货
//        if (empty($orderList)){
//            throw new Exception('您没有待付款订单','404');
//        }
//        $orderList = collection($orderList) -> toArray();
//        return $orderList;
//    }
    public function orderDelete($order_id,$uid)
    {
        $orderM = new dataOrder();
        $res = $orderM -> where('order_id','eq',$order_id) -> update(['is_deleted' => 1]);//is_delete = 1 表示已经删除
        $action = '删除订单';
        $resAction = $this -> orderAction($order_id,$action);

        if ($res < 0 && $resAction){
            throw new Exception('没有找到该订单','404');
        }
        return $res;
    }

    /**
     * @param $order_id  订单ID
     * @return int|string
     * @throws Exception
     * @throws \think\exception\PDOException
     * 关闭订单
     */
    public function orderClose($order_id)
    {
        $orderM = new dataOrder();
        $res = $orderM -> where('order_id','eq',$order_id) -> update(['order_status' => 5 ]);//order_status = 5 表示已经关闭
        $action = '关闭订单';
        $resAction = $this -> orderAction($order_id,$action);
        $orderGoodsM = new dataOrderGoods();
        $orderData = $orderGoodsM -> where('order_id','eq',$order_id)-> select();
        foreach($orderData as $key => $value){
            if($value -> order_id == $order_id){
                $orderGoodsM -> where('order_goods_id','eq',$value -> order_goods_id) -> update(['order_status' => 5]);
            }
        }

        if ($res < 0 && $resAction){
            throw new Exception('没有找到该订单','404');
        }
        return $res;
    }
    public function orderCancel(){

    }

    //将CartGoods分店铺返回
    private function sdfCartGoodsBYShop($cartData)
    {
        $res = [];
        if (!count($cartData) > 0) throw new Exception('数组为空',500);
        foreach ($cartData as $goods){
            $res[$goods['shop_id']][] = $goods;
        }
        return $res;
    }


    //申请退款
    public function askForRefund($order_id,$order_goods_id,$refund_reason,$refund_require_money){

        //action 退款操作内容描述
//        1 买家申请退款
//        2 卖家同意退款
//        3 卖家确认收货
//        4 卖家同意退款
//        -1 卖家拒绝退款
//        -2 买家撤销退款申请
//        -3 退款申请不通过 需要买家修改申请信息
        $action             = 1;
        $action_way         = 1; //1 买家  2 卖家
        $refund_status      = 1;
        $this  -> orderRefundTable($order_goods_id,$refund_status,$action,$action_way);
        $this  -> refundOrderGoodsTable($order_id,$order_goods_id,$refund_status,$refund_reason,$refund_require_money);
        $this  -> refundOrderTable($order_id,$refund_status,$action_way);
        return 1;

    }
    //操作order_refund表
    public function orderRefundTable($order_goods_id,$refund_status,$action,$action_way, $id = 0){

        //操作状态   流程状态(refund_status)	状态名称(refund_status_name)	操作时间
//1	买家申请	发起了退款申请,等待卖家处理//
//2	等待买家退货	卖家已同意退款申请,等待买家退货
//3	等待卖家确认收货	买家已退货,等待卖家确认收货
//4	等待卖家确认退款	卖家同意退款
//0	退款已成功	卖家退款给买家，本次维权结束
//-1	退款已拒绝	卖家拒绝本次退款，本次维权结束
//-2	退款已关闭	主动撤销退款，退款关闭
//-3	退款申请不通过	拒绝了本次退款申请,等待买家修改

        $orderRefundM     = new dataOrderRefund();
        if ($id){
            $orderRefundM = $orderRefundM -> get($id);
        } else{
            $orderRefundM -> order_goods_id = $order_goods_id;
        }

        $uid          = $_SERVER['uid'];
        $userS        = new UserService;
        $userInfo     = $userS          -> read($uid);
        $orderRefundM -> refund_status  = $refund_status;
        $orderRefundM -> action         = $action;
        $orderRefundM -> action_way     = $action_way;
        $orderRefundM -> action_userid  = $uid;
        $orderRefundM -> action_username= $userInfo['user_name'];
        $orderRefundM -> action_time    = time();
//
        $res          = $orderRefundM   -> save();
        if ($res <= 0){
            throw new Exception('操作失败','500');
        }
        return $res;
    }
    //操作order_goods表
    public function refundOrderGoodsTable($order_id,$order_goods_id,$refund_status,$refund_reason = '',$refund_require_money = 0){
        $orderGoodsM    = new dataOrderGoods();
        $orderGoodsM    = $orderGoodsM          -> get($order_goods_id);
        switch ($refund_status){
            case 1;
                $orderM         = new dataOrder();
                $orderInfo      = $orderM -> where('order_id','eq',$order_id) ->find();
                $orderGoodsM    -> refund_type          = $orderInfo -> payment_type;
                $orderGoodsM    -> refund_reason        = $refund_reason;
                $orderGoodsM    -> refund_require_money = $refund_require_money;
                break;
            case 2;
                $orderGoodsM    -> refund_status        = $refund_status;
                break;
            case 3;
                $orderGoodsM    -> refund_status        = $refund_status;
                break;
            case 4;
                $orderGoodsM    -> refund_status        = $refund_status;
                $orderGoodsM    -> where('order_goods_id','eq',$order_goods_id) -> find();


        }


        $res            = $orderGoodsM          -> save();
        if ($res <= 0){
            throw new Exception('操作失败','500');
        }
        return $res;
    }

    //操作order表
    function refundOrderTable($order_id,$refund_status,$action_way){
        $orderM     = new dataOrder();

        $orderM     = $orderM           -> get($order_id);
        switch ($action_way){
            case 1;
                $orderM     -> feedback_status  = $refund_status;
                break;
            case 2;


                $orderM     -> refund_status  = 2;
                break;

        }

        $res         = $orderM          -> save();

        if ($res <= 0){
            throw new Exception('操作失败','500');
        }
        return $res ;
    }

    //存入退货物流信息
    public function addRefundShipping($order_id,$order_goods_id,$refund_shipping_code,$refund_shipping_company){
        $orderGoodsM     = new dataOrderGoods();
        $orderGoodsM     = $orderGoodsM              -> get($order_goods_id);
        $orderGoodsM     -> refund_shipping_code     = $refund_shipping_code;
        $orderGoodsM     -> refund_shipping_company  = $refund_shipping_company;
        $orderGoodsM     -> refund_status            = 3;
        $res             = $orderGoodsM              -> save();
        if ($res <= 0){
            throw new Exception('操作失败','500');
        }
        $orderRefundM    = new dataOrderRefund();
        $orderRefundData = $orderRefundM  -> where('order_goods_id','eq',$order_goods_id) -> field('id') -> find();
        $refund_status   = 3;
        $action          = '卖家已发货,等待卖家确认收货';
        $this -> orderRefundTable($order_goods_id,$refund_status,$action,$action_way = 1, $orderRefundData -> id );
        $this -> refundOrderTable($order_id,$refund_status,$action_way);
        return $res;
    }

    //卖家退货订单审批
    public function refundApproval($refund_id,$order_id,$order_goods_id,$refund_status){

//        $orderRefundM   = new dataOrderRefund();
//        $orderRefundM   = $orderRefundM -> get($refund_id);
//        $orderRefundM   -> refund_status = $refund_status;
        $action_way = 2;//1 买家 2 卖家
        switch ($refund_status){
            case 2;
                $action     = '2';
                break;
            case 4;
                $action     = '卖家确认收货并同意退款';
            //todo 同意退款,计算需要退的金额


        }
        $this   -> refundOrderGoodsTable($order_id,$order_goods_id,$refund_status);
        $this   -> orderRefundTable($order_goods_id,$refund_status,$action,$action_way, $refund_id);
    }
    //确认收货
    public function affirmOrder($order_id){
        $orderM      = new dataOrder();
        $orderM      -> startTrans();
        $orderM      = $orderM -> get($order_id);
        $orderM      -> order_status     = 3;
        $orderM      -> shipping_status  = 2;
        $orderRes    = $orderM -> save();
        $orderDetail = $this     -> getDetail($order_id);
        $data       =[
            'order_status' => 3,
            'shipping_status'     => 2,
        ];
        $orderGoodsM    = new dataOrderGoods();
        $orderGoodsM    -> startTrans();
        $orderGoodsRes  = $orderGoodsM -> where('order_id','eq',$order_id) -> update($data);
        /****************************************************确认收货后，形成空内容订单商品评论记录****************************************************/
        //需要考虑定时事件
        /****************************************************确认收货后，形成空内容订单商品评论记录****************************************************/

        $orderGoods = $orderGoodsM ->where(['order_id'=>$order_id,'order_status' => 3])->field('order_goods_id,order_id,buyer_id,shop_id,goods_id,goods_name')-> select();

        $orderGoods = collection($orderGoods) ->toArray();
//        foreach ($orderGoods as $k => $v){
//            $arr[] = $v['order_id'];
//        }
//        $arr = array_unique($arr);
//        $ordergoods   = $orderGoodsM -> where('order_id' ,'in', $arr)->field('goods_id') -> select();
        print_r($orderGoods);die;
        $time = time();
        foreach ($ordergoodsid as $k => $v){

        }
//        $data = array(
//            'uid' => $uid,
//            'order_goods_id' =>,
//            'level' => 0,
//            'create_time' => $time,
//            'status' => 0
//        );
//        if (isset($param['page'])){
//            $page = $param['page'];
//            $offset = ($page - 1)*10;
//        }else{
//            $offset = 0;
//        }
//        $orderList = $orderM -> where($where)->limit($offset,10) -> select();
//        $orderGoodsData     = $this -> getOrderGoodsDetail($orderId);
        //根据不同订单将相应的商品放入订单树下
        foreach($orderList as $orderList_key => $orderList_value){
            print_r($orderList_value);
//            foreach($orderGoodsData as $goods_key => $goods_value){
//                if ($orderList_value['order_id'] == $goods_value['order_id']){
//                    $orderList[$orderList_key]['goods_list'][] = $goods_value;
//                }
//            }
        }
        die;
        $res = $orderList[$orderList_key]['goods_list']['goods_id'];
//        return $res;
        return $orderList;

        if($orderRes > 0 && $orderGoodsRes>0){
            $orderM         -> commit();
            $orderGoodsM    -> commit();
            return 1;
        }else{
            $orderM         -> rollback();
            $orderGoodsM    -> rollback();
        }



    }



}