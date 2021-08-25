<?php


namespace Kiri\Crontab;


use Exception;
use JetBrains\PhpStorm\Pure;
use Server\SInterface\PipeMessage;
use Kiri\Application;
use Kiri\Kiri;
use Swoole\Timer;

/**
 * Class Async
 * @package Kiri
 * @property Application $application
 */
abstract class Crontab implements PipeMessage, CrontabInterface
{

	const WAIT_END = 'crontab:wait:execute';


	private string $name = '';


	private mixed $params;


	private int $tickTime;


	private bool $isLoop;


	private int $timerId = -1;


	private int $max_execute_number = -1;


	private int $execute_number = 0;


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
	 * @return $this
	 */
	public function increment(): static
	{
		$this->execute_number += 1;
		return $this;
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
	 * @return int
	 */
	public function getMaxExecuteNumber(): int
	{
		return $this->max_execute_number;
	}

	/**
	 * @return int
	 */
	public function getExecuteNumber(): int
	{
		return $this->execute_number;
	}


	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}


	/**
	 * @param int $max_execute_number
	 */
	public function setMaxExecuteNumber(int $max_execute_number): void
	{
		$this->max_execute_number = $max_execute_number;
	}

	/**
	 * @param int $execute_number
	 */
	public function setExecuteNumber(int $execute_number): void
	{
		$this->execute_number = $execute_number;
	}

	/**
	 * @return int
	 */
	public function getTimerId(): int
	{
		return $this->timerId;
	}


	/**
	 *
	 * @throws Exception
	 */
	public function clearTimer()
	{
		$this->application->warning('crontab timer clear.');
		if (Timer::exists($this->timerId)) {
			Timer::clear($this->timerId);
		}
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
	public function execute(): void
	{
		try {
			defer(fn() => $this->afterExecute());
			$redis = $this->application->getRedis();

			$name_md5 = $this->getName();
			$redis->hSet(self::WAIT_END, $name_md5, static::getSerialize($this));
			call_user_func([$this, 'process']);
			$this->execute_number += 1;
			$redis->hDel(self::WAIT_END, $name_md5);
		} catch (\Throwable $throwable) {
			$this->application->addError($throwable, 'throwable');
		}
	}


	/**
	 * @throws Exception
	 */
	public function afterExecute()
	{
		if ($this->isRecover() !== 999) {
			return;
		}
		$redis = $this->application->getRedis();
		$name = $this->getName();
		if (!$redis->exists('stop:crontab:' . $name)) {
			$redis->set('crontab:' . $name, swoole_serialize($this));
			$tickTime = time() + $this->getTickTime();
			$redis->zAdd(Producer::CRONTAB_KEY, $tickTime, $name);
		} else {
			$redis->del('crontab:' . $name);
			$redis->del('stop:crontab:' . $name);
		}
	}


	/**
	 * @return bool|int
	 * @throws Exception
	 */
	public function isRecover(): bool|int
	{
		try {
			$redis = $this->application->getRedis();
			$crontab_name = $this->getName();
			if ($redis->exists('stop:crontab:' . $crontab_name)) {
				return $redis->del('stop:crontab:' . $crontab_name);
			}
			if ($this->isExit()) {
				return $redis->del('crontab:' . $crontab_name);
			}
			if (!$this->isMaxExecute()) {
				return 999;
			}
			call_user_func([$this, 'onMaxExecute']);
			return $redis->del('crontab:' . $crontab_name);
		} catch (\Throwable $throwable) {
			return $this->application->addError($throwable, 'throwable');
		}
	}


	/**
	 * @param $class
	 * @return string
	 */
	public static function getSerialize($class): string
	{
		return serialize($class);
	}


	/**
	 * @return bool
	 */
	private function isExit(): bool
	{
		if ($this->isStop() || !$this->isLoop) {
			return true;
		}
		return false;
	}


	/**
	 * @return bool
	 */
	private function isMaxExecute(): bool
	{
		if ($this->max_execute_number !== -1) {
			return $this->execute_number >= $this->max_execute_number;
		}
		return false;
	}


}
