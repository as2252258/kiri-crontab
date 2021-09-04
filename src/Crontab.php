<?php


namespace Kiri\Crontab;


use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Application;
use Kiri\Kiri;

/**
 * Class Async
 * @package Kiri
 * @property Application $application
 */
abstract class Crontab implements CrontabInterface, \Serializable
{

    const WAIT_END = 'crontab:wait:execute';


    protected string $name = '';


    protected mixed $params;


    protected int $tickTime;


    protected bool $isLoop;


    protected int $timerId = -1;


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
            $redis = $this->application->getRedis();
            $name_md5 = $this->getName();

            defer(fn() => $this->onRecover($name_md5));

            call_user_func([$this, 'execute']);

            $redis->hDel(self::WAIT_END, $name_md5);
            echo date('Y-m-d H:i:s');
        } catch (\Throwable $throwable) {
            $this->application->addError($throwable, 'throwable');
        }
    }


    /**
     * @param $name_md5
     * @throws \Kiri\Exception\NotFindClassException
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function onRecover($name_md5)
    {
        try {
            echo date('Y-m-d H:i:s');

            $redis = $this->application->getRedis();

            /** @var \Kiri\Crontab\Producer $crontab */
            $crontab = Kiri::getFactory()->get('crontab');
            if ($redis->sIsMember(Producer::CRONTAB_STOP_KEY, $name_md5)) {
                var_dump('is exec Stop');
                return $redis->sRem(Producer::CRONTAB_STOP_KEY, $name_md5);
            }
            if ($this->isPropagationStopped()) {
                var_dump('is auto Stop');
                return true;
            }
            var_dump('is add task');
            return $crontab->task($this);
        } catch (\Throwable $throwable) {
            var_dump($throwable);
        }
    }


    /**
     * @return int
     */
    private function next(): int
    {
        return time() + $this->getTickTime();
    }


    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }


    /**
     * @param string $data
     * @return mixed
     */
    public function unserialize($data): mixed
    {
        return unserialize($data);
    }


    /**
     * @param $class
     * @return string
     */
    public static function getSerialize($class): string
    {
        return serialize($class);
    }


}
