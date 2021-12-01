<?php


namespace Kiri\Crontab;


use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Application;
use Kiri\Cache\Redis;
use Kiri\Exception\NotFindClassException;
use Kiri\Kiri;
use ReflectionException;

/**
 * Class Async
 * @package Kiri
 * @property Application $application
 */
abstract class Crontab implements CrontabInterface
{

	const WAIT_END = 'crontab:wait:execute';


	public string $name = '';


	public mixed $params;


	public int $tickTime;


	public bool $isLoop;


	public int $timerId = -1;


	/**
	 * Crontab constructor.
	 * @param mixed $params
	 * @param false $isLoop
	 * @param int $tickTime
	 */
	public function __construct(mixed $params, bool $isLoop = false, int $tickTime = 1)
	{
		$this->params = $params;
		$this->isLoop = $isLoop;
		$this->tickTime = $tickTime;
	}


	/**
	 * @return Application
	 */
	#[Pure] private function getApplication(): Application
	{
		return Kiri::app();
	}


	/**
	 * @return string
	 */
	#[Pure] public function getName(): string
	{
		return md5($this->name);
	}

	/**
	 * @return mixed
	 */
	public function getParams(): mixed
	{
		return $this->params;
	}

	/**
	 * @return int
	 */
	public function getTickTime(): int
	{
		return $this->tickTime;
	}

	/**
	 * @return bool
	 */
	public function isLoop(): bool
	{
		return $this->isLoop;
	}


	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}


	/**
	 * @return int
	 */
	public function getTimerId(): int
	{
		return $this->timerId;
	}


	/**
	 * @param $name
	 * @return mixed
	 */
	#[Pure] public function __get($name): mixed
	{
		if ($name === 'application') {
			return $this->getApplication();
		}
		if (!isset($this->params[$name])) {
			return null;
		}
		return $this->params[$name];
	}


	/**
	 * @throws Exception
	 */
	public function process(): void
	{
		try {
			$redis = Kiri::getDi()->get(Redis::class);

			$name_md5 = $this->getName();

			call_user_func([$this, 'execute']);

			$redis->hDel(self::WAIT_END, $name_md5);
		} catch (\Throwable $throwable) {
			$this->application->addError($throwable, 'throwable');
		} finally {
			$this->onRecover($this->getName());
		}
	}


	/**
	 * @param $name_md5
	 * @return bool|int
	 * @throws ReflectionException
	 * @throws Exception
	 */
	protected function onRecover($name_md5): bool|int
	{
		$redis = Kiri::getDi()->get(Redis::class);
		$crontab = Kiri::getDi()->get(Producer::class);
		if ($redis->sIsMember(Producer::CRONTAB_STOP_KEY, $name_md5)) {
			return $redis->sRem(Producer::CRONTAB_STOP_KEY, $name_md5);
		}
		if ($this->isPropagationStopped()) {
			return true;
		}
		return $crontab->task($this);
	}


	/**
	 * @return int
	 */
	private function next(): int
	{
		return time() + $this->getTickTime();
	}


	/**
	 * @param $class
	 * @return string
	 */
	public static function serialize($class): string
	{
		return serialize($class);
	}


}
