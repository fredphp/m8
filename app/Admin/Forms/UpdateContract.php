<?php

namespace App\Admin\Forms;


use App\Models\ContractPair;
use App\Models\ContractTmp;
use App\Models\User;
use App\Services\ContractService;
use Carbon\Carbon;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Symfony\Component\HttpFoundation\Response;
use Dcat\Admin\Contracts\LazyRenderable;

class UpdateContract extends Form implements LazyRenderable
{
    use LazyWidget;

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return Response
     */
    public function handle(array $input)
    {
        // 获取外部传递参数
        $id = $this->payload['id'] ?? null;
        $t = ContractTmp::find($id);
        if ($t->status != 0) {
            return $this->error('订单已成交 无法修改');
        }
        $user = User::find($t->user_id);
        $maxAmount = (new ContractService())->openNum($user, ['symbol' => $input['symbol'], 'lever_rate' => $input['lever_rate']]);
        $scale = $input['scale'];
        $amount = ceil($scale / 100 * $maxAmount);
        if ($t->symbol !== $input['symbol']){
            $t->change_symbol = $input['symbol'];
        }
        $t->scale = $scale;
        $t->side = $input['side'];
        $t->lever_rate = $input['lever_rate'];
        $t->amount = $amount;
        $t->delay_time = Carbon::now()->addSeconds(1);
        $t->mark = 2;
        if (!$t->save()) {
            return $this->error('订单已成交 无法修改');
        }
        return $this->success('Processed successfully.');
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        // 获取外部传递参数
        $id = $this->payload['id'] ?? null;
        $t = ContractTmp::find($id);
        $symbols = ContractPair::where(['status' => 1, 'trade_status' => 1])->get('symbol');
        $user = User::find($t->user_id);
        $arr = [];
        foreach ($symbols as $symbol) {
            $arr[$symbol->symbol] = $symbol->symbol;
        }
        $max = (new ContractService())->openNum($user, ['symbol' => $t->symbol, 'lever_rate' => $t->lever_rate]);
        $old_scale = round($t->amount / $max, 2) * 100;
        $this->radio('symbol', '交易对')->required()->options($arr)->default($t->symbol);
        $this->radio('side', '方向')->required()->options([1 => '开多', 2 => '开空'])->default($t->side);
        $this->radio('lever_rate', '杠杆')->required()->options(['25' => 25, '50' => 50, '100' => 100, '200' => 200])->default($t->side);
//        $this->text('avai_amount', '可开最大张数')->disable()->default($max);
        $this->radio('scale', '资金百分比')->options([$old_scale => '原始', 25 => '25%', 50 => '50%', 75 => '75%', 100 => '100%'])->default($old_scale);


//            ->when(0, function (Form $form) use ($t) {
////            $form->text('amount', '张数')->required()->default($t->amount);
//        })->when(25, function (Form $form) use ($t, $max) {
//            $amount = ceil(25 / 100 * $max);
////            $form->text('amount', '张数')->required()->default($amount);
//        })->when(50, function (Form $form) use ($t, $max) {
//            $amount = ceil(50 / 100 * $max);
////            $form->text('amount', '张数')->required()->default($amount);
//        })->when(75, function (Form $form) use ($t, $max) {
//            $amount = ceil(75 / 100 * $max);
////            $form->text('amount', '张数')->required()->default($amount);
//        })->when(100, function (Form $form) use ($t, $max) {
//            $amount = ceil(100 / 100 * $max);
////            $form->text('amount', '张数')->required()->default($amount);
//        });
    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        // 获取外部传递参数
        $id = $this->payload['id'] ?? null;
        $t = ContractTmp::find($id);
        return [
            'symbol' => $t->symbol,
            'side' => $t->side,
            'amount' => $t->ampount,
            'lever_rate' => $t->lever_rate
        ];
    }
}
