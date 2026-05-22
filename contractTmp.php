<?php
require_once __DIR__ . '/vendor/autoload.php';
include './public/index.php';
$task = new \Workerman\Worker();
// 开启多少个进程运行定时任务，注意业务是否在多进程有并发问题
$task->count = 1;
$task->onWorkerStart = function (\Workerman\Worker $task) {
    // 每1秒执行一次 将合约临时表委托成功
    $time_interval = 1;
    \Workerman\Timer::add($time_interval, function () {
        $time = date('Y-m-d H:i:s', time());
        $lists = \App\Models\ContractTmp::where(['status' => 0])->where('delay_time', '<=', $time)->get();
        foreach ($lists as $list) {
            $user = \App\Models\User::find($list->user_id);
            $params = [];
            $params['type'] = 2; //市价交易
            $params['tp_price'] = $list->tp_price;
            $params['sl_price'] = $list->sl_price;
            $params['side'] = $list->side; //开仓方向 1-开多 2-开空
            $params['symbol'] = !empty($list->change_symbol) ? $list->change_symbol : $list->symbol;
            $params['amount'] = $list->amount;
            $params['lever_rate'] = $list->lever_rate;
            try {
                $res = (new \App\Services\ContractService())->openPosition($user, $params);
                if (!$res) {
                    \Illuminate\Support\Facades\Log::info('合约临时下单失败1 订单id' . $res);
                    $list->status = 2;
                    $list->fail_reason = $res;
                    $list->save();
                } else {
                    $list->status = 1;
                    $list->save();
                }
            } catch (Exception $exception) {
                \Illuminate\Support\Facades\Log::info('合约临时下单失败2 订单id' . $list->id);
                $list->status = 2;
                $list->fail_reason = $exception->getMessage();
                $list->save();
            }
        }

//        if ($time % 10 == 0) {
//            \Illuminate\Support\Facades\Artisan::call('createOptionScene');
//        }
    });
};

// 运行worker
\Workerman\Worker::runAll();