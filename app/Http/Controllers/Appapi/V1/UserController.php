<?php

namespace App\Http\Controllers\Appapi\V1;

use App\Models\Advice;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\BankConfig;
use App\Models\BankImport;
use App\Models\Customer;
use App\Models\InsideTradeOrder;
use App\Models\InsideTradePair;
use App\Models\OptionPair;
use App\Models\OptionScene;
use App\Models\OptionTime;
use App\Models\UserAuth;
use App\Models\UserGrade;
use App\Models\UserWallet;
use App\Models\Withdraw;
use App\Models\Coins;
use App\Models\Recharge;
use App\Models\SustainableAccount;
use App\Models\TransferRecord;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\HuobiService\HuobiapiService;
use App\Services\UserService;
use App\Exceptions\ApiException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\models\UserCoinName;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;


class UserController extends ApiController
{
    public function test(){
//        dd((new Agent())->device());
//        $new_price_book_key = 'market:' . 'btcusdt' . '_newPriceBook';
//        $new_price_book = array_map(function($v){
//            return json_decode($v,true);
//        },\Illuminate\Support\Facades\Redis::lrange($new_price_book_key,0,-1));
////                        $new_price_book = Cache::store('redis')->get($new_price_book_key);
//        if(blank($new_price_book)) $new_price_book = [];
//        if(!blank($new_price_book)) $new_price_book = array_reverse($new_price_book);
//        $new_price_book = array_slice($new_price_book,-30,30);
////        array_slice($new_price_book,0,30);
//        dd($new_price_book);
//        dd(createWalletAddress(9,'BTC'));
//        dd(0.02001 + (rand(10,80) / 100000));
//        $sub = 'indexMarketList';
//        $type = str_before($sub,'_');
//        $params = str_after($sub,'_');
//        $symbol = str_before($params,'_');
//        dd($sub,$type,$params,$symbol);
//        $symbol = 'btcusdt';
//        $period = '1min';
//        $kline_book_key = 'market:' . $symbol . '_kline_book_' . $period;
////        $kline_book = Cache::store('redis')->get($kline_book_key);\
//        $kline_book = \Illuminate\Support\Facades\Redis::lrange($kline_book_key,0,1);
//        dd($kline_book_key,$kline_book);
//        $option_pairs = OptionPair::query()->where('status',1)->select(['symbol','quote_coin_name','base_coin_name'])->get()->toArray();
//        $exchange_pairs = InsideTradePair::query()->where('status',1)->select(['symbol','quote_coin_name','base_coin_name'])->get()->toArray();
//        $tmp_arr = array_merge($option_pairs,$exchange_pairs);
//        $pairs = collect($tmp_arr)->unique(function ($item){
//            return $item['symbol'];
//        })->toArray();
//        dd($pairs);
//        $data = InsideTradePair::query()->get()->groupBy('quote_coin_name')->toArray();
//        dd($data);
//        $scene = OptionScene::query()->findOrFail(1);
//        $odds_arr = array_collapse([$scene['up_odds'],$scene['down_odds'],$scene['draw_odds']]);
//        $odds = array_first($odds_arr, function ($value, $key) {
//            return $value['uuid'] == '368a9ea9-22a8-4434-ab9b-a54a93729d93';
//        });
//        dd($odds);
//        dd(array_collapse([['q'=>1,'w'=>2],['a'=>'a']]));
//        dd(str_after('btcusdt.trade','.'));
//        $data = InsideTradePair::query()->get();
//        dd($data->groupBy('quote_coin_name')->toArray());
//        dd(Cache::store('redis')->tags('market_asdf')->put('market_asdf3','market_asdf3'));
//        dd(Cache::store('redis')->tags('market_asdf')->get('market_asdf'));
//        $ch = "market.ethbtc.kline.1mon";
//        $ch = "market.btcusdt.mbp.refresh.20";
//        $ch = "market.btcusdt.detail";
//        $pattern_kline = '/^market\.(.*?)\.kline\.([\s\S]*)/';
//        $pattern_depth = '/^market\.(.*?)\.mbp\.refresh\.20$/';
//        $pattern_detail = '/^market\.(.*?)\.detail$/'; //市场概要
//        dd(preg_match($pattern_detail, $ch, $match),$match);
//        dd(json_decode(cache::store('redis')->get('btcusdt_depth'),true));
//        dd(cache::store('file')->get('btcusdt_newPrice'));
//        dd(cache::store('file')->put('btcusdt',[1,2,3,4,5]));
//        dd( (new HuobiapiService())->getAllRecords() );
//        dd(OptionTime::all()->toArray());
//        $sub = ['q','w','e','r'];
//        dd(array_pluck($sub,'w'),$sub);
//        $pair = OptionPair::query()->find(1);
//        $symbol = strtolower($pair['base_coin_name']) . strtolower($pair['quote_coin_name']);
//        $market_trade = (new HuobiapiService())->getMarketTrade($symbol);
//        dd($market_trade);
//        dd(gettype($market_trade),$market_trade);
//        return $this->successWithData($market_trade);
//        dd(request()->allFiles());
//        dd(file_get_contents("https://api.hadax.com/market/trade?symbol=ethusdt"));
//        dd(file_get_contents("https://api.huobi.pro/market/trade?symbol=ethusdt"));
//        $after_encode_data = 'oxOd68uPP/REmEoEnJoS2Rk/ka8gSuWaTnpTEup7aJ/7LW5grjHAiFlMkHSIoCEcvLxRkST/CAX/7hUZ40tjxZFNCiXLUaOZEPDTbFaYcySx4Dslx8AlLeLdOcNKE7DsZlpnRe0MSI9QMNmkePbaYEScyzzczF6+m3UYnn2KhHI=';

//        $decode_result = rsa_decode($after_encode_data);
//        dd($decode_result);
//        $times = OptionTime::query()->where('status',1)->get();
//        dd($times->toArray());
//        $start = Carbon::now();
//        $end = Carbon::now()->addSeconds(300);
//        $range = date_range($start,$end,300);
//        dd($range);

//        dd(request()->all());
//        $phone = '18617004850';
//        dd(sendCodeSMS($phone));
//        $email = '351843463@qq.com';
//        dd(sendEmailCode($email));

//        $user = User::query()->find(3);
//        dd($user->update_wallet_and_log(1,'usable_balance',-10,UserWallet::option_account,'option_order_delivery'));
//        $account_class = array_first(UserWallet::$accountMap,function ($value, $key) {
//            return $value['id'] == 2;
//        });
//        $account = new $account_class['model']();
//        dd($account::$richMap);
//        dd($account);
           // $result=UserCoinName::query()->where([ 'coin_id' => $coin_id])->firstOrFail();


//        dd(Cache::put('testkey','123456',10));
//        $scene_id = 123;
//        dd(Cache::store('redis')->put('testkey:'.$scene_id,$scene_id,8));
//        $market_trade = [
//            "ch"=> "market.btcusdt.trade.detail",
//            "status"=> "ok",
//            "ts"=> 1593242272637,
//            "tick"=> [
//                "id"=> 109242365299,
//                "ts"=> 1593242272481,
//                "data"=> [
//                    [
//                        "id"=> 1.0924236529944560206305051e+25,
//                        "ts"=> 1593242272481,
//                        "trade-id"=> 102152942444,
//                        "amount"=> 0.27,
//                        "price"=> 200,
//                        "direction"=> "sell"
//                    ],
//                    [
//                        "id"=> 1.0924236529944560206305051e+35,
//                        "ts"=> 1593242272482,
//                        "trade-id"=> 102152942445,
//                        "amount"=> 0.29,
//                        "price"=> 100,
//                        "direction"=> "sell"
//                    ]
//                ]
//            ]
//        ];
//        $trade_data = $market_trade['tick']['data'];
//        $price_arr = Arr::pluck($trade_data,'price');
//        $new_price = PriceCalculate(array_sum($price_arr) ,'/', count($price_arr));
//dd($new_price);
//        $url = 'https://api.hadax.pro/market/trade?symbol=btcusdt';
//        $info = @file_get_contents($url);
//        dd($info);
//        return $info;
//        $data = (new HuobiapiService())->getMarketTrade('btcusdt');
//        dd($data);
    }

