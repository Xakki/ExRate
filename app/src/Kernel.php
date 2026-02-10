<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('container.autowiring.strict_mode', true);
        $container->setParameter('container.dumper.inline_class_loader', true);
        // @phpstan-ignore argument.type
        date_default_timezone_set(getenv('TZ'));

        $loader->load($this->getProjectDir().'/config/{packages}/*.yaml', 'glob');
        $loader->load($this->getProjectDir().'/config/{packages}/'.$this->environment.'/*.yaml', 'glob');
        $loader->load($this->getProjectDir().'/config/services.yaml');
        $loader->load($this->getProjectDir().'/config/{services}_'.$this->environment.'.yaml', 'glob');
    }
}
