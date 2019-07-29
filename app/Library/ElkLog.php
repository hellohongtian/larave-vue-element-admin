<?php
/**
 * 统一日志类
 * wood
 * 2017-05-27
 */
namespace App\Library;


class ElkLog
{

    /**
     * 记录到elk日志
     * @param $message
     * @return bool
     */
    public static function writeLog($message)
    {
        try {
            $log_dir_path = isset($_SERVER['SITE_LOG_DIR']) ? $_SERVER['SITE_LOG_DIR'] : '';
            if (!$log_dir_path) {
                return true;
            }
            if (is_dir($log_dir_path) == false) {
                mkdir($log_dir_path, 0777, true);
            }
            if ($log_dir_path && is_dir($log_dir_path)) {
                $log_name = date('Y-m-d') . 'yj.log';
                $message = is_array($message) ? json_encode($message) : (is_string($message) ? $message : var_export($message, true));
                file_put_contents($log_dir_path . '/' . $log_name, $message, FILE_APPEND);
            }
        } catch (\Exception $e) {
            return true;
        }
        return true;
    }

}