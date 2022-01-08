<?php

namespace Kiri\Crontab;

use Psr\EventDispatcher\StoppableEventInterface;
use Server\Contract\OnPipeMessageInterface;

interface CrontabInterface extends StoppableEventInterface, OnPipeMessageInterface
{

    /**
     *
     */
	public function execute(): void;

}
