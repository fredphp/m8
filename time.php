<?php
use Workerman\Worker;
use Workerman\Timer;
require_once __DIR__ . '/vendor/autoload.php';
require './public/index.php';

// worker实例1有4个进程，进程id编号将分别为0、1、2、3
$worker = new Worker();
// 设置启动4个进程
$worker->count = 5;
// 每个进程启动后打印当前进程id编号即 $worker1->id
$worker->onWorkerStart = function($worker)
{
    //将跟单计划的盈亏同步到跟单历史
    if ($worker->id == 0){
        Timer::add(1, function()use($worker){
            (new \App\Services\TimeContractService())->syncKyk();
        });
    }

    //执行跟单计划
    if ($worker->id == 1){
        Timer::add(1, function()use($worker){
            (new \App\Services\TimeContractService())->excFollowPlan();
        });
    }

    //执行散户订单
    if ($worker->id == 2){
        Timer::add(1, function()use($worker){
            (new \App\Services\TimeContractService())->excTimePlan();
        });
    }
    //执行订单结算
    if ($worker->id == 3){
        Timer::add(3, function()use($worker){
            (new \App\Services\TimeContractService())->settleOrder();
        });
    }
    //设置结算价格
    if ($worker->id == 4){
        Timer::add(1, function()use($worker){
            (new \App\Services\TimeContractService())->setSettlePrice();
        });
    }
};
// 运行worker
Worker::runAll();
