<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/7/30
 * Time: 14:51
 */

namespace App\Servers;


use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class HttpServer
{

    public static function run()
    {
        $server = new Server('0.0.0.0',9501);

        $server->set([
            "worker_num" => 4,
            "task_worker_num" => 100,
            'task_enable_coroutine' => true,
        ]);
        $server->on('Request',function (Request $request,Response $response) use ($server) {
            $clintInfo = $server->getClientInfo($request->fd);
            $response->header("ContentType","application/json;charset:utf-8;");
            //$response->write(json_encode($request,320));
            $response->write(json_encode($clintInfo,320));
            $response->write(json_encode($server->setting,320));
            $response->end();
        });

        $server->on('task',function (\Swoole\Server $server, $task_id,$src_work_id,$data) {
           \co::sleep(1);
        });

        $server->on('finish', function (\Swoole\Server $server, $task_id, $data) {

        });
        $server->start();
    }
}