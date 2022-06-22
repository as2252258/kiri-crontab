<?php


namespace Kiri\Crontab;


use Kiri\Di\LocalService;
use Kiri\Server\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Exception\ConfigException;


/**
 * Class CrontabProviders
 * @package Kiri\Crontab
 */
class CrontabProviders extends Providers
{


	/**
	 * @param LocalService $application
	 * @return void
	 * @throws ConfigException
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
    public function onImport(LocalService $application): void
    {
        $server = $this->container->get(Server::class);
        if (Config::get('crontab.enable') !== true) {
            return;
        }
        $server->addProcess(Zookeeper::class);
    }

}
