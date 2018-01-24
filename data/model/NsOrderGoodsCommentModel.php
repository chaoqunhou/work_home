<?php
/**
 *
 * =========================================================
 *
 * ----------------------------------------------
 * 官方网址: http://www.youshengxian.com
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */
namespace data\model;

use data\model\BaseModel as BaseModel;
 /**
 * 订单商品评论表
 *
 comment_id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键id',
 uid int(11) NOT NULL COMMENT '用户id',
 order_id int(11) NOT NULL DEFAULT '0' COMMENT '订单id',
 goods_id int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
 order_goods_id int(11) NOT NULL COMMENT '订单id',
 level int(11) NOT NULL COMMENT '商品id',
 text varchar(2000) NOT NULL COMMENT '评论内容',
 create_time int(11) DEFAULT '0' COMMENT '评论创建时间',
 status int(11) NOT NULL DEFAULT '0' COMMENT '评论状态 0未评论 1已评论',
 */
 
class NsOrderGoodsCommentModel extends BaseModel {

    protected $table = 'ns_order_goods_comment';
    protected $rule = [
        'comment_id'  =>  '',
    ];
    protected $msg = [
        'comment_id'  =>  '',
    ];

}