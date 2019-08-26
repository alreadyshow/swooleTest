<?php
/**
 * Created by PhpStorm.
 * User: non
 * Date: 2019/8/23
 * Time: 16:19
 */

namespace common\Helpers;


class LogHelper
{
    const LOG = 'log';
    const DATA = 'data';

    public static function writeLog($data, $type = 'log', $prefix = false)
    {
        switch ($type) {
            case self::LOG:
                $fileName = __DIR__ . '/../../app/Runtime/Logs/' . date('Y-m-d') . '.log';
                break;
            case self::DATA:
                $fileName = __DIR__ . '/../../app/Runtime/Logs/' . date('Y-m-d') . '.data';
                break;
            default:
                break;
        }

        \co::writeFile($fileName, date('Y-m-d H:i:s') . ' - ' . $prefix . ' - ' . $data . "\r\n", FILE_APPEND);
    }
}