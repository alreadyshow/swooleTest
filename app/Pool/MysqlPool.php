<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/14
 * Time: 19:38
 */

namespace App\Pool;


use common\Helpers\LogHelper;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\MySQL;
use Swoole\Timer;

/**
 * Class MysqlPool
 * @package App\Pool
 *
 * @property Channel $pool 连接池
 * @property integer $maxSize 最大连接池数
 * @property integer $minSize 最小连接池数
 * @property integer $curSize 当前连接数
 * @property array $dbConfig 数据库配置
 * @property integer $freeTime 空闲时间
 *
 */
class MysqlPool
{
    private $dbConfig;
    private $maxSize; //连接池数量
    private $minSize;
    private $curSize;
    private $pool;//连接池
    private $freeTime;

    private static $instance;  //连接池实例

    /**
     * MysqlPool
     * @param array $config
     */
    private function __construct($config)
    {
        LogHelper::writeLog('config:' . json_encode($config, 320), LogHelper::LOG, 'MysqlPool');
        $this->minSize = 5;
        $this->maxSize = 8;
        $this->freeTime = 300; //1h
        $this->dbConfig = $config;
        $this->pool = new Channel($this->maxSize + 1);
    }

    public static function getInstance($config = null)
    {
        if (is_null(self::$instance)) {
            LogHelper::writeLog('实例化MysqlPool对象', LogHelper::LOG, 'MysqlPool');
            if (is_null($config)) {
                throw new \Exception('Mysql config is null');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }


    /**
     * [createDbConn] 创建连接
     * [Time:2019/8/14]
     * [Creator:non]
     * @return MySQL
     */
    public function createDbConn()
    {
        $conn = new MySQL();
        $res = $conn->connect($this->dbConfig);
        if ($res === false) {
            LogHelper::writeLog($conn->connect_error . "", LogHelper::LOG, 'MysqlPoolConnect');
        } else {
            LogHelper::writeLog("连接成功！", LogHelper::LOG, 'MysqlPoolConnect');
        }
        return $conn;
    }

    /**
     * [createConnObj] 创建连接对象
     * [Time:2019/8/14]
     * [Creator:non]
     * @return array|null
     */
    public function createConnObj()
    {
        $conn = $this->createDbConn();
        return $conn ? ['last_used_time' => time(), 'conn' => $conn] : null;
    }

    public function init()
    {
        for ($i = 0; $i < $this->minSize; $i++) {
            $obj = $this->createConnObj();
            $this->curSize++;
            $this->pool->push($obj);
        }
        return $this;
    }

    /**
     * [getConn] 获取连接
     * [Time:2019/8/14]
     * [Creator:non]
     * @param int $timeOut
     * @return MySQL
     */
    public function getConn($timeOut = 3)
    {
        if ($this->pool->isEmpty()) {
            LogHelper::writeLog("当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()} 连接池空！重新创建中...", LogHelper::LOG, 'getConn');
            if ($this->curSize < $this->maxSize) {
                $this->curSize++;
                $obj = $this->createConnObj();
            } else {
                $obj = $this->pool->pop($timeOut);
            }
        } else {
            $obj = $this->pool->pop();
        }
        LogHelper::writeLog("获取连接：" . json_encode($obj, 320), LogHelper::LOG, 'getConn');
        return $obj ? $obj['conn'] : $this->getConn();
    }

    /**
     * [recycle] 回收连接
     * [Time:2019/8/14]
     * [Creator:non]
     * @param MySQL $conn
     */
    public function recycle($conn)
    {
        if ($conn->connected) {
            LogHelper::writeLog("回收连接 当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()}", LogHelper::LOG, 'recycle');
            $this->pool->push(['last_used_time' => time(), 'conn' => $conn]);
        }
    }

    public function recycleFreeConn()
    {
        //每两分钟检测
        Timer::tick((2 * 60 * 1000), function () {
            LogHelper::writeLog("当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()} 回收检测", LogHelper::LOG, 'recycleFreeConn');
            if ($this->pool->length() < intval($this->maxSize * 0.5)) {
                //当前请求连接较多 不回收
                return;
            }
            $flag = $this->pool->length();
            while ($flag > 0) {
                //池子空了 不回收
                if ($this->pool->isEmpty()) {
                    break;
                }
                // 如果池子连接数降低 则补充连接
                if ($this->curSize < $this->minSize) {
                    LogHelper::writeLog("当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()} 补充连接", LogHelper::LOG, 'recycleFreeConn');
                    $this->createConnObj();
                    $this->curSize++;
                }

                $connObj = $this->pool->pop(0.001);
                $nowTime = time();
                $lastUsedTime = $connObj['last_used_time'];

                //如果当前连接失效 则释放连接 重新创建 保持数据库连接

                $status = $connObj['conn']->query("select 1");
                LogHelper::writeLog('连接状态：' . json_encode($status, 320), LogHelper::LOG, 'recycleFreeConn');

                if (!$status) {
                    LogHelper::writeLog("当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()} 连接失效 补充连接", LogHelper::LOG, 'recycleFreeConn');
                    $connObj['conn']->close();
                    $this->curSize--;
                } else {
                    //当前的连接数 大于 最小限制且连接超时。回收连接
                    if ($this->curSize > $this->minSize && ($nowTime - $lastUsedTime) > $this->freeTime) {
                        LogHelper::writeLog("当前连接数：{$this->curSize} 当前池子连接数：{$this->pool->length()} 连接过多且超时 回收连接", LogHelper::LOG, 'recycleFreeConn');
                        $connObj['conn']->close();
                        $this->curSize--;
                    } else {
                        //否则还回去
                        $this->pool->push($connObj);
                    }
                }
                $flag--;
            }
        });
    }

}