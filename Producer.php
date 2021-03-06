<?php


namespace Kiri\Crontab;


use Exception;
use Kiri\Abstracts\Component;
use Kiri\Redis\Redis;
use Kiri;


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
     * @throws Exception
     */
    public function task(Crontab $crontab): bool
    {
        $redis = Kiri::getDi()->get(Redis::class);

        $name = $crontab->getName();
        if ($redis->exists(self::CRONTAB_KEY) && $redis->type(self::CRONTAB_KEY) !== \Redis::REDIS_ZSET) {
            throw new Exception('Cache key ' . self::CRONTAB_KEY . ' types error.');
        }

        $redis->del(Producer::CRONTAB_PREFIX . $name);
        $redis->sRem(Producer::CRONTAB_STOP_KEY, $name);
        $redis->zRem(Producer::CRONTAB_KEY, $name);

        $redis->zAdd(Producer::CRONTAB_KEY, time() + $crontab->getTickTime(), $name);

        return $redis->set(Producer::CRONTAB_PREFIX . $name, swoole_serialize($crontab));
    }


    /**
     * @param string $name
     * @throws Exception
     */
    public function clear(string $name)
    {
        $name = md5($name);

        $redis = Kiri::getDi()->get(Redis::class);
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
        $redis = Kiri::getDi()->get(Redis::class);
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
        $redis = Kiri::getDi()->get(Redis::class);
        $data = $redis->zRange(Producer::CRONTAB_KEY, 0, -1);
        foreach ($data as $datum) {
            $redis->sAdd(Producer::CRONTAB_STOP_KEY, $datum);
            $redis->del(Producer::CRONTAB_PREFIX . $datum);
        }
        $redis->release();
    }


}
