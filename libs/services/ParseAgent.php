<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 解析 UA
 */

class ParseAgent
{
    private static $browserIcon = array(
        'IE' => 'bi-ie',
        'Safari' => 'bi-safari',
        'Chrome' => 'bi-chrome',
        'Firefox' => 'bi-firefox',
        'Edge' => 'bi-edge',
        'Opera' => 'bi-opera',
        'Unknown' => 'bi-unknown'
    );

    private static function normalizeAgent($agent)
    {
        return trim((string)$agent);
    }

    private static function normalizeVersion($version)
    {
        $version = str_replace('_', '.', (string)$version);
        $version = preg_replace('/[^0-9.].*$/', '', $version);

        return trim($version, '. ');
    }

    private static function escape($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }

    private static function label($name, $version = '')
    {
        $name = trim((string)$name);
        $version = self::normalizeVersion($version);

        if ($name === '') {
            $name = 'Unknown';
        }

        if ($version === '') {
            return $name;
        }

        return $name . ' ' . $version;
    }

    private static function iconKey($icon)
    {
        return array_key_exists($icon, self::$browserIcon) ? $icon : 'Unknown';
    }

    private static function browserDisplay($name, $version = '', $icon = 'Unknown')
    {
        $label = self::label($name, $version);
        $iconClass = self::$browserIcon[self::iconKey($icon)];

        return '<i class="bi ' . $iconClass . '" title="' . self::escape($label) . '"></i> ' . self::escape($label);
    }

    private static function matchVersion($agent, $pattern, $group = 1)
    {
        if (preg_match($pattern, $agent, $matches) && isset($matches[$group])) {
            return self::normalizeVersion($matches[$group]);
        }

        return '';
    }

    private static function contains($agent, $pattern)
    {
        return preg_match($pattern, $agent) === 1;
    }

