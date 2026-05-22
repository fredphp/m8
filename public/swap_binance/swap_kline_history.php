<?php
require "../index.php";

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
use Workerman\Worker;
use GatewayWorker\Lib\Gateway;

$worker = new Worker();
$worker->count = 1;
$worker->onWorkerStart = function ($worker) {

    Gateway::$registerAddress = '127.0.0.1:1238';
    // 获取币安K线历史数据
    getBinanceKlineHistory();
    echo 'K线历史请求完成... 请退出';
};

Worker::runAll();
