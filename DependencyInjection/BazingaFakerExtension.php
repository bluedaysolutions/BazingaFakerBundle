<?php

/**
 * This file is part of the FakerBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Bazinga\Bundle\FakerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class BazingaFakerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor      = new Processor();
        $configuration  = new Configuration();
        $config         = $processor->processConfiguration($configuration, $configs);

        if (!$container->hasDefinition('faker')) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('services.xml');
        }

        if (null !== $config['seed']) {
            $container
                ->getDefinition('faker.generator')
                ->addMethodCall('seed', array($config['seed']))
                ;
        }

        $definition = $container
            ->getDefinition('faker.generator')
            ->setArguments(array($config['locale']))
            ;

        $definition->setFactory(array(
            $container->getParameter('faker.generator.class'),
            'create'
        ));

        switch ($config['orm']) {
            case 'propel':
                $container->setParameter('faker.populator.class', \Faker\ORM\Propel\Populator::class);
                $container->setParameter('faker.entity.class', \Faker\ORM\Propel\EntityPopulator::class);
                break;

            case 'doctrine':
                $container
                    ->getDefinition('faker.populator')
                    ->replaceArgument(1, new Reference('doctrine.orm.entity_manager'))
                    ;

                $container->setParameter('faker.populator.class', \Faker\ORM\Doctrine\Populator::class);
                $container->setParameter('faker.entity.class', \Faker\ORM\Doctrine\EntityPopulator::class);
                break;

            case 'mandango':
                $container
                    ->getDefinition('faker.populator')
                    ->replaceArgument(1, new Reference('mandango'))
                    ;

                $container->setParameter('faker.populator.class', \Faker\ORM\Mandango\Populator::class);
                $container->setParameter('faker.entity.class', \Faker\ORM\Mandango\EntityPopulator::class);
                break;
        }

        if ($config['populator']) {
            $container->setParameter('faker.populator.class', $config['populator']);
        }

        if ($config['entity']) {
            $container->setParameter('faker.entity.class', $config['entity']);
        }

        $i = 0;
        foreach ($config['entities'] as $class => $params) {
            $number = $params['number'] ?? 5;

            switch ($config['orm']) {
                case 'propel':
                    $container
                        ->register('faker.entities.' . $i)
                        ->setClass($container->getParameter('faker.entity.class'))
                        ->setArguments(array($class))
                        ;
                    break;

                case 'doctrine':
                    $definition = $container->register('faker.entities.'.$i.'.metadata');

                    $definition
                        ->setFactory(array(new Reference('doctrine.orm.entity_manager'), 'getClassMetadata'))
                        ->setClass(\Doctrine\ORM\Mapping\ClassMetadata::class)
                        ->setArguments(array($class));

                    $container
                        ->register('faker.entities.'.$i)
                        ->setClass($container->getParameter('faker.entity.class'))
                        ->setArguments(array(new Reference('faker.entities.' . $i . '.metadata')))
                        ;
                    break;

                case 'mandango':
                    $container
                        ->register('faker.entities.'.$i)
                        ->setClass($container->getParameter('faker.entity.class'))
                        ->setArguments(array($class))
                        ;
                    break;
            }

            $formatters = array();
            if (isset($params['custom_formatters'])) {
                $j = 0;
                foreach ($params['custom_formatters'] as $column => $formatter) {
                    $method = $formatter['method'];
                    $parameters = $formatter['parameters'];
                    $unique = $formatter['unique'];
                    $optional = $formatter['optional'];

                    if (null === $method) {
                        $formatters[$column] = null;
                    } else {
                        $definition = $container->setDefinition('faker.entities.' . $i . '.formatters.' . $j, new Definition(
                            'closure',
                            array(new Reference('faker.generator'), $method, $parameters, $unique, $optional)
                        ));

                        $definition->setFactory(array(new Reference('faker.formatter_factory'), 'createClosure'));

                        $formatters[$column] = new Reference('faker.entities.' . $i . '.formatters.' . $j);
                        $j++;
                    }
                }
            }

            $customModifiers = array();
            if (isset($params['custom_modifiers'])) {
                $j = 0;
                foreach ($params['custom_modifiers'] as $methodName => $arguments) {
                    foreach ($arguments as $key => $formatter) {
                        $method = $formatter['method'];
                        $parameters = $formatter['parameters'];

                        if (null === $method) {
                            $customModifiers[$methodName][$key] = null;
                        } else {
                            $definition = $container->setDefinition('faker.entities.' . $i . '.formatters.' . $j, new Definition(
                                'closure',
                                array(new Reference('faker.generator'), $method, $parameters)
                            ));

                            $definition->setFactory(array(new Reference('faker.formatter_factory'), 'createClosure'));

                            $customModifiers[$methodName][$key] = new Reference('faker.entities.' . $i . '.formatters.' . $j);
                        }
                        $j++;
                    }
                }
            }

            $definition = $container->getDefinition('faker.populator');
            switch ($config['orm']) {
                case 'doctrine':
                    $definition->addMethodCall('addEntity', array(new Reference('faker.entities.' . $i), $number, $formatters, $customModifiers, $params['generate_id']));
                    break;
                default:
                    $definition->addMethodCall('addEntity', array(new Reference('faker.entities.' . $i), $number, $formatters));
                    break;
            }

            $i++;
        }
    }
}
