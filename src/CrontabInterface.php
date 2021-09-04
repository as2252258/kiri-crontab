<?php

namespace Kiri\Crontab;

use Psr\EventDispatcher\StoppableEventInterface;
use Server\SInterface\PipeMessage;

interface CrontabInterface extends StoppableEventInterface, PipeMessage
{

    /**
     *
     */
	public function execute(): void;

}
