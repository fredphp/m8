<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\RewardConfig;   // 小白
use App\Events\UserLoginEvent;
use App\Events\UserRegisterEvent;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\UserService;
use App\Services\UserWalletService;
use App\Models\UserWallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Jenssegers\Agent\Agent;

class LoginController extends ApiController
{
    //登陆注册

    public $agent;
    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    //滑块验证
    public function sliderVerify(Request $request)
    {
        if ($res = $this->verifyField($request->all(),[
            'account' => 'required',
            'datas' => 'required',
//            'signature' => 'required|string',
//            'timestamp' => 'required',
            'slider_type' => 'required|string|in:register,login',
        ])) return $res;

        $slider_type = $request->input('slider_type');
        $account = $request->input('account');
        $datas = $request->input('datas');
//        $datas = [ 1, 2, 3,4,5,6];
        $size = sizeof($datas);
        $sum = 0;
        foreach ($datas as $item){
            $sum += $item;
        }
        $avg = $sum * 1.0 / $size;

        $sum2 = 0;
        foreach ($datas as $item){
            $sum2 += pow($item - $avg,2);
        }

        $stddev = $sum2 / $size;
//        dd($stddev,$sum,$sum2,$avg);
        if($stddev){
            $token = getSliderToken($account,$slider_type);
            return $this->success('验证成功',['token' => $token]);
        }
        return $this->error(0,'验证失败');
    }

    //发送注册短信验证码
    public function sendSmsCode(Request $request,User $user)
    {
        if ($vr = $this->verifyField($request->all(),[
            'country_code' => 'required|string', //国家代码
            'phone' => 'required|string',
            'type' => 'integer|in:1', //1注册验证码
            'token' => '',
        ])) return $vr;

        $account = $request->input('phone');
        // if($this->agent->isDesktop()){
        //     $token = $request->input('token');
        //     if( ($checkResult = checkSliderVerify($account,$token)) !== true ) return $this->error(0,$checkResult);
        // }

        $type = $request->input('type',1);
        if($type == 1){
            //注册验证码
            if ($user->getUserByPhone($account)) return $this->error(0,'账号已被注册');
        }

        $sendResult = sendCodeSMS($account,'',$request->country_code);
        if ($sendResult === true){
            return $this->success();
        }
        return $this->error(4001,$sendResult);
    }

