<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/27
 * Time: 10:22
 */

namespace App\Pool;


use common\Helpers\LogHelper;

class MysqlAr
{

    public static function query($sql,$timeout = null)
    {
        LogHelper::writeLog($sql." timeout:{$timeout}",LogHelper::LOG,'mysqlArQuery');
        $conn = MysqlPool::getInstance()->getConn();
        $rows = $conn->query($sql,$timeout);
        LogHelper::writeLog(json_encode($rows,320),LogHelper::LOG,'mysqlArQuery');
        if ($rows == false) {
            LogHelper::writeLog($conn->error,LogHelper::LOG,'mysqlArQuery');
        }
        MysqlPool::getInstance()->recycle($conn);
        return $rows;
    }

}