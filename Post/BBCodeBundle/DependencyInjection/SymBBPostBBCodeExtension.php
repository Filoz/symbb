<?php

namespace SymBB\Post\BBCodeBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class SymBBPostBBCodeExtension extends Extension implements PrependExtensionInterface 
{
    
    public function prepend(ContainerBuilder $container)
    {
        
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('fm_bbcode.yml');
        $loader->load('twig.yml');
    }
        
    public function load(array $configs, ContainerBuilder $container)
    {        
        
        
    }
}
