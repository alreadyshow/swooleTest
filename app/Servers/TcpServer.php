<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/14
 * Time: 19:27
 */

namespace App\Servers;


use App\Pool\MysqlAr;
use App\Pool\MysqlPool;
use common\Helpers\LogHelper;
use Swoole\Server;
use Swoole\Table;
use Swoole\Timer;

class TcpServer
{
    public static function run($config)
    {
        $server = new Server($config['host'], $config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $server->set($config['server']);

        $table = new Table(1024);

        $table->column('t_info', Table::TYPE_STRING, 16);
        $table->column('t_id', Table::TYPE_STRING, 16);
        $table->create();


        $server->on('WorkerStart', function (Server $server, $work_id) use ($config) {
            MysqlPool::getInstance($config['mysql'])->init()->recycleFreeConn();
        });

        //监听连接进入事件
        $server->on('Connect', function (Server $server, $fd) use ($config, $table) {
            LogHelper::writeLog("connected @ {$fd}", LogHelper::LOG, 'connectCallBack');

            $timer = Timer::tick(10 * 1000, function () use ($server, $fd) {
                $server->send($fd, 'aa');
            });

            $status = Timer::after(10 * 60 * 1000, function () use ($server, $fd) {
                $server->send($fd, 'close');
            });

            LogHelper::writeLog($status . '定时器状态', LogHelper::LOG, 'connectCallBack');

            foreach (Timer::list() as $timerId) {
                LogHelper::writeLog($timerId . ' -- ' . json_encode(Timer::info($timerId), 320), LogHelper::LOG, 'connectCallBack');
            }
            $table->set($server->worker_id . '_' . $fd . 'timer', ['t_info' => Timer::info($timer), 't_id' => $timer]);
            $table->set($server->worker_id . '_' . $fd . 'timerAfter', ['t_info' => Timer::info($status), 't_id' => $status]);

        });

        $server->on('Receive', function (Server $serv, $fd, $reactor_id, $data) use ($table) {
            switch ($data) {
                case 'task':
                    //worker 连接
                    $id = $fd + 10;
                    $sql = "select * from t_b_cash where id = {$id}";

                    MysqlAr::query($sql);

                    $taskId = $serv->task($fd);
                    $serv->send($fd, "任务已发布 {$taskId}");
                    LogHelper::writeLog("任务已发布 {$taskId}", LogHelper::LOG, 'receiveCallBack');
                    break;
                case 'close':
                    $serv->close($fd);
                    LogHelper::writeLog("关闭 {$fd}", LogHelper::LOG, 'receiveCallBack');
                    $timer = $table->get($serv->worker_id . '_' . $fd . 'timer', 't_id');
                    LogHelper::writeLog("清除定时器 'timer' {$timer} info: " . json_encode(Timer::info($timer), 320), LogHelper::LOG, 'receiveCallBack');
                    Timer::clear($timer);
                    break;
                default:
                    break;
            }
        });

        $server->on('Close', function (Server $serv, $fd) use ($table) {
            $timer = $table->get($serv->worker_id . '_' . $fd . 'timer', 't_id');
            $timerAfter = $table->get($serv->worker_id . '_' . $fd . 'timerAfter', 't_id');
            LogHelper::writeLog("清除定时器 'timer' {$timer} info: " . json_encode(Timer::info($timer), 320), LogHelper::LOG, 'closeCallBack');
            Timer::clear($timer);
            if (Timer::info($timerAfter)['removed'] == false) {
                LogHelper::writeLog("清除定时器 'timerAfter' {$timerAfter} info: " . json_encode(Timer::info($timerAfter), 320), LogHelper::LOG, 'closeCallBack');
                Timer::clear($timerAfter);
            }
            LogHelper::writeLog("Client: {$fd} Close.", LogHelper::LOG, 'closeCallBack');
        });


        $server->on('Task', function (Server $server, Server\Task $task) use ($config) {
            $sql = "select * from t_b_cash where id = {$task->data}";
            $rows = MysqlAr::query($sql);
            $task->finish($rows);
        });

        $server->on('Finish', function (Server $server, $task_id, $data) {
            LogHelper::writeLog(json_encode($data, 320), LogHelper::DATA, 'taskFinishCallBack');
        });
        $server->start();
    }
}