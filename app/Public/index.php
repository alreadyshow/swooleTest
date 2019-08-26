<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/7/30
 * Time: 14:49
 */

require __DIR__ . '/../../vendor/autoload.php';

$config = array_merge(
    require_once __DIR__.'/../Config/App.php',
    require_once __DIR__.'/../Config/Db.php'
    );
//\App\Servers\HttpServer::run();
date_default_timezone_set($config['timeZone']);
\App\Servers\TcpServer::run($config);