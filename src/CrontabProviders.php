<?php


namespace Kiri\Crontab;


use Exception;
use ReflectionException;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Exception\ComponentException;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;


/**
 * Class CrontabProviders
 * @package Kiri\Crontab
 */
class CrontabProviders extends Providers
{


	/**
	 * @param Application $application
	 * @throws ConfigException
	 * @throws Exception
	 */
    public function onImport(Application $application)
    {
        $server = $application->getServer();
        $application->set('crontab', ['class' => Producer::class]);
        if (Config::get('crontab.enable') !== true) {
            return;
        }
        $server->addProcess(Zookeeper::class);
    }

}
