<?php


namespace Kiri\Crontab;


use Note\Inject;
use Exception;
use Kiri\Cache\Redis;
use Kiri\Error\Logger;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Server\Abstracts\BaseProcess;
use Server\ServerManager;
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
	 * @var ServerManager|null
	 */
	#[Inject(ServerManager::class)]
	public ?ServerManager $manager = null;


	/**
	 * @var Logger|null
	 */
	#[Inject(Logger::class)]
	public ?Logger $logger = null;


	/**
	 * @param Process $process
	 * @throws Exception
	 */
	public function process(Process $process): void
	{
		Timer::tick(300, [$this, 'loop']);
	}


	/**
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function loop()
	{
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
		try {
			$handler = $redis->get(Producer::CRONTAB_PREFIX . $value);
			if (!empty($handler)) {
				$redis->hSet(Crontab::WAIT_END, $value, $handler);
				$redis->del(Producer::CRONTAB_PREFIX . $value);
				$this->manager->sendMessage(swoole_unserialize($handler), $this->getWorker());
			}
		} catch (Throwable $exception) {
			$this->logger->addError($exception);
		}
	}


	/**
	 * @return int
	 * @throws Exception
	 */
	private function getWorker(): int
	{
		$settings = $this->manager->getSetting();
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
