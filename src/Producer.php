<?php


namespace Kiri\Crontab;


use Exception;
use Kiri\Abstracts\Component;
use Kiri\Kiri;


/**
 * Class Producer
 * @package Kiri\Abstracts
 */
class Producer extends Component
{

    const CRONTAB_KEY = '_application:{crontab}:system:crontab';
    const CRONTAB_STOP_KEY = '_application:{crontab}:system:stop:crontab';
    const CRONTAB_PREFIX = 'CRONTAB:';


    /**
     * @param Crontab $crontab
     * @return bool
     * @throws \Exception
     */
    public function task(Crontab $crontab)
    {
        $redis = Kiri::app()->getRedis();

        $name = $crontab->getName();
        if ($redis->exists(self::CRONTAB_KEY) && $redis->type(self::CRONTAB_KEY) !== \Redis::REDIS_ZSET) {
            throw new Exception('Cache key ' . self::CRONTAB_KEY . ' types error.');
        }

        $redis->del(Producer::CRONTAB_PREFIX . $name);
        $redis->sRem(Producer::CRONTAB_STOP_KEY, $name);
        $redis->zRem(Producer::CRONTAB_KEY, $name);

        $redis->zAdd(Producer::CRONTAB_KEY, time() + $crontab->getTickTime(), $name);

        var_dump(Producer::CRONTAB_PREFIX . $name, $crontab->serialize());
        return $redis->set(Producer::CRONTAB_PREFIX . $name, $crontab->serialize());
    }


    /**
     * @param string $name
     * @throws Exception
     */
    public function clear(string $name)
    {
        $name = md5($name);

        $redis = Kiri::app()->getRedis();
        $redis->sAdd(Producer::CRONTAB_STOP_KEY, $name);

        $redis->del(Producer::CRONTAB_PREFIX . $name);
        $redis->zRem(Producer::CRONTAB_KEY, $name);
    }


    /**
     * @param string $name
     * @return bool
     * @throws Exception
     */
    public function exists(string $name): bool
    {
        $name = md5($name);
        $redis = Kiri::app()->getRedis();
        if ($redis->exists(Producer::CRONTAB_PREFIX . $name)) {
            return true;
        }
        if ($redis->zRank(Producer::CRONTAB_KEY, $name)) {
            return true;
        }
        if ($redis->hExists(Crontab::WAIT_END, $name)) {
            return true;
        }
        return false;
    }


    /**
     * @throws Exception
     */
    public function clearAll()
    {
        $redis = Kiri::app()->getRedis();
        $data = $redis->zRange(Producer::CRONTAB_KEY, 0, -1);
        foreach ($data as $datum) {
            $redis->sAdd(Producer::CRONTAB_STOP_KEY, $datum);
            $redis->del(Producer::CRONTAB_PREFIX . $datum);
        }
        $redis->release();
    }


}