    public function sendSmsCodeBeforeLogin(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'signature' => 'required|string',
        ])) return $vr;

        $signature = $request->input('signature');
        $data = getSignatureData($signature);
        if($data === false) return $this->error(0,'验证已过期');
        $user = User::query()->findOrFail($data['user_id']);

        $sendResult = sendCodeSMS($user['phone'],'',$user['country_code']);
        if ($sendResult === true){
            return $this->success();
        }
        return $this->error(4001,$sendResult);
    }

    public function sendEmailCodeBeforeLogin(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'signature' => 'required|string',
        ])) return $vr;

        $signature = $request->input('signature');
        $data = getSignatureData($signature);
        if($data === false) return $this->error(0,'验证已过期');
        $user = User::query()->findOrFail($data['user_id']);

        $sendResult = sendEmailCode($user['email']);
        if ($sendResult === true){
            return $this->success();
        }
        return $this->error(4001,$sendResult);
    }

    //发送注册邮箱验证码
    public function sendEmailCode(Request $request,User $user)
    {
        if ($vr = $this->verifyField($request->all(),[
            'email' => 'required|string',
            'type' => 'integer|in:1', //1注册验证码
            'token' => '',
        ])) return $vr;
        
        $account = $request->input('email');
        
        // if($this->agent->isDesktop()){
        //     $token = $request->input('token');
        //     if( ($checkResult = checkSliderVerify($account,$token)) !== true ) return $this->error(0,$checkResult);
        // }

        $type = $request->input('type',1);
        if($type == 1){
            //注册验证码
            if ($user->getUserByEmail($request->email)) return $this->error(0,'账号已被注册');
        }

        $sendResult = sendEmailCode($request->email);
       
        if ($sendResult === true){
            return $this->success();
        }
        return $this->error(4001,$sendResult);
    }

    public function register(Request $request,User $user)
    {
        if ($vr = $this->verifyField($request->all(),[
            'type' => 'required|in:1,2', // 注册类型 1手机注册 2邮箱注册
            'country_code' => 'required_if:type,1', //国家代码
            'account' => 'required|string', //账号
            'code' => 'required|string', //验证码
            'password' => 'required|string|confirmed:password_confirmation|min:8|max:16', //密码
            'password_confirmation' => 'required', //确认密码
            //'invite_code' => 'required', //邀请码
        ])) return $vr;
        //var_dump($vr);exit;
        $params = $request->all();

        $type = $request->input('type',1);
        $account = $request->input('account');

        $lockKey = 'user_register_keylock:' . $account;
        if (!$this->setKeyLock($lockKey,3)) return $this->error();

        if($type == 1){ //手机注册
            if ($user->getUserByPhone($account)) throw new ApiException('账号已被注册');

            $checkResult = checkSMSCode($account,$request->code,'',$request->country_code);
            if ($checkResult !== true) return $this->error(4001,$checkResult);
        }else{ // 邮箱注册
            if ($user->getUserByEmail($account)) throw new ApiException('账号已被注册');

            $checkResult = checkEmailCode($account,$request->code);
            if ($checkResult !== true) return $this->error(4001,$checkResult);
        }

        DB::beginTransaction();
        try{

            $data['account'] = $params['account'];
            $data['account_type'] = $type;
            if($type == 1){
                $data['country_id'] = $params['country_id'];
                $data['country_code'] = $params['country_code'];
                $data['phone'] = $params['account'];
                $data['phone_status'] = 1;
            }else{
                $data['email'] = $params['account'];
                $data['email_status'] = 1;
                if (array_key_exists('phone',$params)){ //存在手机号就保存
                    $data['country_id'] = $params['country_id'];
                    $data['country_code'] = $params['country_code'];
                    $data['phone'] = $params['phone'];
                    $data['phone_status'] = 1;
                }
            }

            $data['username'] = $params['account'];
            $data['reg_ip'] = $request->getClientIp();
            $data['invite_code'] = User::gen_invite_code();
            $data['password'] = $user->passwordHash($request->password);
            $loginCode = User::gen_login_code(6);
            $data['login_code'] = $loginCode;
            $data['last_login_time'] = Carbon::now()->toDateTimeString();
            $data['last_login_ip'] = $data['reg_ip'];

            //邀请注册
            if($invite_code = $request->input('invite_code')){
                $parent_user = User::query()->where('invite_code',$invite_code)->first();
                if(!$parent_user){
                    throw new ApiException('不存在该邀请码');
                }
                $data['pid'] = $parent_user['user_id'];
                $data['deep'] = $parent_user['deep'] + 1;
                if($parent_user['is_agency'] == 1){
                    //上级用户是代理 则新注册用户的代理人是上级用户
                    $data['referrer'] = $parent_user['user_id'];
                }else{
                    //上级用户不是代理
                    if($parent_user['referrer']){
                        $data['referrer'] = $parent_user['referrer'];
                    }else{
                        $data['referrer'] = 0;
                    }
                }
            }else{
                $data['referrer'] = 0;
                $data['pid'] = 0;
                $data['deep'] = 0;
            }

            //创建用户
            $user = User::query()->create($data);
            
            // 创建用户钱包
            $result3 = (new UserWalletService())->createWallet($user);
            //var_dump($result3);exit;
            //用户注册事件
            event(new UserRegisterEvent($user));

            // 发放奖励
//            $this->registration_award($user,$result3);
            
            DB::commit();

            $return['token'] = auth('api')->claims(['login_code'=>$loginCode])->fromUser($user);
            $return['user'] = $user;
            
            return $this->successWithData($return);

        }catch (\Exception $e){
            DB::rollBack();
            return $this->error(0,$e->getMessage());
        }
    }

    // 小白
    public function registration_award($user,$result3)
    {
        if(empty($result3)){
            throw new ApiException('钱包创建异常');
        }
        if(empty($user)){
            throw new ApiException('账号创建异常');
        }
        // 获取用户AXN账号
        $coin_id = 0;
        foreach($result3 as $k=>$v){
            if($v['coin_name'] == 'USDT'){
                $coin_id = $v['coin_id'];
            }
        }
        if(empty($coin_id)){
            throw new ApiException('钱包创建异常1005');
        }

        // 获取配置
        $reward = RewardConfig::query()->where(['status'=>'1'])->first();
        if(!empty($reward)){
            // 发送注册用户奖励
            $user->update_wallet_and_log($coin_id, 'usable_balance', $reward->register_reward,UserWallet::asset_account, 'register_send_stai');

            // 获取上级用户
            $invit_user = User::query()->where(['user_id'=>$user->pid])->first();
            if($invit_user){
                // 获取钱包
                $invit_wallet = UserWallet::query()->where(['coin_name'=>'USDT','user_id'=>$invit_user->user_id])->first();
                if($invit_wallet){
                    $invit_user->update_wallet_and_log($invit_wallet->coin_id, 'usable_balance', $reward->register_sup_reward,UserWallet::asset_account, 'register_invitation');
                }
            }

            // 获取上级的上级
            $team_user = User::query()->where(['user_id'=>$invit_user->pid])->first();
            if($team_user){
                // 获取钱包
                $team_wallet = UserWallet::query()->where(['coin_name'=>'USDT','user_id'=>$team_user->user_id])->first();
                if($team_wallet){
                    $team_user->update_wallet_and_log($team_wallet->coin_id, 'usable_balance', $reward->register_team_reward,UserWallet::asset_account, 'register_invitation');
                }
            }
        }
    }

    // 代理注册
    public function agent_register(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'email' => 'required|string', //登录账号 这里是邮箱
            'name' => 'required|string', //昵称
            'code' => 'required|string', //验证码
            'password' => 'required|string|confirmed:password_confirmation|min:8|max:16', //密码
            'password_confirmation' => 'required', //确认密码
            'subscribe_rebate_rate' => 'required', // 申购返佣比率
            'contract_rebate_rate' => 'required', // 合约返佣比率
            'option_rebate_rate' => 'required', // 期权返佣比率
            //'invite_code' => 'required', //邀请码
        ])) return $vr;

        $params = $request->all();

        $account = $request->input('email');

        $lockKey = 'agent_register_keylock:' . $account;
        if (!$this->setKeyLock($lockKey,3)) return $this->error();

        $checkResult = checkEmailCode($account,$request->code);
        if ($checkResult !== true) return $this->error(4001,$checkResult);

        $is_exist = \App\Models\Agent::query()->where(['is_agency'=>1,'username'=>$account])->exists();
        if($is_exist) return $this->error(0,'账号已经存在');

        DB::beginTransaction();
        try{
            $data['username'] = $account;
            $data['name'] = $params['name'];
            $data['subscribe_rebate_rate'] = $params['subscribe_rebate_rate'];
            $data['contract_rebate_rate'] = $params['contract_rebate_rate'];
            $data['option_rebate_rate'] = $params['option_rebate_rate'];
            $data['invite_code'] = User::gen_invite_code();
            $data['password'] = Hash::make($params['password']);
            $data['is_agency'] = 1;
            $data['status'] = 0;

            //邀请注册
            if($invite_code = $request->input('invite_code')){
                $parent_user = \App\Models\Agent::query()->where('is_agency',1)->where('invite_code',$invite_code)->first();
                if(!$parent_user){
                    throw new ApiException('不存在该代理邀请码');
                }
                $data['referrer'] = $parent_user['user_id'];
                $data['pid'] = $parent_user['user_id'];
                $data['deep'] = $parent_user['deep'] + 1;

                // 代理条件限制
                if($params['subscribe_rebate_rate'] > $parent_user['subscribe_rebate_rate']
                    || $params['contract_rebate_rate'] > $parent_user['contract_rebate_rate']
                    || $params['option_rebate_rate'] > $parent_user['option_rebate_rate']
                ){
                    return $this->error(0,'代理条件不能高于上级');
                }
            }else{
                $data['referrer'] = 0;
                $data['pid'] = 0;
                $data['deep'] = 0;
            }

            //创建代理
            $agent = \App\Models\Agent::query()->create($data);

            $agent->update(['id'=>$agent['user_id']]);

            DB::table("agent_admin_role_users")->insert(["role_id"=>2,"user_id"=>$agent['user_id']]);

            DB::commit();

            return $this->success();

        }catch (\Exception $e){
            DB::rollBack();
            return $this->error(0,$e->getMessage());
        }
    }

    public function login(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'type' => 'required|integer|in:1,2',
            'account' => 'required|string',
//            'country_code' => 'required_if:type,1', //国家代码
            'password' => 'required|string',
            'token' => '', //滑块验证token
        ])) return $vr;

        $account = $request->input('account');
        // if($this->agent->isDesktop()){
        //     $token = $request->input('token');
        //     if( ($checkResult = checkSliderVerify($account,$token,'login')) !== true ) return $this->error(0,$checkResult);
        // }

        $type = $request->input('type',1);
