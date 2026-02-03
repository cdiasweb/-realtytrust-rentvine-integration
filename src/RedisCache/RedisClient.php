<?php

namespace RedisCache;

use Util\Env;

class RedisClient
{
    public ?\Redis $redis = null;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect('redis', Env::getRedisPort() ?? 6378);
    }
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->redis, $name], $arguments);
    }

}
