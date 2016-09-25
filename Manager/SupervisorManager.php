<?php

namespace YZ\SupervisorBundle\Manager;

use Supervisor\Supervisor;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * SupervisorManager.
 */
class SupervisorManager
{
    use ContainerAwareTrait;
    /**
     * @var array
     */
    private $supervisors = array();

    /**
     * Constuctor.
     *
     * @param array $supervisorsConfiguration Configuration in the symfony parameters
     */
    public function __construct(array $supervisorsConfiguration)
    {
        $this->supervisors = array_keys($supervisorsConfiguration);
    }

    /**
     * Get all supervisors.
     *
     * @return Supervisor[]
     */
    public function getSupervisors()
    {
        $values = array_map(function ($id) {
            return $this->container->get('supervisor.server.'.$id);
        }, $this->supervisors);

        return array_combine($this->supervisors, $values);
    }

    /**
     * Get Supervisor by key.
     *
     * @param string $key
     *
     * @return Supervisor|null
     */
    public function getSupervisorByKey($key)
    {
        if ($this->container->has('supervisor.server.'.$key)) {
            return $this->container->get('supervisor.server.'.$key);
        }

        return null;
    }
}
