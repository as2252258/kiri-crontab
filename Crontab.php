<?php


namespace Kiri\Crontab;


use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri;
use Kiri\Di\Context;
use Kiri\Redis\Redis;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * Class Async
 * @package Kiri
 */
abstract class Crontab implements CrontabInterface
{

	const WAIT_END = 'crontab:wait:execute';


	public string $name = '';


	public mixed $params;


	public int $tickTime;


	public bool $isLoop;


	public int $retry = 1;


	/**
	 * Crontab constructor.
	 * @param mixed $params
	 * @param false $isLoop
	 * @param int $tickTime
	 * @param int $retry
	 */
	public function __construct(mixed $params, bool $isLoop = false, int $tickTime = 1, int $retry = 1)
	{
		$this->params = $params;
		$this->isLoop = $isLoop;
		$this->tickTime = $tickTime;
		$this->retry = $retry;
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
	 * @return array
	 */
	public function __serialize(): array
	{
		return [
			'name'     => $this->name,
			'params'   => $this->params,
			'tickTime' => $this->tickTime,
			'isLoop'   => $this->isLoop,
			'retry'    => $this->retry
		];
	}


	/**
	 * @param array $data
	 * @return void
	 */
	public function __unserialize(array $data): void
	{
		$this->name = $data['name'];
		$this->params = $data['params'];
		$this->tickTime = $data['tickTime'];
		$this->isLoop = $data['isLoop'];
		$this->retry = $data['retry'];
	}


	/**
	 * @param $name
	 * @return mixed
	 */
	#[Pure] public function __get($name): mixed
	{
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
			$redis->hSet(Crontab::WAIT_END, $name_md5, serialize($this));

			call_user_func([$this, 'execute']);

			$redis->hDel(self::WAIT_END, $name_md5);
		} catch (\Throwable $throwable) {
			$logger = Kiri::getDi()->get(LoggerInterface::class);
			$logger->error('crontab execute fail.[' . $throwable->getMessage() . ']', [throwable($throwable)]);
			if (Context::increment('retry.number') >= $this->retry) {
				return;
			}
			$this->process();
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
		if ($redis->sIsMember(Producer::CRONTAB_STOP_KEY, $name_md5)) {
			return $redis->sRem(Producer::CRONTAB_STOP_KEY, $name_md5);
		}
		if ($this->isPropagationStopped()) {
			return true;
		}
		return Kiri::getDi()->get(Producer::class)->task($this);
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
