<?php

namespace YZ\SupervisorBundle\Controller;

use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SupervisorController.
 */
class SupervisorController extends Controller
{
    use LoggerAwareTrait;

    private static $publicInformations = ['description', 'group', 'name', 'state', 'statename'];

    /**
     * indexAction.
     */
    public function indexAction()
    {
        $supervisorManager = $this->get('supervisor.manager');

        return $this->render('YZSupervisorBundle:Supervisor:list.html.twig', array(
            'supervisors' => $supervisorManager->getSupervisors(),
        ));
    }

    /**
     * Reload the configuration.
     *
     * @param array $valid_gnames
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function reloadConfigAction($valid_gnames = [])
    {
        $supervisor = $this->container->get('supervisor.server.locahost');

        $result = $supervisor->reloadConfig();
        list($added, $changed, $removed) = $result[0];
        if (in_array('all', $valid_gnames)) {
            $valid_gnames = [];
        }
        if (!empty($valid_gnames)) {
            $groups = [];
            foreach ($supervisor->getAllProcessInfo() as $info) {
                $groups[] = $info['group'];
            }
            $groups = array_unique(array_merge($groups, $added));
            foreach ($valid_gnames as $gname) {
                if (!in_array($gname, $groups)) {
                    throw new \Exception('ERROR: no such group: %s'); /// $gname
                }
            }
        }
        foreach ($removed as $gname) {
            if (!empty($valid_gnames) && !in_array($gname, $valid_gnames)) {
                continue;
            }

            $results = $supervisor->stopProcessGroup($gname);
            $this->logger->info('stopped '.$gname);
            $supervisor->removeProcessGroup($gname);
            $this->logger->info('removed process group '.$gname);
        }

        foreach ($changed as $gname) {
            if (!empty($valid_gnames) && !in_array($gname, $valid_gnames)) {
                continue;
            }
            $results = $supervisor->stopProcessGroup($gname);
            $this->logger->info('stopped '.$gname);
            $supervisor->removeProcessGroup($gname);
            $supervisor->addProcessGroup($gname);
            $this->logger->info('updated process group '.$gname);
        }
        foreach ($added as $gname) {
            if (!empty($valid_gnames) && !in_array($gname, $valid_gnames)) {
                continue;
            }
            $supervisor->addProcessGroup($gname);
            $this->logger->info('added process group '.$gname);
        }

        return $this->redirect($this->generateUrl('supervisor'));
    }

    /**
     * startStopProcessAction.
     *
     * @param string  $start   1 to start, 0 to stop it
     * @param string  $key     The key to retrieve a Supervisor object
     * @param string  $name    The name of a process
     * @param string  $group   The group of a process
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response represents an HTTP response
     *
     * @throws \Exception
     */
    public function startStopProcessAction($start, $key, $name, $group, Request $request)
    {
        $supervisor = $this->get('supervisor.manager')->getSupervisorByKey($key);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $success = true;
        $process = $supervisor->getProcess($group.':'.$name);
        try {
            if ($start == '1') {
                $success = $supervisor->startProcess($process['name']);
            } elseif ($start == '0') {
                $success = $supervisor->stopProcess($process['name']);
            } else {
                $success = false;
            }
        } catch (\Exception $e) {
            $success = false;
            $this->get('session')->getFlashBag()->add(
                'error',
                $this->get('translator')->trans('process.stop.error', array(), 'YZSupervisorBundle')
            );
        }

        if (!$success) {
            $this->get('session')->getFlashBag()->add(
                'error',
                $this->get('translator')->trans(
                    ($start == '1' ? 'process.start.error' : 'process.stop.error'),
                    array(),
                    'YZSupervisorBundle'
                )
            );
        }

        if ($request->isXmlHttpRequest()) {
            $processInfo = $process;
            $res = json_encode([
                'success' => $success,
                'message' => implode(', ', $this->get('session')->getFlashBag()->get('error', array())),
                'processInfo' => $processInfo,
            ]);

            return new Response($res, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ]);
        }

