<?php

namespace App\Admin\Forms;

use App\Models\ContractPair;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ContractRisk extends Form
{
    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return Response
     */
    public function handle(array $input)
    {
        $symbol = $input['symbol'];

        $risk_key = 'fkJson:' . $symbol . '/USDT';
        $cacheKey = 'swap:trade_detail_' . $symbol;
        $cachePrice = Cache::store('redis')->get($cacheKey) ?? ['price' => 0];
        $now_price = $cachePrice['price'];
        if (floatval($input['target_price']) !== 0) {
            if (abs(($input['target_price'] - $now_price) / $now_price) > 0.1) {
                return $this->error('价格浮动超过10%');
            }
        }
        $data = [];
        $data['start_price'] = $now_price;
        $data['target_price'] = $input['target_price'];
        $data['float'] = $input['float'];
        $data['enabled'] = $input['enabled'];
        $data['one_status'] = 0; //充值
        Redis::set($risk_key, json_encode($data));
        Cache::store('redis')->set(Admin::user()->id . '_select_symbol', $symbol);

        return $this->success('Processed successfully.');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $symbols = ContractPair::query()->pluck('symbol');
        $arr = [];
        foreach ($symbols as $symbol) {
            $arr[$symbol] = $symbol;
        }

        $current_symbol = Cache::store('redis')->get(Admin::user()->id . '_select_symbol') ?? 'BTC';
        $risk_key = 'fkJson:' . $current_symbol . '/USDT';
        $risk = json_decode(Redis::get($risk_key),true);
        if (!$risk){
            $enable = 0;
        }else{
            $enable = intval($risk['enabled']);
        }

        $cacheKey = 'swap:trade_detail_' . $current_symbol;
        $cachePrice = Cache::store('redis')->get($cacheKey) ?? ['price' => 0];
        $this->setFormId('risk_form');
        $this->select('symbol', '交易币种')->setElementClass('symbol')->options($arr)->required()->default($current_symbol);
        $this->text('start_price', '起始价')->setElementClass('start_price')->readOnly()->default($cachePrice['price']);
        $this->text('target_price', '目标价')->setElementClass('target_price')->required()->default($risk['target_price']);
        $this->text('float', '上下浮动(%)')->setElementClass('float')->default($risk['float']);
        $this->text('enabled', '是否执行(0-不执行 1-执行)')->setElementClass('enabled')->options([0 => '否',1 => '是'])->default($enable)->rules('required|in:0,1');;

    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        return [];
    }
}
