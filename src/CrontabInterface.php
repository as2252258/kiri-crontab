<?php

namespace Kiri\Crontab;

interface CrontabInterface
{


	/**
	 *
	 */
	public function onMaxExecute(): void;


	/**
	 * @return bool
	 */
	public function isStop(): bool;



	public function process(): void;

}
