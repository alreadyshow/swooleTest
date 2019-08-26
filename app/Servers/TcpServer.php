<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/14
 * Time: 19:27
 */

namespace App\Servers;


use App\Pool\MysqlPool;
use common\Helpers\LogHelper;
use Swoole\Server;
use Swoole\Table;
use Swoole\Timer;

class TcpServer
{
    public static function run($config)
    {
        $server = new Server('0.0.0.0', 9502, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

        $server->set([
            "worker_num" => 1,
            "task_worker_num" => 1,
            'task_enable_coroutine' => true,
        ]);

        $table = new Table(1024);

        $table->column('t_name', Table::TYPE_STRING, 16);
        $table->column('t_value', Table::TYPE_STRING, 16);
        $table->create();


        $server->on('WorkerStart', function (Server $server, $work_id) use ($config) {
            MysqlPool::getInstance($config['mysql'])->init()->recycleFreeConn();
        });

        //监听连接进入事件
        $server->on('Connect', function (Server $server, $fd) use ($config, $table) {
            LogHelper::writeLog("connected @ {$fd}", LogHelper::LOG, 'connectCallBack');

            $timer = Timer::tick(10 * 1000, function () use ($server, $fd) {
                $server->send($fd, 'task');
            });

            Timer::after(10 * 60 * 1000, function () use ($server, $fd) {
                $server->send($fd, 'close');
            });

            $table->set('timer', ['t_name' => 'connect', 't_value' => $timer]);
        });

        $server->on('Receive', function (Server $serv, $fd, $reactor_id, $data) use ($table) {
            switch ($data) {
                case 'task':
                    //worker 连接
                    $conn = MysqlPool::getInstance()->getConn();
                    $id = $fd + 10;
                    $sql = "select * from t_b_cash where id = {$id}";
                    $rows = $conn->query($sql);
                    MysqlPool::getInstance()->recycle($conn);
                    LogHelper::writeLog($sql, LogHelper::LOG, 'receiveCallBack');
                    LogHelper::writeLog(json_encode($rows, 320), LogHelper::DATA, 'receiveCallBack');


                    $taskId = $serv->task($fd);
                    $serv->send($fd, "任务已发布 {$taskId}");
                    LogHelper::writeLog("任务已发布 {$taskId}", LogHelper::LOG, 'receiveCallBack');
                    break;
                case 'close':
                    $serv->close($fd);
                    LogHelper::writeLog("关闭 {$fd}", LogHelper::LOG, 'receiveCallBack');
                    $timer = $table->get('timer', 't_value');
                    Timer::clear($timer);
                    break;
                default:
                    break;
            }
        });

        $server->on('Close', function ($serv, $fd) {
            LogHelper::writeLog("Client: {$fd} Close.", LogHelper::LOG, 'closeCallBack');
        });


        $server->on('Task', function (Server $server, Server\Task $task) use ($config) {
            $conn = MysqlPool::getInstance()->getConn();
            $sql = "select * from t_b_cash where id = {$task->data}";
            $rows = $conn->query($sql);
            MysqlPool::getInstance()->recycle($conn);
            LogHelper::writeLog($sql, LogHelper::LOG, 'taskCallBack');
            $task->finish($rows);
        });

        $server->on('Finish', function (Server $server, $task_id, $data) {
            LogHelper::writeLog(json_encode($data, 320), LogHelper::DATA, 'taskFinishCallBack');
        });
        $server->start();
    }
}