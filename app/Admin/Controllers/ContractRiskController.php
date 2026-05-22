<?php

namespace App\Admin\Controllers;

use App\Models\ContractPair;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ContractRiskController extends AdminController
{
    public function index(Content $content)
    {
        Admin::script(
            <<<JS
    // // 3秒后刷新当前页面
    // setTimeout(function () { 
    //     Dcat.reload(); 
    // }, 3000);
    
    $(window).on('load', function() {
           $.ajax({
            url: '/admin/contract-risk/contract_risk_detail?symbol='+$('.field_symbol').val(), // 替换为你的API端点
            type: 'GET', // 请求类型
            dataType: 'json', // 期望从服务器返回的数据类型
            success: function(response) {
                $('.field_start_price').val(response.data.start_price)
                $('.field_target_price').val(response.data.target_price)
                $('.field_float').val(response.data.float)
                $('.field_enabled').val(response.data.enabled)
        },
            error: function(xhr, status, error) {
        // 请求失败时的回调函数
            alert('请求失败')
            }
        })
});
    $('.field_symbol').on('change', function () {
        $.ajax({
            url: '/admin/contract-risk/contract_risk_detail?symbol='+$(this).val(), // 替换为你的API端点
            type: 'GET', // 请求类型
            dataType: 'json', // 期望从服务器返回的数据类型
            success: function(response) {
                $('.field_start_price').val(response.data.start_price)
                $('.field_target_price').val(response.data.target_price)
                $('.field_float').val(response.data.float)
                $('.field_enabled').val(response.data.enabled)
        },
            error: function(xhr, status, error) {
        // 请求失败时的回调函数
            alert('请求失败')
            }
        })
    })
JS
        );
        return $content
            ->title('合约风控')
            ->body(new Card(new \App\Admin\Forms\ContractRisk()));
    }

    public function contract_risk_detail(Request $request)
    {
        $symbol = $request->symbol;
        $cacheKey = 'swap:trade_detail_' . $symbol;
        $cachePrice = Cache::store('redis')->get($cacheKey) ?? ['price' => 0];
        $now_price = $cachePrice['price'];
        $risk_key = 'fkJson:' . $symbol . '/USDT';
        $cacheData = json_decode(Redis::get($risk_key),true);
        if (!$cacheData) {
            //{"start_price":3496.83,"target_price":"0","float":"1","enabled":0,"one_status":0}
            $cacheData = [];
            $cacheData['start_price'] = $now_price;
            $cacheData['target_price'] = 0;
            $cacheData['float'] = 1;
            $cacheData['enabled'] = 0;
            $cacheData['one_status'] = 0;
            Redis::set($risk_key, json_encode($cacheData));
        }else{
            $cacheData['start_price'] = $now_price;
        }
        return response()->json(['code' => 200, 'data' => $cacheData]);
    }

    public function symbol_enable(Request $request){
        $q = $request->q;
        dd($q);
    }
}
