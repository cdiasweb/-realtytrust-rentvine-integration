<?php

namespace RedisCache;

class RedisClient
{
    public ?\Redis $redis = null;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('redis', 6379); // Connect to Redis service in Docker
    }
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis, $name], $arguments);
    }

}
