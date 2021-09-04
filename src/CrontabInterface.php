<?php

namespace Kiri\Crontab;

use Psr\EventDispatcher\StoppableEventInterface;

interface CrontabInterface extends StoppableEventInterface
{

    /**
     *
     */
	public function process(): void;

}
