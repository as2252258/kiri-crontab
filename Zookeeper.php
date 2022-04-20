<?php


namespace Kiri\Crontab;


use Exception;
use Kiri;
use Kiri\Redis\Redis;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\Broadcast\OnBroadcastInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Events\EventDispatch;
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


    public Process $process;

    public ?int $timerId = null;


    /**
     * @param Process $process
     * @throws Exception
     */
    public function process(Process $process): void
    {
        $this->process = $process;
        $logger = Kiri::getDi()->get(LoggerInterface::class);

        $redis = Kiri::getDi()->get(Redis::class);
        while (true) {
            if ($this->isStop()) {
                break;
            }
            $this->loop($redis, $logger);

            usleep(100 * 1000);
        }

        $redis->destroy();

        $process->exit(0);
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


    /**
     * @return $this
     */
    public function onSigterm(): static
    {
        $application = $this;
        pcntl_signal(SIGTERM, static function () use ($application) {
            $application->onProcessStop();
        });
        return $this;
    }


    /**
     * @throws Exception
     */
    public function loop($redis, $logger)
    {
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


    /**
     * @param $handler
     * @return void
     * @throws Exception
     */
    private function execute($handler): void
    {
        $swollen = Kiri::getDi()->get(Kiri\Server\SwooleServerInterface::class);

        $max = $swollen->setting['worker_num'] + ($swollen->setting['task_worker_num'] ?? 0);

        $swollen->sendMessage($handler, random_int(0, $max - 1));
    }


}