    private static function detectBrowserInfo($agent)
    {
        if (self::contains($agent, '/PostmanRuntime\/([0-9.]+)/i')) {
            return array('name' => 'Postman', 'version' => self::matchVersion($agent, '/PostmanRuntime\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/curl\/([0-9.]+)/i')) {
            return array('name' => 'curl', 'version' => self::matchVersion($agent, '/curl\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/Wget\/([0-9.]+)/i')) {
            return array('name' => 'Wget', 'version' => self::matchVersion($agent, '/Wget\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/python-requests\/([0-9.]+)/i')) {
            return array('name' => 'Python Requests', 'version' => self::matchVersion($agent, '/python-requests\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/Go-http-client\/([0-9.]+)/i')) {
            return array('name' => 'Go HTTP Client', 'version' => self::matchVersion($agent, '/Go-http-client\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/okhttp\/([0-9.]+)/i')) {
            return array('name' => 'OkHttp', 'version' => self::matchVersion($agent, '/okhttp\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/(?:EdgA|EdgiOS|Edg|Edge)\/([0-9.]+)/i')) {
            return array('name' => 'Edge', 'version' => self::matchVersion($agent, '/(?:EdgA|EdgiOS|Edg|Edge)\/([0-9.]+)/i'), 'icon' => 'Edge');
        }

        if (self::contains($agent, '/(?:OPR|Opera Mini|Opera Touch|Opera)\/([0-9.]+)/i')) {
            return array('name' => 'Opera', 'version' => self::matchVersion($agent, '/(?:OPR|Opera Mini|Opera Touch|Opera)\/([0-9.]+)/i'), 'icon' => 'Opera');
        }

        if (self::contains($agent, '/Vivaldi\/([0-9.]+)/i')) {
            return array('name' => 'Vivaldi', 'version' => self::matchVersion($agent, '/Vivaldi\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/YaBrowser\/([0-9.]+)/i')) {
            return array('name' => 'Yandex Browser', 'version' => self::matchVersion($agent, '/YaBrowser\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/SamsungBrowser\/([0-9.]+)/i')) {
            return array('name' => 'Samsung Internet', 'version' => self::matchVersion($agent, '/SamsungBrowser\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/(?:HuaweiBrowser|HUAWEI Browser)\/([0-9.]+)/i')) {
            return array('name' => 'Huawei Browser', 'version' => self::matchVersion($agent, '/(?:HuaweiBrowser|HUAWEI Browser)\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/MiuiBrowser\/([0-9.]+)/i')) {
            return array('name' => 'MIUI Browser', 'version' => self::matchVersion($agent, '/MiuiBrowser\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/(?:QQBrowser|MQQBrowser)\/([0-9.]+)/i')) {
            return array('name' => 'QQ Browser', 'version' => self::matchVersion($agent, '/(?:QQBrowser|MQQBrowser)\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/(?:UCBrowser|UC?Browser|UCWEB)\/?([0-9.]*)/i')) {
            return array('name' => 'UC Browser', 'version' => self::matchVersion($agent, '/(?:UCBrowser|UC?Browser|UCWEB)\/?([0-9.]*)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/(?:360SE|360EE|QIHU 360SE|QIHU 360EE|QihooBrowser|QHBrowser|MetaSr|LBBROWSER)/i')) {
            return array('name' => '360 Browser', 'version' => '', 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/2345Explorer(?:\/([0-9.]+))?/i')) {
            return array('name' => '2345 Explorer', 'version' => self::matchVersion($agent, '/2345Explorer(?:\/([0-9.]+))?/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/MicroMessenger\/([0-9.]+)/i')) {
            return array('name' => 'WeChat', 'version' => self::matchVersion($agent, '/MicroMessenger\/([0-9.]+)/i'), 'icon' => 'Unknown');
        }

        if (self::contains($agent, '/Maxthon(?:\/| )([0-9.]+)/i')) {
            return array('name' => 'Maxthon', 'version' => self::matchVersion($agent, '/Maxthon(?:\/| )([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/(?:Firefox|FxiOS)\/([0-9.]+)/i')) {
            return array('name' => 'Firefox', 'version' => self::matchVersion($agent, '/(?:Firefox|FxiOS)\/([0-9.]+)/i'), 'icon' => 'Firefox');
        }

        if (self::contains($agent, '/(?:CriOS|HeadlessChrome|Chrome)\/([0-9.]+)/i')) {
            return array('name' => 'Chrome', 'version' => self::matchVersion($agent, '/(?:CriOS|HeadlessChrome|Chrome)\/([0-9.]+)/i'), 'icon' => 'Chrome');
        }

        if (self::contains($agent, '/MSIE\s([0-9.]+)/i') || self::contains($agent, '/Trident\/.*rv:([0-9.]+)/i')) {
            return array(
                'name' => 'IE',
                'version' => self::matchVersion($agent, '/MSIE\s([0-9.]+)/i') ?: self::matchVersion($agent, '/Trident\/.*rv:([0-9.]+)/i'),
                'icon' => 'IE'
            );
        }

        if (self::contains($agent, '/Version\/([0-9.]+).*Safari/i')) {
            return array('name' => 'Safari', 'version' => self::matchVersion($agent, '/Version\/([0-9.]+).*Safari/i'), 'icon' => 'Safari');
        }

        if (self::contains($agent, '/Safari\/([0-9.]+)/i')) {
            return array('name' => 'Safari', 'version' => self::matchVersion($agent, '/Safari\/([0-9.]+)/i'), 'icon' => 'Safari');
        }

        return array('name' => 'Unknown', 'version' => '', 'icon' => 'Unknown');
    }

    private static function detectOsInfo($agent)
    {
        if (self::contains($agent, '/HarmonyOS(?:\s|\/)?([0-9._]+)/i')) {
            return self::label('HarmonyOS', self::matchVersion($agent, '/HarmonyOS(?:\s|\/)?([0-9._]+)/i'));
        }

        if (self::contains($agent, '/OpenHarmony(?:\s|\/)?([0-9._]+)/i')) {
            return self::label('OpenHarmony', self::matchVersion($agent, '/OpenHarmony(?:\s|\/)?([0-9._]+)/i'));
        }

        if (self::contains($agent, '/Windows Phone(?: OS)? ([0-9.]+)/i')) {
            return self::label('Windows Phone', self::matchVersion($agent, '/Windows Phone(?: OS)? ([0-9.]+)/i'));
        }

        if (self::contains($agent, '/Windows NT 10\.0/i')) {
            return 'Windows 10/11';
        }

        if (self::contains($agent, '/Windows NT 6\.3/i')) {
            return 'Windows 8.1';
        }

        if (self::contains($agent, '/Windows NT 6\.2/i')) {
            return 'Windows 8';
        }

        if (self::contains($agent, '/Windows NT 6\.1/i')) {
            return 'Windows 7';
        }

        if (self::contains($agent, '/Windows NT 6\.0/i')) {
            return 'Windows Vista';
        }

        if (self::contains($agent, '/Windows NT 5\.[12]/i')) {
            return 'Windows XP';
        }

        if (self::contains($agent, '/Windows/i')) {
            return 'Windows';
        }

        if (self::contains($agent, '/Android\s([0-9.]+)/i')) {
            return self::label('Android', self::matchVersion($agent, '/Android\s([0-9.]+)/i'));
        }

        if (self::contains($agent, '/Android/i')) {
            return 'Android';
        }

        if (self::contains($agent, '/(?:iPad|iPadOS).*OS\s([0-9_]+)/i')) {
            return self::label('iPadOS', self::matchVersion($agent, '/(?:iPad|iPadOS).*OS\s([0-9_]+)/i'));
        }

        if (self::contains($agent, '/(?:CPU(?: iPhone)? OS|iPhone OS|CPU OS)\s([0-9_]+)/i')) {
            return self::label('iOS', self::matchVersion($agent, '/(?:CPU(?: iPhone)? OS|iPhone OS|CPU OS)\s([0-9_]+)/i'));
        }

        if (self::contains($agent, '/\b(iPhone|iPod)\b/i')) {
            return 'iOS';
        }

        if (self::contains($agent, '/Mac OS X\s([0-9_]+)/i')) {
            return self::label('macOS', self::matchVersion($agent, '/Mac OS X\s([0-9_]+)/i'));
        }

        if (self::contains($agent, '/\bMacintosh\b|\bMac OS\b/i')) {
            return 'macOS';
        }

        if (self::contains($agent, '/CrOS [^ ]+ ([0-9.]+)/i')) {
            return self::label('ChromeOS', self::matchVersion($agent, '/CrOS [^ ]+ ([0-9.]+)/i'));
        }

        if (self::contains($agent, '/KaiOS\/([0-9.]+)/i')) {
            return self::label('KaiOS', self::matchVersion($agent, '/KaiOS\/([0-9.]+)/i'));
        }

        if (self::contains($agent, '/Ubuntu(?:\/([0-9.]+))?/i')) {
            return self::label('Ubuntu', self::matchVersion($agent, '/Ubuntu(?:\/([0-9.]+))?/i'));
        }

        if (self::contains($agent, '/Debian/i')) {
            return 'Debian';
        }

        if (self::contains($agent, '/Fedora/i')) {
            return 'Fedora';
        }

        if (self::contains($agent, '/CentOS/i')) {
            return 'CentOS';
        }

        if (self::contains($agent, '/Arch Linux/i')) {
            return 'Arch Linux';
        }

        if (self::contains($agent, '/Linux/i')) {
            return 'Linux';
        }

        return 'Unknown';
    }

    // 获取浏览器信息
    static public function getBrowser($agent)
    {
        $agent = self::normalizeAgent($agent);
        if ($agent === '') {
            return self::browserDisplay('Unknown');
        }

        $browser = self::detectBrowserInfo($agent);

        return self::browserDisplay($browser['name'], $browser['version'], $browser['icon']);
    }

    // 获取操作系统信息
    static public function getOs($agent)
    {
        $agent = self::normalizeAgent($agent);
        if ($agent === '') {
            return 'Unknown';
        }

        return self::detectOsInfo($agent);
    }
}
