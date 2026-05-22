<?php
require_once __DIR__ . '/vendor/autoload.php';
include './public/index.php';
$task = new \Workerman\Worker();
// 开启多少个进程运行定时任务，注意业务是否在多进程有并发问题
$task->count = 1;
$task->onWorkerStart = function (\Workerman\Worker $task) {
    // 每2.5秒执行一次
    $time_interval = 1;
    \Workerman\Timer::add($time_interval, function () {
        $time = time();
        if ($time % 10 == 0) {
            \Illuminate\Support\Facades\Artisan::call('createOptionScene');
        }
    });
};

// 运行worker
\Workerman\Worker::runAll();