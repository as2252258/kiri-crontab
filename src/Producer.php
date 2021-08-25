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


    /**
     * @param Crontab $crontab
     * @throws Exception
     */
    public function dispatch(Crontab $crontab)
    {
        $redis = Kiri::app()->getRedis();

        $name = $crontab->getName();
        if ($redis->exists(self::CRONTAB_KEY) && $redis->type(self::CRONTAB_KEY) !== \Redis::REDIS_ZSET) {
            throw new Exception('Cache key ' . self::CRONTAB_KEY . ' types error.');
        }

        $redis->del('stop:crontab:' . $name);

        $redis->del('crontab:' . $name);
        $redis->zRem(static::CRONTAB_KEY, $name);

        $redis->zAdd(self::CRONTAB_KEY, time() + $crontab->getTickTime(), $name);
        $redis->set('crontab:' . $name, swoole_serialize($crontab));
    }


    /**
     * @param string $name
     * @throws Exception
     */
    public function clear(string $name)
    {
        $redis = Kiri::app()->getRedis();

        $redis->del('crontab:' . md5($name));
        $redis->zRem(static::CRONTAB_KEY, md5($name));

        $redis->setex('stop:crontab:' . md5($name), 120, 1);
    }


	/**
	 * @param string $name
	 * @return bool
	 * @throws Exception
	 */
    public function exists(string $name): bool
    {
        $redis = Kiri::app()->getRedis();
        if ($redis->exists('crontab:' . md5($name))) {
            return true;
        }
        if ($redis->zRank(static::CRONTAB_KEY, md5($name))) {
            return true;
        }
        if ($redis->hExists(Crontab::WAIT_END, md5($name))) {
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
        $data = $redis->zRange(self::CRONTAB_KEY, 0, -1);
        foreach ($data as $datum) {
            $redis->setex('stop:crontab:' . $datum, 120, 1);
            $redis->del('crontab:' . $datum);
        }
        $redis->release();
    }


}
