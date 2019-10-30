<?php

namespace Kanboard\Plugin\GithubWebhook\Helper;
use Kanboard\Core\Base;
use \Exception;

class LogHelper extends Base
{
    /**
     * @param Exception $e
     * @return string
     */
    public static function eToString($e) {
        return sprintf(
            "file:%s line:%s %d:%s\n%s",
            $e->getFile(),
            $e->getLine(),
            $e->getCode(),
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    public function log($msg, $file = '', $call_stack = true, $full_stack = true) {
        if ($msg instanceof Exception) {
            $msg = self::eToString($msg);
        }

        if (!is_string($msg)) {
            $msg = var_export($msg, true);
        }

        $m = "\n[" . date('Y-m-d H:i:s', time()) . "] "
            . (isset($_SERVER['SERVER_ADDR']) ? 'Host: ' . $_SERVER['SERVER_ADDR'] : '')
            . (($real_ip = self::getRealIp()) ? ' Client：' . $real_ip : '');
        $m .= ': ';
        if ($call_stack) {
            if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) {
                $host = isset($_SERVER['HTTP_HOST'])
                    ? $_SERVER['HTTP_HOST']
                    : (isset($_SERVER['SERVER_NAME'])
                        ? $_SERVER['SERVER_NAME']
                        : 'invalid_host');
                $m .= "\n" . 'REQUEST_URL: http://' . $host . $_SERVER['REQUEST_URI'] . "\n";
            }
            if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) {
                $m .= 'REFERER: http://' . $_SERVER['HTTP_REFERER'] . "\n";
            }
            $d = debug_backtrace();
            if (is_array($d)) {
                $i = 0;
                foreach ($d as $trace) {
                    $extra = ($i == 1) ? '  ***': '';
                    if (isset($trace['file'])) {
                        if ($full_stack || $i == 1) {
                            $m .= "\n" . $trace['file'] . ':' . $trace['line'] . $extra . "\n";
                        }
                    }
                    $i++;
                }
            }
        }
        $m .= $msg . "\n";

        $file = $file ? $file : '/tmp/tmp.log';
        $fp = @fopen($file, "a+");
        if ($fp) {
            fputs($fp, $m);
            fclose($fp);
        }
    }


    public static function print_r($var) {
        echo '<pre style="color:red">' . print_r($var, true) . '</pre>';
    }

    /**
     * 得到当前用户Ip地址
     *
     * @return string
     */
    public static function getRealIp() {
        $pattern = '/(\d{1,3}\.){3}\d{1,3}/';
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && preg_match_all($pattern, $_SERVER['HTTP_X_FORWARDED_FOR'], $_mat)) {
            foreach ($_mat[0] as $_ip) {
                //得到第一个非内网的IP地址
                if ((0 != strpos($_ip, '192.168.')) && (0 != strpos($_ip, '10.')) && (0 != strpos($_ip, '172.16.'))) {
                    return $_ip;
                }
            }
        } else {
            if (isset($_SERVER["HTTP_CLIENT_IP"]) && preg_match($pattern, $_SERVER["HTTP_CLIENT_IP"])) {
                return $_SERVER["HTTP_CLIENT_IP"];
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

}