        return $this->redirect($this->generateUrl('supervisor'));
    }

    /**
     * startStopAllProcessesAction.
     *
     * @param Request $request
     * @param string  $start   1 to start, 0 to stop it
     * @param string  $key     The key to retrieve a Supervisor object
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     *
     * @throws \Exception
     */
    public function startStopAllProcessesAction(Request $request, $start, $key)
    {
        $supervisor = $this->get('supervisor.manager')->getSupervisorByKey($key);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $processesInfo = true;
        if ($start == '1') {
            $processesInfo = $supervisor->startAllProcesses(false);
        } elseif ($start == '0') {
            $processesInfo = $supervisor->stopAllProcesses(false);
        }

        if ($request->isXmlHttpRequest()) {
            $res = json_encode([
                'processesInfo' => $processesInfo,
            ]);

            return new Response($res, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ]);
        }

        return $this->redirect($this->generateUrl('supervisor'));
    }

    /**
     * showSupervisorLogAction.
     *
     * @param string $key The key to retrieve a Supervisor object
     *
     * @return Response
     */
    public function showSupervisorLogAction($key)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $logs = $supervisor->readLog(0, 0);

        return $this->render('YZSupervisorBundle:Supervisor:showLog.html.twig', array(
            'log' => $logs,
        ));
    }

    /**
     * clearSupervisorLogAction.
     *
     * @param string $key The key to retrieve a Supervisor object
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function clearSupervisorLogAction($key)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        if ($supervisor->clearLog() !== true) {
            $this->get('session')->getFlashBag()->add(
                'error',
                $this->get('translator')->trans('logs.delete.error', array(), 'YZSupervisorBundle')
            );
        }

        return $this->redirect($this->generateUrl('supervisor'));
    }

    /**
     * showProcessLogAction.
     *
     * @param string $key   The key to retrieve a Supervisor object
     * @param string $name  The name of a process
     * @param string $group The group of a process
     *
     * @return Response
     */
    public function showProcessLogAction($key, $name, $group)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);
        $process = $supervisor->getProcess($name.':'.$group);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $result = $supervisor->tailProcessStdoutLog($name.':'.$group, 0, 1);
        $stdout = $supervisor->tailProcessStdoutLog($name.':'.$group, 0, $result[1]);

        return $this->render('YZSupervisorBundle:Supervisor:showLog.html.twig', array(
            'log' => $stdout[0],
        ));
    }

    /**
     * showProcessLogErrAction.
     *
     * @param string $key   The key to retrieve a Supervisor object
     * @param string $name  The name of a process
     * @param string $group The group of a process
     *
     * @return Response
     */
    public function showProcessLogErrAction($key, $name, $group)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);
        $process = $supervisor->getProcess($name.':'.$group);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $result = $supervisor->tailProcessStderrLog($name.':'.$group, 0, 1);
        $stderr = $supervisor->tailProcessStderrLog($name.':'.$group, 0, $result[1]);

        return $this->render('YZSupervisorBundle:Supervisor:showLog.html.twig', array(
            'log' => $stderr[0],
        ));
    }

    /**
     * clearProcessLogAction.
     *
     * @param string $key   The key to retrieve a Supervisor object
     * @param string $name  The name of a process
     * @param string $group The group of a process
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function clearProcessLogAction($key, $name, $group)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);
        $process = $supervisor->getProcess($name.':'.$group);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        if ($supervisor->clearProcessLogs($name.':'.$group) !== true) {
            $this->get('session')->getFlashBag()->add(
                'error',
                $this->get('translator')->trans('logs.delete.error', array(), 'YZSupervisorBundle')
            );
        }

        return $this->redirect($this->generateUrl('supervisor'));
    }

    /**
     * showProcessInfoAction.
     *
     * @param string  $key     The key to retrieve a Supervisor object
     * @param string  $name    The name of a process
     * @param string  $group   The group of a process
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response represents an HTTP response
     *
     * @throws \Exception
     */
    public function showProcessInfoAction($key, $name, $group, Request $request)
    {
        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);
        $process = $supervisor->getProcess($name.':'.$group);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $infos = $process->getPayload();

        if ($request->isXmlHttpRequest()) {
            $processInfo = [];
            foreach (self::$publicInformations as $public) {
                $processInfo[$public] = $infos[$public];
            }

            $res = json_encode([
                'supervisor' => $key,
                'processInfo' => $processInfo,
                'controlLink' => $this->generateUrl('supervisor.process.startStop', [
                    'key' => $key,
                    'name' => $name,
                    'group' => $group,
                    'start' => ($infos['state'] == 10 || $infos['state'] == 20 ? '0' : '1'),
                ]),
            ]);

            return new Response($res, 200, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-store',
            ]);
        }

        return $this->render('YZSupervisorBundle:Supervisor:showInformations.html.twig', array(
            'informations' => $infos,
        ));
    }

    /**
     * showProcessAllInfoAction.
     *
     * @param string  $key     The key to retrieve a Supervisor object
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response represents an HTTP response
     *
     * @throws \Exception
     */
    public function showProcessInfoAllAction($key, Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new \Exception('Ajax request expected here');
        }

        $supervisorManager = $this->get('supervisor.manager');
        $supervisor = $supervisorManager->getSupervisorByKey($key);

        if (!$supervisor) {
            throw new \Exception('Supervisor not found');
        }

        $processes = $supervisor->getAllProcesses();
        $processesInfo = [];
        foreach ($processes as $process) {
            $infos = $process;
            $processInfo = [];
            foreach (self::$publicInformations as $public) {
                $processInfo[$public] = $infos[$public];
            }

            $processesInfo[$infos['name']] = [
                'supervisor' => $key,
                'processInfo' => $processInfo,
                'controlLink' => $this->generateUrl('supervisor.process.startStop', [
                    'key' => $key,
                    'name' => $infos['name'],
                    'group' => $infos['group'],
                    'start' => ($infos['state'] == 10 || $infos['state'] == 20 ? '0' : '1'),
                ]),
            ];
        }

        $res = json_encode($processesInfo);

        return new Response($res, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ]);
    }
}
