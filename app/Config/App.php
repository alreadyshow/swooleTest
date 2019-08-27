<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/15
 * Time: 11:06
 */

return [
    'timeZone' => 'PRC',
    'host' => '0.0.0.0',
    'port' => '9502',
    'server' => [
        "worker_num" => 1,
        "task_worker_num" => 1,
        'task_enable_coroutine' => true,
        'dispatch_mode' => 2,
        'heartbeat_check_interval' => 60,
        'heartbeat_idle_time' => 120,
        'daemonize' => true,
        'log_file' => '/var/www/swooleTest/app/Runtime/Tmp/'.date('Y-m-d').'.log',
    ],
];