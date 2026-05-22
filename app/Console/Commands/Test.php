<?php

namespace App\Console\Commands;

use App\Jobs\HandleContractEntrust;
use App\Jobs\HandleFlatPosition;
use App\Models\ContractEntrust;
use App\Models\ContractPosition;
use App\Models\InsideTradeBuy;
use App\Services\ContractService;
use App\Services\InsideTradeService;
use App\Services\TimeContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试方法';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        (new TimeContractService())->settleOrder();
//        (new InsideTradeService())->handleBuyOrder(InsideTradeBuy::where('id',183)->first());
//       $a =  (new ContractService())->taskPrice('BTC',100,'trade');
//       dd($a);
//        $positions = ContractPosition::query()->where('user_id',861854)->where('hold_position','>',0)->get();
//        (new HandleFlatPosition($positions))->handle();
//        (new HandleContractEntrust(ContractEntrust::find(1030411)))->handle();
//        HandleContractEntrust::dispatch($entrust)->onQueue('handleContractEntrust');
        //
        // Cache::store('redis')->put('get_begin_price:1234','235g');
//        return Cache::remember('agent_grade_option', 60, function () {
//            return '233';
//        });
    }
}
