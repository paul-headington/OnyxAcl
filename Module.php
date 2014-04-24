<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace OnyxAcl;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Authentication\Storage;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $this->initAcl($e);
        $e->getApplication()->getEventManager()->attach('route', array($this, 'checkAcl'));
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    
    public function getServiceConfig()
    {
        return array(
            'factories'=>array(
                'OnyxAcl\AuthStorage' => function($sm){
                    return new \OnyxAcl\AuthStorage('OnyxAcl_Auth'); 
                },         
                'AuthService' => function($sm) {
                    $config = $sm->get('Config');
                    $dbAdapter           = $sm->get('Zend\Db\Adapter\Adapter');
                    $dbTableAuthAdapter  = new DbTableAuthAdapter($dbAdapter);
                    $dbTableAuthAdapter
                        ->setTableName($config['user_settings']['auth_table'])
                        ->setIdentityColumn($config['user_settings']['identity_column'])
                        ->setCredentialColumn($config['user_settings']['credential_column'])
                        ->setCredentialTreatment("SHA1(CONCAT(salt, ?, '".$config['user_settings']['static_salt']. "')) AND isactive = 1");

                    $authService = new AuthenticationService();
                    $authService->setAdapter($dbTableAuthAdapter);
                    $authService->setStorage($sm->get('OnyxAcl\AuthStorage'));

                    return $authService;
                },
            ),
        );
    }
    
    public function initAcl(MvcEvent $e) { 
        $acl = new \Zend\Permissions\Acl\Acl();
        //swap this with $roles = $this->getDbRoles($e); for db accesss retrieve
        $roles = include __DIR__ . '/config/module.acl.roles.php';
        $allResources = array();
        foreach ($roles as $role => $resources) {

            $role = new \Zend\Permissions\Acl\Role\GenericRole($role);
            $acl->addRole($role);

            //this allows for inheritance
            $allResources = array_merge($resources, $allResources);

            //adding resources
            foreach ($resources as $resource) {
                 // Edit 4
                 if(!$acl->hasResource($resource))
                    $acl->addResource(new \Zend\Permissions\Acl\Resource\GenericResource($resource));
            }
            //adding restrictions
            foreach ($allResources as $resource) {
                $acl->allow($role, $resource);
            }
        }
        //testing
       // var_dump($acl->isAllowed('admin','home'));
        //true
        //exit();

        //setting to view
        $e->getViewModel()->acl = $acl;

    }

    public function checkAcl(MvcEvent $e) {
        $route = $e->getRouteMatch()->getMatchedRouteName();
        //you set your role
        $userRole = 'guest';
        //echo "route: " . $route . "<br/>";
        //var_dump($e->getViewModel()->acl->hasResource($route));
        //if (!$e -> getViewModel() -> acl -> isAllowed($userRole, $route)) {
        if($e->getViewModel()->acl->hasResource($route) && !$e->getViewModel()->acl->isAllowed($userRole, $route)) {
            $response = $e->getResponse();
            //location to page or what ever
            //echo "denied";
            //exit();
            $response->getHeaders()->addHeaderLine('Location', $e->getRequest()->getBaseUrl() . '/403');
            $response->setStatusCode(403);  
            \Zend\Debug\Debug::dump($response);
            exit();
            return;

        }
        
        //echo "allow";
        //exit();
    }    
    
    //this function can be used to pull roles and access from db instead of array file 
    // not currently used
    public function getDbRoles(MvcEvent $e){
        // I take it that your adapter is already configured
        $dbAdapter = $e->getApplication()->getServiceManager()->get('Zend\Db\Adapter\Adapter');
        $results = $dbAdapter->query('SELECT * FROM acl');
        // making the roles array
        $roles = array();
        foreach($results as $result){
            $roles[$result['user_role']][] = $result['resource'];
        }
        return $roles;
    }
}
