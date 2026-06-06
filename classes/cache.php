<?php
class Cache
{
    private static $dir = __DIR__ . '/../cache/';

    public static function get($key)
    {
        $file = self::$dir . md5($key) . '.cache';
        if (file_exists($file) && time() - filemtime($file) < 3600) {
            return unserialize(file_get_contents($file));
        }
        return false;
    }

    public static function set($key, $value, $ttl = 3600)
    {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
        $file = self::$dir . md5($key) . '.cache';
        return file_put_contents($file, serialize($value)) !== false;
    }

    public static function delete($key)
    {
        $file = self::$dir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }

    public static function clear()
    {
        $files = glob(self::$dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }

    public static function clearByPrefix($prefix)
    {
        $files = glob(self::$dir . $prefix . '*');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}