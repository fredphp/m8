<?php

namespace App\Jobs;

use App\Handlers\ContractTool;
use App\Models\ContractPair;
use App\Models\ContractPosition;
use App\Models\SustainableAccount;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class FlatPosition implements ShouldQueue
{
    //强平计算

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $symbol,$price,$t;
    /**
     * 任务可以尝试的最大次数。
     *
     * @var int
     */
    public $tries = 1;
    /**
     * Create a new job instance.
     * @param $scene_order
     * @param $delivery_result
     * @return void
     */
    public function __construct($symbol,$price,$t)
    {
        $this->symbol = $symbol;
        $this->price = $price;
        $this->t = $t;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $s_time = Carbon::now()->getPreciseTimestamp(3);
        $logger = new Logger('flat-position');
        $logger->pushHandler(new RotatingFileHandler(storage_path('/logs/flat-position/flat-position.log')));
        // 强制平仓风险率
        $flatRiskRate = get_setting_value('flat_risk_rate','contract',0);
        // 先查出当前symbol价格变动 受影响的用户ID
        $logger->info('===============当前symbol:'.$this->symbol.'=========价格:'.$this->price.'==========时间:'.date('Y-m-d H:i:s',$this->t).'==========');
        $userIds = ContractPosition::where(['status' => 1,'symbol' => $this->symbol])->pluck('user_id')->toArray();
        $logger->info('受影响用户:'.json_encode($userIds));
        SustainableAccount::query()->whereIn('user_id',$userIds)->chunkById(1000,function ($wallets)use($flatRiskRate,$logger){
            foreach ($wallets as $wallet){
                if(blank($wallet)) continue;
                $user_id = $wallet['user_id'];
                $account = [];
                $totalUnrealProfit = 0;
                //此处的仓位需要加上时间筛选 开仓时间必须要小于当前的$t
                $positions = ContractPosition::query()->where('user_id',$user_id)->where('hold_position','>',0)
                    ->where('created_at','<=',date('Y-m-d H:i:s'))->get();
                foreach ($positions as $position){
                    $contract = ContractPair::query()->find($position['contract_id']);
                    // 获取最新一条成交记录 即实时最新价格
                    $realtime_price = Cache::store('redis')->get('swap:' . 'trade_detail_' . $position['symbol'])['price'] ?? null;
                    if ($this->symbol == $position['symbol']){
                        $realtime_price = $this->price;
                    }
                    $unRealProfit = ContractTool::unRealProfit($position,$contract,$realtime_price);
                    $totalUnrealProfit += $unRealProfit;
                }

                $account['usable_balance'] = $wallet['usable_balance'];
                $account['used_balance'] = $wallet['used_balance'];
                $account['freeze_balance'] = $wallet['freeze_balance'];
                $account['totalUnrealProfit'] = $totalUnrealProfit;
                $account['account_equity'] = custom_number_format($account['usable_balance'] + $account['used_balance'] + $account['freeze_balance'] + $account['totalUnrealProfit'],4); // 永续账户权益 = 账户可用余额 + 持仓保证金 + 委托冻结保证金 + 未实现盈亏
                // 风险率 用以衡量当前合约账户风险程度的指标。风险率越低，账户风险越高，当风险率=10.0%时，将会被强制平仓。风险率=账户权益/（持仓保证金+委托冻结）*100%
                $riskRate = ContractTool::riskRate($account);
                // 风险率是衡量用户资产风险的指标，当风险率 ≤ 10%时，您的仓位将会被系统强制平仓
                if($riskRate != 0 && $riskRate <= $flatRiskRate){
                    $logger->info('用户ID:'.$user_id . '--' . $riskRate . '--' . json_encode($account));
                    // TODO 强制平仓
                    HandleFlatPosition::dispatch($positions,1)->onQueue('HandleFlatPosition');
                }
            }
        });
        $e_time = Carbon::now()->getPreciseTimestamp(3);
        $logger->info('===============结束symbol:'.$this->symbol.'===================耗时:'.($e_time - $s_time).'ms==========');
    }
}
