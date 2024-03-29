<?php


namespace Kiri\Crontab;


use Exception;
use Kiri;
use Kiri\Exception\ConfigException;
use Kiri\Redis\Redis;
use Kiri\Server\Abstracts\BaseProcess;
use Kiri\Server\Broadcast\OnBroadcastInterface;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

/**
 * Class Zookeeper
 * @package Kiri\Process
 */
class Zookeeper extends BaseProcess
{

	/**
	 * @var string
	 */
	public string $name = 'crontab zookeeper';


	/**
	 * @var Process|null
	 */
	public ?Process $process;


	/**
	 * @var bool
	 */
	protected bool $enable_coroutine = false;


	/**
	 * @param Process|null $process
	 * @throws ConfigException
	 */
	public function process(?Process $process): void
	{
		$this->process = $process;
		$logger = Kiri::getDi()->get(LoggerInterface::class);

		$redis = Kiri::getDi()->get(Redis::class);
		while ($this->isStop() === false) {
			$this->loop($redis, $logger);

			usleep(100 * 1000);
		}
		$redis->destroy();
	}


	/**
	 * @return $this
	 */
	public function onSigterm(): static
	{
		pcntl_signal(SIGTERM, function () {
			$this->onProcessStop();
		});
		return $this;
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
		if (!$range) {
			return;
		}
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
		$swollen = Kiri::getDi()->get(Kiri\Server\ServerInterface::class);

		$max = $swollen->setting['worker_num'] - 1;

		$swollen->sendMessage($handler, random_int(0, $max));
	}


}
