<?php


namespace Kiri\Crontab;


use JetBrains\PhpStorm\Pure;

/**
 * Class DefaultCrontab
 * @package Kiri\Crontab
 */
class DefaultCrontab extends Crontab
{


    public int $searchNum = 0;


    /**
     *
     */
    #[Pure] public function isPropagationStopped(): bool
    {
        if ($this->searchNum >= 50) {
            return true;
        }
        return !$this->isLoop();
    }


    public function execute(): void
    {
        $this->searchNum += 1;
    }
}
