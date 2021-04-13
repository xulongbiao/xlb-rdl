<?php

namespace Xlb\Rdl;

//redis实现分布式锁
class RedisDistributedLock
{
    //redis操作类对象
    private $redis;

    //锁的过期时间（秒）
    private static $ttl = 30;

    public function __construct()
    {
        $this->redis = $this->getRedis();
    }

    /**
     * 获取锁
     * @param string $lockKey
     * @param string $lockValue
     * @return bool
     */
    public function getLock($lockKey, $lockValue)
    {
        //KEYS为键名参数，ARGV为附加参数
        //eval第三个参数指定第二个参数数组中前几个是键名参数
        //redis ttl命令说明：当key不存在时返回-2，当key存在但没有设置剩余生存时间时返回-1，否则返回key的剩余生存时间（毫秒）。
        $lua = <<<EOF
            local key = KEYS[1]
            local value = ARGV[1]
            local ttl = ARGV[2]
            if (redis.call('setnx', key, value) == 1) 
            then
                return redis.call('expire', key, ttl)
            elseif (redis.call('ttl', key) == -1) 
            then
                return redis.call('expire', key, ttl)
            end
            return 0
EOF;
        while (true) {
            $res = $this->redis->eval($lua, [$lockKey, $lockValue, self::$ttl], 1);
            if ($res) {
                break;
            }
        }
        return true;
    }

    /**
     * 释放锁
     * @param string $lockKey
     * @param string $lockValue
     * @return bool
     */
    public function unlock($lockKey, $lockValue)
    {
        if ($this->redis->get($lockKey) == $lockValue) {
            $res = $this->redis->del($lockKey);
            if ($res) return true;
            else return false;
        }
        return false;
    }

    //连接redis
    private function getRedis()
    {
        $redis = new \Redis();
        $redis->pconnect('101.132.41.105', 6379);
        $redis->auth('zhj');
        $redis->select(2);
        return $redis;
    }
}

//使用
$obj = new RedisDistributedLock();
//加锁
$lockKey = 'RedisLock:GoodsSystem';
$lockValue = 'abc123'; //实际使用的话这里必须设置一个随机数，且基数较大，从而尽可能避免不同线程产生相同的value值
$getLockRes = $obj->getLock($lockKey, $lockValue);
//执行业务逻辑
sleep(1);
//释放锁
$unlockRes = $obj->unlock($lockKey, $lockValue);
