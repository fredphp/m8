<?php


namespace App\Services;


use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function verifySecurityCode($user,$codes)
    {
        if($user['phone_status'] == 1){
            if(blank($user['phone'])) return '手机未绑定';
            if(!isset($codes['sms_code']) || blank($codes['sms_code'])) return '手机验证码不能为空';
            $checkResult1 = checkSMSCode($user['phone'],$codes['sms_code'],'',$user['country_code']);
            if($checkResult1 !== true) return $checkResult1;
        }
        if($user['email_status'] == 1){
            if(blank($user['email'])) return '邮箱未绑定';
            if(!isset($codes['email_code']) || blank($codes['email_code'])) return '邮箱验证码不能为空';
            $checkResult2 = checkEmailCode($user['email'],$codes['email_code']);
            if($checkResult2 !== true) return $checkResult2;
        }
        if($user['google_status'] == 1){
            if(blank($user['google_token'])) return '谷歌验证未绑定';
            if(!isset($codes['google_code']) || blank($codes['google_code'])) return '谷歌验证码不能为空';
            $checkResult3 = checkGoogleToken($user['google_token'],$codes['google_code']);
            if($checkResult3 !== true) return $checkResult3;
        }
        return true;
    }

    public function verifyCode($user,$code_type,$code)
    {
        if($code_type == 1){
            if(blank($user['phone'])) return '手机未绑定';
            $checkResult = checkSMSCode($user['phone'],$code,'',$user['country_code']);
        }elseif($code_type == 2){
            if(blank($user['email'])) return '邮箱未绑定';
            $checkResult = checkEmailCode($user['email'],$code);
        }else{
            if(blank($user['google_token'])) return '谷歌验证未绑定';
            $checkResult = checkGoogleToken($user['google_token'],$code);
        }
        return $checkResult;
    }

    public function getAuthInfo($user)
    {
        $auth = UserAuth::query()->where('user_id',$user['user_id'])->first();
        if(blank($auth)){
            $auth = [
                'primary_status' => 0,
                'status' => 0,
                'primary_status_text' => UserAuth::$primaryStatusMap[0],
                'status_text' => UserAuth::$statusMap[0],
            ];
        }
        return $auth;
    }

    public function primaryAuth($user,$params,$status = 1)
    {
        //验证证件真实性
//        if (! (new Identify())->verify($data['identify_name'],$data['identify_card'],$data['identify_sex'])) return -1;

        $find = UserAuth::query()->where(['id_card' => $params['id_card']])->where('user_id','!=',$user['user_id'])->first();
        if(!blank($find)) throw new ApiException('该证件已被认证');

        DB::beginTransaction();
        try{
            $params['user_id'] = $user['user_id'];
            $params['primary_status'] = 1;
            if ($status == 2){
                $params['status'] = 1;
            }
            $auth = UserAuth::where(['user_id' => $user['user_id']])->first();
            if ($auth){
                $auth->update($params);
            }else{
                $auth = UserAuth::insert($params);
            }
            $user->update(['user_auth_level' => $status]);

            DB::commit();
        }catch (\Exception $e){
            DB::rollBack();
            throw $e;
        }

        return $auth;
    }

    public function topAuth($user,$params)
    {
        $auth = UserAuth::query()->where('user_id',$user['user_id'])->first();
        if(blank($auth)) throw new ApiException('请先完成初级认证');
        if ($auth->status == UserAuth::STATUS_AUTH) throw new ApiException('已认证用户不可修改');

        $params['status'] = 1;
        return $auth->update($params);
    }
}