//        $password = rsa_decode($request->password);
        $password = $request->password;
        if ($type == 1){
            $account_credentials = ['phone' => $request->account, 'password' => $password];
        }else{
            $account_credentials = ['email' => $request->account, 'password' => $password];
        }

        if (!(auth('api')->attempt($account_credentials))) {
            return $this->error(0,'账号或密码错误');
        }

        if ($type == 1){
            $user = User::query()->where('phone',$request->account)->firstOrFail();
        }else{
            $user = User::query()->where('email',$request->account)->firstOrFail();
        }

        //二次验证
        if($user['second_verify']){
            //二次验证
            $user = $user->toArray();
            $verify_data = array_only($user,['user_id','country_code','phone','phone_status','email','email_status','google_token','google_status']);

            $signature = generateSignature(['user_id'=>$user['user_id'],'account'=>$account]); //签名
            $verify_data['signature'] = $signature;
            return $this->error(1021,'二次验证',$verify_data);
        }else{
            $login_code = User::gen_login_code();
            $token = auth('api')->claims(['login_code' => $login_code])->fromUser($user);

            $updateData = ['last_login_ip'=>$request->getClientIp(),'login_code'=>$login_code,'last_login_time'=>Carbon::now()->toDateTimeString()];
            $user->update($updateData);

            //用户登陆事件
            event(new UserLoginEvent($user));

            $data['user'] = $user;
            $data['token'] = $token;

            return $this->successWithData($data,'登录成功');
        }
    }

    public function loginConfirm(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'signature' => 'required|string',
            'code' => 'required|string',
            'code_type' => 'required|integer|in:1,2,3', //二次验证Code类型 1手机 2邮箱 3谷歌验证器
        ])) return $vr;

        $signature = $request->input('signature');
        $data = getSignatureData($signature);
        if($data === false) return $this->error(0,'验证已过期');
        $user = User::query()->findOrFail($data['user_id']);
        //登陆二次验证
        $code = $request->input('code');
        $code_type = $request->input('code_type',1);
        $userService = new UserService();
        $checkResult = $userService->verifyCode($user,$code_type,$code);
        if ($checkResult !== true) return $this->error(4001,$checkResult);

        //登陆成功
        $login_code = User::gen_login_code();
        $token = auth('api')->claims(['login_code' => $login_code])->fromUser($user);
        $updateData = ['last_login_ip'=>$request->getClientIp(),'login_code'=>$login_code,'last_login_time'=>Carbon::now()->toDateTimeString()];
        $user->update($updateData);

        //用户登陆事件
        event(new UserLoginEvent($user));

        //签名过期
        forgetSignature($signature);

        $return['user'] = $user;
        $return['token'] = $token;
        return $this->successWithData($return,'登录成功');
    }

