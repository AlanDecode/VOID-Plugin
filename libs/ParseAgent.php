<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 解析 UA
 */

class ParseAgent 
{
    private static $browserIcon = array(
        'IE' => '<i class="bi bi-ie"></i>',
        'Safari' => '<i class="bi bi-safari"></i>',
        'Chrome' => '<i class="bi bi-chrome"></i>',
        'FireFox' => '<i class="bi bi-firefox"></i>',
        'Edge' => '<i class="bi bi-edge"></i>',
        'Opera' => '<i class="bi bi-opera"></i>',
        'Unkown' => '<i class="bi bi-unknown"></i>'
    );

    // 获取浏览器信息
    static public function getBrowser($agent)
    {
        $browser = 'Unkown';

        if (preg_match('/MSIE\s([^\s|;]+)/i', $agent, $regs)) {
            $browser = 'IE';
        } else if (preg_match('/FireFox\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'FireFox';
        } else if (preg_match('/Maxthon([\d]*)\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Edge';
        } else if (preg_match('#360([a-zA-Z0-9.]+)#i', $agent, $regs)) {
            $browser = '360';
        } else if (preg_match('/Edge([\d]*)\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Edge';
        } else if (preg_match('/UC/i', $agent)) {
            $browser = 'UC';
        }  else if (preg_match('/QQ/i', $agent, $regs)||preg_match('/QQBrowser\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'QQ';
        } else if (preg_match('/UBrowser/i', $agent, $regs)) {
            $browser = 'UC';
        }  else if (preg_match('/Opera[\s|\/]([^\s]+)/i', $agent, $regs)) {
            $browser = 'Opera';
        } else if (preg_match('/Chrome([\d]*)\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Chrome';
        } else if (preg_match('/safari\/([^\s]+)/i', $agent, $regs)) {
            $browser = 'Safari';
        } else{
            $browser = 'Unkown';
        }

        if (array_key_exists($browser, self::$browserIcon))
            $browser = self::$browserIcon[$browser];

        return $browser;
    }
    // 获取操作系统信息
    static public function getOs($agent)
    {
        $os = 'Unkown';
    
        if (preg_match('/win/i', $agent)) {
            $os = 'Windows';
        } else if (preg_match('/android/i', $agent)) {
            $os = 'Android';
        } else if (preg_match('/ubuntu/i', $agent)) {
            $os = 'Ubuntu';
        } else if (preg_match('/linux/i', $agent)) {
            $os = 'Linux';
        } else if (preg_match('/iPhone/i', $agent)) {
            $os = 'iOS';
        } else if (preg_match('/mac/i', $agent)) {
            $os = 'macOS';
        }else if (preg_match('/fusion/i', $agent)) {
            $os = 'Android';
        } else {
            $os = 'Unkown';
        }
        
        return $os;
    }
}