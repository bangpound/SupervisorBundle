<?php

namespace YZ\SupervisorBundle\Manager;

use fXmlRpc\Client as fXmlRpcClient;
use fXmlRpc\Transport\HttpAdapterTransport;
use GuzzleHttp\Client as GuzzleHttpClient;
use Http\Adapter\Guzzle6\Client as Guzzle6Adapter;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Supervisor\Connector\XmlRpc;
use Supervisor\Supervisor;

/**
 * SupervisorManager
 */
class SupervisorManager
{
    /**
     * @var array
     */
    private $supervisors = array();

    /**
     * Constuctor
     *
     * @param array $supervisorsConfiguration Configuration in the symfony parameters
     */
    public function __construct(array $supervisorsConfiguration)
    {
        foreach ($supervisorsConfiguration as $serverName => $configuration) {
            //Create GuzzleHttp client
            $guzzleClient = new GuzzleHttpClient(['auth' => [$configuration['username'], $configuration['password']]]);

            // Pass the url and the guzzle client to the XmlRpc Client
            $client = new fXmlRpcClient(
              'http://'.$configuration['host'].':'.$configuration['port'].'/RPC2',
              new HttpAdapterTransport(new GuzzleMessageFactory(), new Guzzle6Adapter($guzzleClient))
            );

            // Pass the client to the connector
            // See the full list of connectors bellow
            $connector = new XmlRpc($client);

            $supervisor = new Supervisor($connector);
            $key = hash('md5', serialize(array(
              $configuration['host'],
              $configuration['port'],
              $configuration['username'],
              $configuration['password'],
            )));

            $this->supervisors[$key] = $supervisor;
        }
    }

    /**
     * Get all supervisors
     *
     * @return Supervisor[]
     */
    public function getSupervisors()
    {
        return $this->supervisors;
    }

    /**
     * Get Supervisor by key
     *
     * @param string $key
     *
     * @return Supervisor|null
     */
    public function getSupervisorByKey($key)
    {
        if (isset($this->supervisors[$key])) {
            return $this->supervisors[$key];
        }

        return null;
    }
}
