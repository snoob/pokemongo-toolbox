<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Symfony finds the project directory by walking up to a composer.json, which the
     * archive does not ship — it would otherwise settle on src/.
     */
    #[\Override]
    public function getProjectDir(): string
    {
        return PharEnvironment::isRunning() ? PharEnvironment::root() : parent::getProjectDir();
    }

    /**
     * A PHAR is read-only, so the compiled container and the logs move to the user's
     * cache directory.
     */
    #[\Override]
    public function getCacheDir(): string
    {
        return PharEnvironment::isRunning()
            ? PharEnvironment::writableDir() . '/cache/' . $this->environment
            : parent::getCacheDir();
    }

    #[\Override]
    public function getLogDir(): string
    {
        return PharEnvironment::isRunning() ? PharEnvironment::writableDir() . '/log' : parent::getLogDir();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $config = new ConfigFiles($this->getConfigDir());

        foreach ([...$config->in('/packages'), ...$config->in('/packages/' . $this->environment)] as $file) {
            $container->import($file);
        }

        foreach ($config->in('', 'services') as $file) {
            $container->import($file);
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $config = new ConfigFiles($this->getConfigDir());

        foreach ([...$config->in('', 'routes'), ...$config->in('/routes')] as $file) {
            $routes->import($file);
        }
    }
}