    //获取用户信息
    public function getUserInfo()
    {
        $user = $this->current_user();

        return $this->successWithData($user);
    }

    public function question(Request $request)
    {
        $user = $this->current_user();
        $msg = trim($request->msg);
        if (empty($msg)) {
            return $this->error(1002020,'请填写消息');
        }
        $c = new Customer();
        $c->user_id = $user->user_id;
        $c->question = $msg;
        if (!$c->save()) {
            return $this->fail(1002020,'提交失败！');
        }
        return $this->success();
    }

    public function repleList(Request $request)
    {
        $user = $this->current_user();
        $list = Customer::where(['user_id' => $user->user_id])->orderBy('updated_at', 'desc')->select('question', 'reply', 'created_at')->paginate(10);
        return $this->successWithData($list);
    }

    public function addRecharge(Request $request)
    {
        $user = $this->current_user();
        $data = $request->post();
        $validator = Validator::make($data, [
//            'coin_name' => 'required|in:BTC,ETH,USDT',
            'number' => 'required',
            'type' => 'required|in:1,2'
        ], [
//            'coin_name.required' => '请选择币种',
            'number.required' => '请输入数量',
            'type.required' => '请选择充值类型'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        if (floatval($data['number']) < 0.0000001) {
            return $this->error(1002020,'最小数量为0.0000001');
        }
        if (BankImport::where('user_id', $user->id)->whereIn('status', [0, 1, 2])->exists()) {
            return $this->error(10002020,'您有未完成的充值记录');
        }
        $new = new BankImport();
        $new->user_id = $user->user_id;
        $new->coin_name = 'USDT';
        $new->number = $data['number'];
        $new->type = $data['type'];
        $new->status = 0;
        $scale = BankConfig::where('id', 1)->value('u_ja');
//        if ($data['coin_name'] != 'USDT') {
//            $price = Market::where('name', strtolower($data['coin_name']) . 'usdt')->value('now');
//            $new->zhe_number = round($price * $data['number'] * $scale, 2);
//        } else {
            $new->zhe_number = round($data['number'] / $scale , 2);
//        }
        if (!$new->save()) {
            return $this->error(1002020,'提交失败');
        }
        return $this->success();
    }

    public function rechargeList(Request $request)
    {
        $user = $this->current_user();
        $list = BankImport::where(['user_id' => $user->user_id])->orderBy('id', 'desc')->select(['id', 'coin_name', 'type', 'number', 'zhe_number', 'type', 'bank_name', 'bank_branch', 'bank_branch_code', 'bank_number', 'bank_username', 'qr_url', 'status', 'created_at'])->paginate(10);
        foreach ($list->items() as $item) {
            if ($item->status != 1) {
                $item->bank_name = '';
                $item->bank_branch = '';
                $item->bank_branch_code = '';
                $item->bank_number = '';
                $item->bank_username = '';
                $item->qr_url = '';
            }else{
                $item->qr_url = 'https://api.bicsbank.vip/'.$item->qr_url;
            }
        }
        return $this->successWithData($list);
    }

    public function successPay(Request $request)
    {
        $user = $this->current_user();
        $id = $request->id;
        $bankImport = BankImport::where(['id' => $id, 'user_id' => $user->user_id, 'status' => 1])->first();
        if (!$bankImport) {
            return $this->error(1002020,'状态不正确!');
        }
        $bankImport->status = 2;
        $bankImport->save();
        return $this->success();
    }

    public function getScale(Request $request)
    {
//        $user = $request->user();
//        $coin_name = $request->coin_name;
        $number = $request->number;
        $scale = BankConfig::where('id', 1)->value('u_ja');
//        if ($coin_name != 'USDT') {
//            $price = Market::where('name', strtolower($coin_name) . 'usdt')->value('now');
//            $zhe_number = round($price * $number * $scale, 2);
//        } else {
            $zhe_number = round($number / $scale  , 2);
//        }
        return $this->successWithData(['zhe_number' => $zhe_number]);
    }

    //修改用户信息
    public function updateUserInfo(Request $request)
    {
        if ($res = $this->verifyField($request->all(),[
            'avatar' => '',
            'username' => 'string|min:1|max:30',
            'second_verify' => 'integer|in:0,1',
        ])) return $res;

        $params = $request->all();
        if(blank($params)) return $this->error('缺少参数');

        $user = $this->current_user();

        $res = $user->update($params);
        if(!$res){
            return $this->error();
        }
        return $this->successWithData($user);
    }

    //登陆二次验证开关
    public function switchSecondVerify()
    {
        $user = $this->current_user();

        $second_verify = $user->second_verify;

        $user->second_verify = $second_verify == 1 ? 0 : 1;
        $user->save();
        return $this->successWithData(['second_verify' => $user['second_verify']]);
    }

    public function addAdvice(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'phone' => 'string',
            'email' => 'string|email',
            'realname' => 'string',
            'contents' => 'required|string',
            'imgs' => '',
        ])) return $vr;

