<?php

/**
 * yac共享内存操作类
 */
class YacShmop
{
    private static $_yac = null;
    private static $_keyPrefix = 'zbx_shm_';
    private static $_ttlMaxTime = 3000;  //默认保存3000s 为防止永久贮存及保存时间过久造成内存消耗严重导致数据被踢出

    public static function getYacInstance()
    {
        if (extension_loaded("yac")) {
            if (self::$_yac == null) {
                self::$_yac = new Yac(self::$_keyPrefix);
            }

            return self::$_yac;
        }
    }

    /**
     * add value
     *
     * @param mixed $keys
     * @param mixed $value
     * @param int   $ttl
     *
     * @return mixed
     */
    public static function add($key, $value, $ttl = -1)
    {
        if (empty($key)) {
            return null;
        }

        self::getYacInstance();

        if ($ttl < 0 || $ttl > self::$_ttlMaxTime) {
            $ttl = self::$_ttlMaxTime;
        }

        return self::$_yac->add($key, $value, $ttl);
    }

    /**
     * set value
     *
     * @param mixed $keys
     * @param mixed $value
     * @param int   $ttl
     *
     * @return mixed
     */
    public static function set($key, $value, $ttl = -1)
    {
        if (empty($key)) {
            return null;
        }

        self::getYacInstance();

        if ($ttl < 0 || $ttl > self::$_ttlMaxTime) {
            $ttl = self::$_ttlMaxTime;
        }

        return self::$_yac->set($key, $value, $ttl);
    }

    /**
     * get value
     *
     * @param mixed $keys
     *
     * @return mixed
     */
    public static function get($key)
    {
        if (empty($key)) {
            return null;
        }

        self::getYacInstance();

        return self::$_yac->get($key);
    }

    /**
     * delete yacshm
     *
     * @param mixed $keys
     * @param int   $delay
     *
     * @return mixed
     */
    public static function delete($key, $delay = 0)
    {
        if (empty($key)) {
            return null;
        }

        self::getYacInstance();

        return self::$_yac->delete($key, $delay);
    }

    /**
     * flush yacshm
     *
     * @param void
     *
     * @return mixed
     */
    public static function flush()
    {

        self::getYacInstance();

        return self::$_yac->flush();
    }

    /**
     * get yacshm info
     *
     * @param void
     *
     * @return mixed
     */
    public static function info()
    {

        self::getYacInstance();

        return self::$_yac->info();
    }

    /**
     * Get an item from the yacshm, or store the value.
     *
     * @param string $key
     * @param        $callback
     *
     * @return mixed
     */
    public static function remember($key, $ttl, $callback)
    {

        self::getYacInstance();

        if ($value = self::get($key)) {
            return $value;
        }

        return self::tap($callback(), function ($value) use ($key, $ttl) {
            self::add($key, $value, $ttl);
        });
    }

    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed         $value
     * @param callable|null $callback
     *
     * @return mixed
     */
    private static function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return null;
        }

        $callback($value);

        return $value;
    }

}