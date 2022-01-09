<?php


namespace Kiri\Crontab;


use Exception;
use Kiri\Cache\Redis;
use Kiri\Kiri;
use Psr\Log\LoggerInterface;
use Server\Abstracts\BaseProcess;
use Server\ServerManager;
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


	/**
	 * @var int
	 */
	private int $workerNum = 0;

	public string $name = 'crontab zookeeper';


	/**
	 * @param Process $process
	 * @throws Exception
	 */
	public function process(Process $process): void
	{
		Timer::tick(300, [$this, 'loop']);
	}


	/**
	 * @throws Exception
	 */
	public function loop($timerId)
	{
		if ($this->isStop()) {
			Timer::clear($timerId);
			return;
		}
		$redis = Kiri::getDi()->get(Redis::class);
		$range = $this->loadCarobTask($redis);
		foreach ($range as $value) {
			$this->dispatch($value, $redis);
		}
	}


	/**
	 * @param $value
	 * @param $redis
	 * @throws Exception
	 */
	private function dispatch($value, $redis)
	{
		$logger = Kiri::getDi()->get(LoggerInterface::class);
		try {
			$handler = $redis->get(Producer::CRONTAB_PREFIX . $value);
			$redis->del(Producer::CRONTAB_PREFIX . $value);
			if (!empty($handler)) {
				Coroutine::create(function ($handler) {
					$serialize = swoole_unserialize($handler);
					if (is_null($serialize)) {
						return;
					}
					$serialize->process();
				}, $handler);
			}
		} catch (Throwable $exception) {
			$logger->addError($exception);
		}
	}


	/**
	 * @return int
	 * @throws Exception
	 */
	private function getWorker(): int
	{
		$settings = Kiri::getDi()->get(ServerManager::class)->getSetting();
		if ($this->workerNum == 0) {
			$this->workerNum = $settings['worker_num'] + ($settings['task_worker_num'] ?? 0);
		}
		return random_int(0, $this->workerNum - 1);
	}


	/**
	 * @param Redis|\Redis $redis
	 * @return array
	 */
	private function loadCarobTask(Redis|\Redis $redis): array
	{
		$script = <<<SCRIPT
local _two = redis.call('zRangeByScore', KEYS[1], '0', ARGV[1])

if (table.getn(_two) > 0) then
	redis.call('ZREM', KEYS[1], unpack(_two))
end

return _two
SCRIPT;
		return $redis->eval($script, [Producer::CRONTAB_KEY, (string)time()], 1);
	}

}