        $user = $this->current_user();

        $params = $request->only(['contents','email','phone','realname','imgs']);
        $params['user_id'] = $user['user_id'];

        $res = Advice::query()->create($params);

        if(!$res){
            return $this->error();
        }
        return $this->success();
    }

    public function advices()
    {
        $user = $this->current_user();

        $advices = Advice::query()->where(['user_id'=>$user['user_id']])->latest()->paginate();

        return $this->successWithData($advices);
    }

    public function adviceDetail(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'id' => 'required|integer',
        ])) return $vr;

        $user = $this->current_user();

        $advice = Advice::query()->where(['user_id'=>$user['user_id'],'id'=>$request->id])->firstOrFail();

        return $this->successWithData($advice);
    }

    //用户消息通知数量统计
    public function myNotifiablesCount(Request $request)
    {
        $user = $this->current_user();

        $count = [];

        $count['total'] = $user->unreadNotifications()->count();

        return $this->successWithData($count);
    }

    public function myNotifiables(Request $request)
    {
        $user = $this->current_user();

        $notifiables = $user->notifications()->latest()->paginate();
        foreach ($notifiables->items() as &$item) {
            $returnData['title'] = __($item->data['title']);
            $returnData['content'] = __($item->data['content']);
            $item->data = $returnData;
        }

        //获取列表 全部标记已读
        $user->unreadNotifications->markAsRead();

        return $this->successWithData($notifiables);
    }

    public function batchReadNotifiables(Request $request)
    {
        $user = $this->current_user();

        //全部标记已读
        $user->unreadNotifications->markAsRead();

        return $this->success();
    }

    public function readNotifiable(Request $request)
    {
        if ($vr = $this->verifyField($request->all(),[
            'id' => 'required',
        ])) return $vr;

        $user = $this->current_user();

        $notifiable = $user->notifications()->where('id',$request->id)->firstOrFail();
        $returnData['title'] = __($notifiable->data['title']);
        $returnData['content'] = __($notifiable->data['content']);
        $notifiable->data = $returnData;
        //标记消息为已读
        $notifiable->markAsRead();

        return $this->successWithData($notifiable);
    }

    //获取认证信息
    public function getAuthInfo(Request $request,UserService $userService)
    {
        $user = $this->current_user();

        $auth = $userService->getAuthInfo($user);
        return $this->successWithData($auth);
    }

    //初级认证
    public function primaryAuth(Request $request,UserService $userService)
    {
//        if ($res = $this->verifyField($request->all(),[
//            'country_code' => 'string',
//            'realname' => 'required|string',
//            'id_card' => 'required|string',
//            'type' => 'integer|in:1',
//            'front_img' => 'required',
////            'back_img' => 'required',
//            'hand_img' =>'required',
//        ])) return $res;
//        $request->validate($request->all(),[
//            'country_code' => 'string',
//            'realname' => 'required|string',
//            'id_card' => 'required|string',
//            'type' => 'integer|in:1',
//            'front_img' => 'required',
////            'back_img' => 'required',
//            'hand_img' =>'required',
//        ],[
//            'realname.required' =>'请输入姓名',
//            'id_card.required' => '请输入证件号码',
//            'front_img.required' => '请上传正面照',
//            'hand_img.required' => '请上传手持照'
//        ]);
        $data = $request->all();
        $validator = Validator::make($data,[
            'country_code' => 'string',
            'realname' => 'required|string',
            'id_card' => 'required|string',
            'type' => 'integer|in:1',
            'front_img' => 'required',
//            'back_img' => 'required',
            'hand_img' =>'required',
        ],[
            'realname.required' =>'请输入姓名',
            'id_card.required' => '请输入证件号码',
            'front_img.required' => '请上传正面照',
            'hand_img.required' => '请上传手持照'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $user = $this->current_user();
        $params['country_id'] = $request->input('country_id',1);
        $params['country_code'] = $request->input('country_code','86');
        $params['realname'] = $request->input('realname');
        $params['id_card'] = $request->input('id_card');
        $params['birthday'] = $request->input('birthday');
        $params['address'] = $request->input('address');
        $params['city'] = $request->input('city');
        $params['postal_code'] = $request->input('postal_code');
        $params['extra'] = $request->input('extra');
        $params['phone'] = $request->input('phone');
        $params['type'] = $request->input('type',1);
        $params['front_img'] = $request->input('front_img','');
//        $params['back_img'] = $request->input('back_img','');
        $params['hand_img'] = $request->input('hand_img','');
//        if ( !$this->isIdentityCard($params['id_card']) ) return $this->error(0,'身份证不合法');
        $auth = UserAuth::where(['user_id' => $user['user_id']])->first();
        if ($auth){
            if ($auth['status'] == 1 || $auth['status'] == 2){
                return $this->error(4001,"您已提交认证！");
            }
        }
        $res = $userService->primaryAuth($user,$params,2);
        if(!$res){
            return $this->error();
        }
        return $this->success('认证成功');
    }

    //高级认证
    public function topAuth(Request $request,UserService $userService)
    {
        if ($res = $this->verifyField($request->all(),[
            'front_img' => 'required',
            'back_img' => 'required',
            'hand_img' => '',
        ])) return $res;

        $user = $this->current_user();
        $params = $request->only(['front_img','back_img','hand_img']);

        $res = $userService->topAuth($user,$params);
        if(!$res){
            return $this->error();
        }
        return $this->success('提交成功');
    }

    public function getGradeInfo()
    {
        $user = $this->current_user();

        $data['user'] = $user;
        $data['grade'] = UserGrade::query()->where('status',1)->orderBy('grade_id','asc')->get();
        $grade_explain = Article::query()->where('category_id',ArticleCategory::$typeMap['grade_remark'])->first();
        $grade_explain->makeHidden('translations');
        $data['remark'] = $grade_explain;

        return $this->successWithData($data);
    }

    //登陆日志
    public function getLoginLogs(Request $request)
    {
        $user = $this->current_user();

        $per_page = $request->input('per_page',10);

        $data = UserLoginLog::query()->where('user_id',$user['user_id'])->orderBy('login_time','desc')->paginate($per_page);
        return $this->successWithData($data);
    }

}
