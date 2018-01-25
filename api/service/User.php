<?php
namespace app\api\service;

use app\api\api\RESTInterface;
use data\model\NsOrderGoodsPromotionDetailsModel;
use data\model\NsSmsModel as dataSmsSend;
use data\model\UserModel;
use think\Exception;
use think\Db;
use think\helper\hash\Md5;
use data\model\NsShopModel as dataShop;
use app\api\service\Login;

class User extends BaseService implements RESTInterface
{
    private $user_model = [];
    private static $key = '';

    public function __construct()
    {
        parent::__construct();
        $this->user_model = new UserModel();
    }

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
        $userM = new UserModel();
        $userInfo = $userM->where('uid', $id)->field('uid,user_name,user_tel,current_login_ip,real_name,nick_name')->find();
        if (empty($userInfo)) {
            throw new Exception('没有该用户信息,请重新登陆', '404');
        }
        $userInfo = $userInfo->toArray();

        return $userInfo;
    }

    public function editAction($id)
    {
        // TODO: Implement edit() method.
    }

    public function save()
    {
        // TODO: Implement save() method.
    }

    public function update($id)
    {
        // TODO: Implement update() method.
    }

    public function delete($id)
    {
        // TODO: Implement delete() method.
    }

    /**
     * @param $lenth 随机字符串的长度
     */
    public function randString($lenth)
    {
        $string = 'abcdefjhijklmnopqrstuvwxyZABCDEFJHIJKLMNOPQRSTUVWXYZ23456789';
        $randString = substr(str_shuffle($string), 0, $lenth);
        return $randString;
    }

    public function Vpwd($user_name, $user_password, $field = '*')
    {
        $userM = $this->user_model;
        $data = ['user_name' => $user_name, 'user_password' => $user_password];
        $this->vData($data, 'User.Vpwd');
        $userM->where($data)->field($field);
        $res = $userM::get();
        return empty($res) ? false : $res;
    }

    public function Tpwd($user_name, $user_password, $field = '*')
    {
        $userM = $this->user_model;
        $data = ['user_tel' => $user_name, 'user_password' => $user_password];
        $this->vData($data, 'User.Tpwd');
        $userM->where($data)->field($field);
        $res = $userM::get();
        return empty($res) ? false : $res;
    }

    /**
     * @param $registerInfo  注册用户的信息
     */
    public function register($registerInfo)
    {
        //验证两次密码输入是否一样
        $nameRes = $this->verifyUsername($registerInfo);
        if (!$nameRes) throw new Exception('用户名已存在', 300);
        $telRes = $this->verifyTel($registerInfo);
        if (!$telRes) throw new Exception('手机号已注册', 300);

        $codeRes = $this->verifyCode($registerInfo);

        if (!$nameRes && !$codeRes && !$telRes) {
        }
        $time = time();
        $userM = new UserModel();
        $data = [
            'user_tel' => $registerInfo['mobile'],
            'user_tel_bind' => 1,
            'current_login_ip' => $registerInfo['device_open_id'],
            'current_login_type' => $registerInfo['client_id'],
            'reg_time' => $time,
            'user_password' => md5($registerInfo['user_password']),
        ];
        $res = $userM->save($data);
        if ($res <= 0) {
            throw new Exception('注册失败', 500);
        }
        if ($res > 0) {
            $smsSendM = new SmsSend();
            $data = [
                'mobile' => $registerInfo['mobile'],
                'type_id' => 1
            ];
            $smsRes = $smsSendM->smsCode($data);
        }
        return $res;
    }

    /**
     * 验证用户名(帐号)是否存在
     */
    public function verifyUsername($verifyInfo)
    {
        $userM = new UserModel();
        $userData = $userM->where(['user_name' => $verifyInfo['user_name']])->find();
        if (!empty($userData)) {
            return false; //用户名已存在
        }
        return true;
    }

    /**
     * @param $verifyInfo
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function verifyTel($verifyInfo)
    {
        $userM = new UserModel();
        $userData = $userM->where(['user_tel' => $verifyInfo['mobile']])->find();
        if (!empty($userData)) {
            return false; //用户已注册
        }
        return true;
    }

    /**
     * @param $verifyInfo 需要验证的信息
     */
    public function verifyCode($verifyInfo)
    {
        $validateM = new dataSmsSend();
        $info = $validateM->where(['mobile' => $verifyInfo['mobile']])->order('sms_id', 'desc')->limit(1)->find();
        $time = time();

        $diff_time = $time - $info->send_time;
        if ($verifyInfo['code'] == $info->template_code && $diff_time <= 60) return true;

        else if ($verifyInfo['code'] != $info->template_code) throw new Exception('验证码错误', 300);//-1代表验证码错误

        if ($diff_time > 60) throw new Exception('验证码失效', 300); //-2代表验证码失效
    }

    /**
     * @param $verifyInfo 需要验证的信息
     * @param $status 0 修改密码  1 找回密码验证手机号成功
     * 验证密码的正确性
     * @return bool
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function verifyPassword($verifyInfo, $status = 0)
    {
        $userM = new UserModel();
        if ($status !== 0 && $verifyInfo['key'] == $status) {
            return true;
        }
        $userData = $userM->where(['user_password' => $verifyInfo['old_password']])->find();
        if (empty($userData)) {
            throw new Exception('旧密码错误', 300);
        }
        return true;
    }

//    public function   getIP(){
//        $ip=getenv( 'REMOTE_ADDR ');
//        $ip_   =   getenv( 'HTTP_X_FORWARDED_FOR ');
//        if(($ip_ != " ") && ($ip_ != "unknown ")){
//            $ip=$ip_;
//        }
//        return   $ip;
//    }

    public function changePassword($Info)
    {
        //判断是否有key值携带,如果有key值则进行直接修改新密码
        //========================直验证手机后修改密码================================
        if (!empty($Info['key'])) {
            $smsM = new dataSmsSend();
            $res = $smsM->where(['key' => $Info['key']])->find();
            if (empty($res)) {
                throw new Exception('key错误', 400);
            }
            $userM = new UserModel();
            $res = $userM->where(['user_tel' => $res->mobile])->update(['user_password' => $Info['new_password']]);
            if ($res > 0) {
                return $res;
            } else {
                throw new Exception('密码找回失败', 500);
            }
        }
        //========================直接修改密码================================
        $telRes = $this->verifyTel($Info);
        $passRes = $this->verifyPassword($Info);
        if ($telRes == false && $passRes) {
            $userM = new UserModel();
            $res = $userM->where(['user_tel' => $Info['mobile']])->update(['user_password' => $Info['new_password']]);
            if ($res > 0) {
                return $res;
            } else {
                throw new Exception('密码修改失败', 400);
            }
        }
    }

    /**
     * @param $verify 验证码
     * @return int
     * @throws Exception
     */
    public function findPasswordVerify($verify)
    {

//        $codeRes = $this -> verifyCode($verify);
        if (1) {
            $key = $this->randString(10);
            $smsM = new dataSmsSend();
            $data = ['key' => $key];
            $res = $smsM->where(['mobile' => $verify['mobile']])->update($data);
            //1代表验证码验证成功 跳转新密码输入页面
            if ($res > 0) {
                $data = [
                    'id' => $res,
                    'key' => $key,
                ];
                return $data;
            } else {
                throw new Exception('验证码验证失败', 400);
            }
        }
    }

    /**
     * @param $uid 用户ID
     * @param $invitecode 邀请码
     * @return object
     * @throws Exception
     */
    public function usershopBund($uid, $invitecode)
    {
        $shop_id = substr($invitecode, 0, -4);
        $shop_id = (int)$shop_id;
        $icd_bund = Db::table('sys_invite_code')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();
        $sub_bund = Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();
        $record = (empty($icd_bund) && empty($sub_bund)) ? 1 : 0;
        $data = array(
            'shop_id' => $shop_id, 'user_id' => $uid, 'invite_code' => $invitecode, 'record' => $record
        );
        $icd_exists = Db::table('sys_invite_code')->where(['invite_code' => $invitecode, 'shop_id' => $shop_id])->find();

        if (!empty($icd_exists)) {
            $shopM = new dataShop();
            $res = $shopM->where(['shop_id' => $shop_id])->find();
            if (!empty($sub_bund) && in_array($sub_bund['record'], [0, 1, 2])) {
                $res->bundinfo = "已经绑定过！";
            } else {
                if (empty($sub_bund)) {
                    Db::table('sys_shop_user_bund')->insert($data);
                } elseif ($sub_bund['record'] == 3) {
                    if (empty($icd_bund)) {
                        Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->setField('record', 1);
                    } else {
                        Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->setField('record', 0);
                    }
                }
                $bund = Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid, 'invite_code' => $invitecode])->find();
                if (!empty($bund) && in_array($bund['record'], [0, 1, 2])) {
                    $res->bundinfo = "绑定成功！";
                } else {
                    $res->bundinfo = "绑定不成功！";
                }
            }
        } else {
            throw new Exception('找不到相应商铺', '404');
        }

        return $res;
    }

    public function readShop($invitecode)
    {
        $shop_id = substr($invitecode, 0, -4);
        $shop_id = (int)$shop_id;
        $shopM = new dataShop();
        $icd = Db::table('sys_invite_code')->where(['invite_code' => $invitecode])->find();
        if (!empty($icd)) {
            $res = $shopM->where(['shop_id' => $shop_id])->find();
            if (empty($res)) throw new Exception('找不到相应商铺', '404');
        } else {
            throw new Exception('找不到相应商铺', '404');
        }

        return $res;
    }

    public function wxBunded($appid = 'wx005d6f7571e3013c', $code, $client_id, $device_open_id)
    {
        $openid = $this->getOpenid($appid, $code);
        $user = new UserModel();
        if (isset($openid))
            $userinfo = $user->getInfo(['wx_openid' => $openid], 'uid,user_name,user_password,user_tel,wx_openid');
        if (!empty($userinfo)) {
            $userinfo['bundinfo'] = '已绑定!';
            $uid = $userinfo['uid'];
            $login = new Login();
            $resdata = $login->getToken($uid, $client_id, $device_open_id);
            $userinfo['token'] = $resdata;
        } else {
            throw new Exception('未绑定或未注册', '401');
        }

        return $userinfo;
    }

    public function wxUserBund($appid = 'wx005d6f7571e3013c', $code, $tel, $client_id, $device_open_id)
    {
        $openid = $this->getOpenid($appid, $code);
        $user = new UserModel();
        if (isset($openid) && isset($tel)){
            $userinfo = $user->getInfo(['user_tel' => $tel], 'uid,user_name,user_password,user_tel,wx_openid');
            if (!empty($userinfo)) {
                $uid = $userinfo['uid'];
                $data = array('wx_openid' => $openid);
                $bund_status = $user->save($data, ['uid' => $uid]);
                $res_uid = $user->getInfo(['uid' => $uid], 'uid,user_name,user_password,user_tel,wx_openid');
                $res = $user->getInfo(['wx_openid' => $openid,'user_tel'=>$tel], 'uid,user_name,user_password,user_tel,wx_openid');
                if (!empty($res_uid) && !empty($res) && $res_uid == $res) $userinfo['bundinfo'] = '绑定成功!';
                $login = new Login();
                $resdata = $login->getToken($uid, $client_id, $device_open_id);
                $res['token'] = $resdata;

                return $res;
            } else {
                throw new Exception('未注册，需注册绑定', '402');
            }
        }
    }

    public function wxRegBund($Info)
    {
        $openid = $this->getOpenid($Info['appid'], $Info['code']);
        $this->register($Info);
        $tel = $Info['mobile'];
        $user = new UserModel();
        if (isset($openid) && isset($tel)){
            $userinfo = $user->getInfo(['user_tel' => $tel], 'uid,user_name,user_password,user_tel,wx_openid');
            if (!empty($userinfo)) {
                $uid = $userinfo['uid'];
                $data = array('wx_openid' => $openid);
                $bund_status = $user->save($data, ['uid' => $uid]);
                $res_uid = $user->getInfo(['uid' => $uid], 'uid,user_name,user_password,user_tel,wx_openid');
                $res = $user->getInfo(['wx_openid' => $openid,'user_tel'=>$tel], 'uid,user_name,user_password,user_tel,wx_openid');
                if (!empty($res_uid) && !empty($res) && $res_uid == $res) $userinfo['bundinfo'] = '绑定成功!';
                $login = new Login();
                $resdata = $login->getToken($uid, $Info['client_id'], $Info['device_open_id']);
                $res['token'] = $resdata;

                return $res;

            } else {
                throw new Exception('注册或绑定不成功！', '403');
            }
        }

    }

    public function vget($url)
    {//curl实现的get方法请求
        $info = curl_init();
        curl_setopt($info, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($info, CURLOPT_HEADER, 0);
        curl_setopt($info, CURLOPT_NOBODY, 0);
        curl_setopt($info, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($info, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($info, CURLOPT_URL, $url);
        $output = curl_exec($info);
        curl_close($info);
        return $output;
    }

    public function getOpenid($appid = 'wx005d6f7571e3013c', $code)
    {
        // 获取openid
        $appSecret = "2657944c215cd6340783838f673c9f4d";
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$appSecret.'&code='.$code.'&grant_type=authorization_code';
        $arr = $this->vget($url);  // 一个使用curl实现的get方法请求
        $arr = json_decode($arr, true);
        $openid = $arr['openid'];
        $session_key = $arr['session_key'];

        return $openid;
    }



    public function deleteUsBund($uid, $shop_id)
    {
        $usb = Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();

        if (!empty($usb)) {
            $shopM = new dataShop();
            $res = $shopM->where(['shop_id' => $shop_id])->field(['shop_id', 'shop_name'])->find();
            $res->uid = $uid;
            if (in_array($usb['record'], [0, 1, 2])) {
                Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->setField('record', 3);
                $usb_rec = Db::table('sys_shop_user_bund')->field('record')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();
                if ($usb_rec['record'] == 3) {
                    $res->bundinfo = "解除绑定成功！";
                } else {
                    $res->bundinfo = "解除绑定不成功！！！";
                }
            } else {
                $res->bundinfo = "已解除绑定！";
            }
        } else {
            throw new Exception('找不到相应商铺，或未绑定该商铺', '404');
        }

        return $res;
    }

    public function deleteWxBund($uid, $shop_id)
    {
        $usb = Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();

        if (!empty($usb)) {
            $shopM = new dataShop();
            $res = $shopM->where(['shop_id' => $shop_id])->field(['shop_id', 'shop_name'])->find();
            $res->uid = $uid;
            if (in_array($usb['record'], [0, 1, 2])) {
                Db::table('sys_shop_user_bund')->where(['shop_id' => $shop_id, 'user_id' => $uid])->setField('record', 3);
                $usb_rec = Db::table('sys_shop_user_bund')->field('record')->where(['shop_id' => $shop_id, 'user_id' => $uid])->find();
                if ($usb_rec['record'] == 3) {
                    $res->bundinfo = "解除绑定成功！";
                } else {
                    $res->bundinfo = "解除绑定不成功！！！";
                }
            } else {
                $res->bundinfo = "已解除绑定！";
            }
        } else {
            throw new Exception('找不到相应商铺，或未绑定该商铺', '404');
        }

        return $res;
    }

}