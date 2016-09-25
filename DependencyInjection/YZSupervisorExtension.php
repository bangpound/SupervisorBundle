<?php

namespace YZ\SupervisorBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class YZSupervisorExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $supervisorsConfiguration = $config['servers'][$config['default_environment']];
        $container->setParameter('supervisor.servers', $supervisorsConfiguration);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        foreach ($supervisorsConfiguration as $serverName => $configuration) {
            //Create GuzzleHttp client
            $guzzleClient = new Definition(\GuzzleHttp\Client::class);
            $guzzleClient->addArgument(['auth' => [$configuration['username'], $configuration['password']]]);

            // Pass the url and the guzzle client to the XmlRpc Client
            $client = new Definition(\fXmlRpc\Client::class);
            $client->addArgument('http://'.$configuration['host'].':'.$configuration['port'].'/RPC2');

            $guzzleMessageFactoryDefinition = new Definition(\Http\Message\MessageFactory\GuzzleMessageFactory::class);

            $guzzle6AdapterDefinition = new Definition(\Http\Adapter\Guzzle6\Client::class);
            $guzzle6AdapterDefinition->addArgument($guzzleClient);

            $httpAdapterTransportDefinition = new Definition(\fXmlRpc\Transport\HttpAdapterTransport::class);
            $httpAdapterTransportDefinition->addArgument($guzzleMessageFactoryDefinition);
            $httpAdapterTransportDefinition->addArgument($guzzle6AdapterDefinition);

            $client->addArgument($httpAdapterTransportDefinition);

            // Pass the client to the connector
            // See the full list of connectors bellow
            $connectorDefinition = new Definition(\Supervisor\Connector\XmlRpc::class);
            $connectorDefinition->addArgument($client);

            $supervisor = new Definition(\Supervisor\Supervisor::class);
            $supervisor->addArgument($connectorDefinition);

            $container->setDefinition('supervisor.server.'.$serverName, $supervisor);
        }
    }
}