//    public function verifyLogin(Request $request)
//    {
//        if ($vr = $this->verifyField($request->all(),[
//            'type' => 'required|string|in:1,2',
//            'account' => 'required|string', //账号
//            'country_code' => 'required_if:type,1', //国家代码
//            'code' => 'required|string', //验证码
//        ])) return $vr;
//
//        $type = $request->input('type',1);
//
//        if($type == 1){
//            $user = User::query()->where('phone',$request->account)->first();
//            if(blank($user)) return $this->error(0,'用户不存在');
//
//            $checkResult = checkSMSCode($request->account,$request->code,'',$request->country_code);
//            if ($checkResult !== true) return $this->error(4001,$checkResult);
//        }else{
//            $user = User::query()->where('email',$request->account)->first();
//            if(blank($user)) return $this->error(0,'用户不存在');
//
//            $checkResult = checkEmailCode($request->account,$request->code);
//            if ($checkResult !== true) return $this->error(4001,$checkResult);
//        }
//
//        $login_code = User::gen_login_code();
//        $token = auth('api')->claims(['login_code' => $login_code])->fromUser($user);
//
//        $updateData = ['last_login_ip'=>$request->getClientIp(),'login_code'=>$login_code,'last_login_time'=>Carbon::now()->toDateTimeString()];
//        $user->update($updateData);
//
//        //用户登陆事件
//        event(new UserLoginEvent($user));
//
//        $data['user'] = $user;
//        $data['token'] = $token;
//
//        return $this->successWithData($data,'登录成功');
//    }

    public function logout()
    {
        auth('api')->logout();
        return $this->success('退出成功');
    }

}
