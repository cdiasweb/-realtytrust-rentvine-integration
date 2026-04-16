<?php

namespace RedisCache;

use Util\Env;

class RedisClient
{
    public ?\Redis $redis = null;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect(Env::getRedisHost() ?: 'redis', (int)(Env::getRedisPort() ?: 6379));
    }
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis, $name], $arguments);
    }

}
