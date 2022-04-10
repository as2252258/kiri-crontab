<?php


namespace Kiri\Crontab;


use Exception;
use Kiri;
use Kiri\Redis\Redis;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\Broadcast\OnBroadcastInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

/**
 * Class Zookeeper
 * @package Kiri\Process
 */
class Zookeeper extends BaseProcess
{


    public string $name = 'crontab zookeeper';


    protected bool $enable_coroutine = true;


    public ?int $timerId = null;


    /**
     * @param Process $process
     * @throws Exception
     */
    public function process(Process $process): void
    {
        $logger = Kiri::getDi()->get(LoggerInterface::class);
        Timer::tick(100, [$this, 'loop'], $logger);
    }


    /**
     * @param OnBroadcastInterface $message
     * @return void
     */
    public function onBroadcast(OnBroadcastInterface $message): void
    {
        $logger = Kiri::getDi()->get(LoggerInterface::class);
        $logger->debug($message->data . '::' . static::class);
    }


    public function onSigterm(): static
    {
        Coroutine::create(function () {
            $data = Coroutine::waitSignal(SIGTERM, -1);
            if ($data) {
                Timer::clear($this->timerId);

                $this->isStop = true;
            }
        });
        return $this;
    }


    /**
     * @throws Exception
     */
    public function loop($timerId, $logger)
    {
        $this->timerId = $timerId;

        $redis = Kiri::getDi()->get(Redis::class);

        $script = <<<SCRIPT
local _two = redis.call('zRangeByScore', KEYS[1], '0', ARGV[1])

if (table.getn(_two) > 0) then
	redis.call('ZREM', KEYS[1], unpack(_two))
end

return _two
SCRIPT;
        $range = $redis->eval($script, [Producer::CRONTAB_KEY, (string)time()], 1);
        foreach ($range as $value) {
            try {
                $handler = $redis->get(Producer::CRONTAB_PREFIX . $value);
                $redis->del(Producer::CRONTAB_PREFIX . $value);
                if (!empty($handler)) {
                    $this->execute($handler);
                }
            } catch (Throwable $exception) {
                $logger->addError($exception);
            }
        }
    }


    private function execute($handler)
    {
        go(function () use ($handler) {
            $serialize = swoole_unserialize($handler);
            if (is_null($serialize)) {
                return;
            }
            $serialize->process();
        });
    }


}
