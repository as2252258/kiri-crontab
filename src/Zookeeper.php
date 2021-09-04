<?php


namespace Kiri\Crontab;


use Annotation\Inject;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Cache\Redis;
use Kiri\Error\Logger;
use Kiri\Exception\ConfigException;
use Server\Abstracts\CustomProcess;
use Server\ServerManager;
use Swoole\Process;
use Swoole\Timer;
use Throwable;

/**
 * Class Zookeeper
 * @package Kiri\Process
 */
class Zookeeper extends CustomProcess
{


	/**
	 * @var int
	 */
	private int $workerNum = 0;


	/**
	 * @var ServerManager|null
	 */
	#[Inject(ServerManager::class)]
	public ?ServerManager $manager = null;


	/**
	 * @var Redis|null
	 */
	#[Inject('redis')]
	public ?Redis $redis = null;


	/**
	 * @var Logger|null
	 */
	#[Inject(Logger::class)]
	public ?Logger $logger = null;


	/**
	 * @param Process $process
	 * @return string
	 * @throws ConfigException
	 */
	public function getProcessName(Process $process): string
	{
		$name = Config::get('id', 'system') . '[' . $process->pid . ']';
		if (!empty($prefix)) {
			$name .= '.crontab zookeeper';
		}
		return $name;
	}


	/**
	 * @param Process $process
	 * @throws Exception
	 */
	public function onHandler(Process $process): void
	{
		Timer::tick(300, [$this, 'loop']);
	}


	/**
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function loop()
	{
		defer(fn() => $this->redis->release());
		$range = $this->loadCarobTask();
		foreach ($range as $value) {
			$this->dispatch($value);
		}
	}


	/**
	 * @param $value
	 * @throws Exception
	 */
	private function dispatch($value)
	{
		try {
			$handler = $this->redis->get(Producer::CRONTAB_PREFIX . $value);
			if (!empty($handler)) {
                $this->redis->hSet(Crontab::WAIT_END, $value, $handler);
                $this->redis->del(Producer::CRONTAB_PREFIX . $value);
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
	 * @return array
	 */
	private function loadCarobTask(): array
	{
		$script = <<<SCRIPT
local _two = redis.call('zRangeByScore', KEYS[1], '0', ARGV[1])

if (table.getn(_two) > 0) then
	redis.call('ZREM', KEYS[1], unpack(_two))
end

return _two
SCRIPT;
		return $this->redis->eval($script, [Producer::CRONTAB_KEY, (string)time()], 1);
	}

}
