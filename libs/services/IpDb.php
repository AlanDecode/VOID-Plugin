<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/../vendor/ip2region/xdb/Searcher.class.php';

use ip2region\xdb\IPv4;
use ip2region\xdb\IPv6;
use ip2region\xdb\Searcher;
use ip2region\xdb\Util;

class VOID_IpDb_Exception extends Exception {}

class VOID_IpDb
{
    private static $vectorIndexes = array();

    public static function locate($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return 'unknown';
        }

        $special = self::classifySpecialIp($ip);
        if ($special !== null) {
            return $special;
        }

        try {
            $region = self::searchRegion($ip);
            return self::formatRegion($region);
        } catch (Exception $e) {
            return 'unknown';
        }
    }

    public static function bootstrapOrFail()
    {
        self::inspectDatabaseFile(self::ipv4File(), 4);
        self::inspectDatabaseFile(self::ipv6File(), 6);
    }

    private static function searchRegion($ip)
    {
        $ipBytes = Util::parseIP($ip);
        if ($ipBytes === null) {
            throw new VOID_IpDb_Exception('IP 格式无效。');
        }

        $key = strlen($ipBytes) === 16 ? 'ipv6' : 'ipv4';
        $version = $key === 'ipv6' ? IPv6::default() : IPv4::default();
        $dbFile = $key === 'ipv6' ? self::ipv6File() : self::ipv4File();

        if (!isset(self::$vectorIndexes[$key])) {
            $vectorIndex = Util::loadVectorIndexFromFile($dbFile);
            if ($vectorIndex === null) {
                throw new VOID_IpDb_Exception('数据库索引加载失败。');
            }
            self::$vectorIndexes[$key] = $vectorIndex;
        }

        $searcher = Searcher::newWithVectorIndex($version, $dbFile, self::$vectorIndexes[$key]);
        try {
            return (string)$searcher->search($ip);
        } finally {
            $searcher->close();
        }
    }

    private static function formatRegion($region)
    {
        $region = trim((string)$region);
        if ($region === '') {
            return 'unknown';
        }

        $parts = explode('|', $region);
        $filtered = array();
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '' || $part === '0') {
                continue;
            }
            if (preg_match('/^[A-Z]{2}$/', $part)) {
                continue;
            }
            if ($part === '中国') {
                continue;
            }
            if (!in_array($part, $filtered, true)) {
                $filtered[] = $part;
            }
        }

        if (!count($filtered)) {
            return 'unknown';
        }

        return implode(' / ', $filtered);
    }

    private static function classifySpecialIp($ip)
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return '本机地址';
        }

        $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        if ($valid === false) {
            return 'unknown';
        }

        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($public !== false) {
            return null;
        }

        $noPriv = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE);
        if ($noPriv === false) {
            return '私有网络';
        }

        return '保留地址';
    }

    private static function inspectDatabaseFile($file, $expectedVersion)
    {
        if (!is_file($file)) {
            throw new VOID_IpDb_Exception('数据库文件不存在：' . basename($file));
        }
        if (!is_readable($file)) {
            throw new VOID_IpDb_Exception('数据库文件不可读：' . basename($file));
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            throw new VOID_IpDb_Exception('数据库文件打开失败：' . basename($file));
        }

        try {
            $header = Util::loadHeader($handle);
            if ($header === null) {
                throw new VOID_IpDb_Exception('数据库头信息读取失败：' . basename($file));
            }

            $verifyErr = Util::verify($handle);
            if ($verifyErr !== null) {
                throw new VOID_IpDb_Exception('数据库校验失败：' . $verifyErr);
            }

            $version = Util::versionFromHeader($header);
        } catch (Exception $e) {
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        if ($expectedVersion !== null && (int)$version->id !== (int)$expectedVersion) {
            throw new VOID_IpDb_Exception('数据库版本与文件类型不匹配：' . basename($file));
        }

        return array(
            'ip_version' => (int)$version->id,
            'key' => (int)$version->id === 6 ? 'ipv6' : 'ipv4',
            'version_name' => (string)$version->name
        );
    }

    private static function ipv4File()
    {
        return self::dataDir() . '/data/ip2region_v4.xdb';
    }

    private static function ipv6File()
    {
        return self::dataDir() . '/data/ip2region_v6.xdb';
    }

    private static function dataDir()
    {
        return dirname(__DIR__) . '/data/ip2region';
    }
}
