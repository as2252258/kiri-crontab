<?php


namespace Kiri\Crontab;


/**
 * Class DefaultCrontab
 * @package Kiri\Crontab
 */
class DefaultCrontab extends Crontab
{

	public bool $isLoop = false;


	public int|string $year = '*';

	public int|string $month = '*';

	public int|string $day = '*';

	public int|string $hour = '*';


	public int|string $minute = '*';

	public int|string $second = '*';


	public function oq()
	{
		return [
			'year'   => $this->year == '*' ? date('Y') : '*',
			'month'  => $this->month == '*' ? date('m') : '*',
			'day'    => $this->day == '*' ? date('d') : '*',
			'hour'   => $this->hour == '*' ? date('H') : '*',
			'minute' => $this->minute == '*' ? date('i') : '*',
			'second' => $this->second == '*' ? date('s') : '*',
		];
	}


	/**
	 * @return bool
	 */
	public function isStop(): bool
	{
		return true;
	}


	/**
	 *
	 */
	public function process(): void
	{
		// TODO: Implement process() method.
	}


	/**
	 *
	 */
	public function onMaxExecute(): void
	{
		// TODO: Implement max_execute() method.
	}
}
