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
use Zend\View\Model\ViewModel;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;

class Module
{
    protected $ACL_ERROR = 'Access denied to that resource';
    protected $loadFromDb = false;
    protected $tableName = 'acl';
    protected $denyUnlisted = FALSE;
    
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $this->initAcl($e);
        $e->getApplication()->getEventManager()->attach('route', array($this, 'checkAcl'));
        $e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'aclError'));
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
                        ->setTableName($config['onyx_user']['auth_table'])
                        ->setIdentityColumn($config['onyx_user']['identity_column'])
                        ->setCredentialColumn($config['onyx_user']['credential_column'])
                        ->setCredentialTreatment("SHA1(CONCAT(salt, ?, '".$config['onyx_user']['static_salt']. "')) AND isactive = 1");

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
        $config = $e->getApplication()->getServiceManager()->get('config');
        $roles = $config['onyx_acl_roles'];
        $this->ACL_ERROR = $config['onyx_acl']['errorMessage'];
        $this->loadFromDb = $config['onyx_acl']['loadFromDb'];
        $this->denyUnlisted = $config['onyx_acl']['denyUnlisted'];
        
        if($this->loadFromDb){
            $roles = $this->getDbRoles($e);// for db accesss retrieve
        }
        
        $allResources = array();
        foreach ($roles as $role => $resources) {

            $role = new \Zend\Permissions\Acl\Role\GenericRole($role);
            $acl->addRole($role);

            //this allows for inheritance
            $allResources = array_merge($resources, $allResources);

            //adding resources
            foreach ($resources as $resource) {
                
                 if(!$acl->hasResource($resource)){
                    $acl->addResource(new \Zend\Permissions\Acl\Resource\GenericResource($resource));
                 }
            }
            //adding restrictions
            foreach ($allResources as $resource) {
                $acl->allow($role, $resource);
            }
        }

        //setting to view
        $e->getViewModel()->acl = $acl;

    }
    
    public function aclError(MvcEvent $event){
        $error = $event->getError();
        if (empty($error) || $error != $this->ACL_ERROR) {
            return;
        }

        $result = $event->getResult();

        if ($result instanceof StdResponse) {
            return;
        }

        $baseModel = new ViewModel();
        $baseModel->setTemplate('layout/layout');

        $model = new ViewModel();
        $model->setTemplate('error/403');

        $baseModel->addChild($model);
        $baseModel->setTerminal(true);

        $event->setViewModel($baseModel);

        $response = $event->getResponse();
        $response->setStatusCode(403);

        $event->setResponse($response);
        $event->setResult($baseModel);

        return false;
    }

    public function checkAcl(MvcEvent $e) {
        $route = $e->getRouteMatch()->getMatchedRouteName();
        //you set your role this needs to load from user session
        $userRole = 'guest';
        $denied = FALSE;
        
        if($e->getViewModel()->acl->hasResource($route)) {
            if(!$e->getViewModel()->acl->isAllowed($userRole, $route)){
                $denied = TRUE;
            }
        }else{
            $denied = $this->denyUnlisted;
        }
        
        if($denied){      
                $app = $e->getTarget();
                $route = $e->getRouteMatch();

                $e->setError($this->ACL_ERROR) 
                  ->setParam('route', $route->getMatchedRouteName());
                $app->getEventManager()->trigger('dispatch.error', $e);
        }
        
        
        //echo "allow";
        //exit();
    }    
    
    //this function can be used to pull roles and access from db instead of array file 
    // not currently used
    public function getDbRoles(MvcEvent $e){
        // I take it that your adapter is already configured
        $dbAdapter = $e->getApplication()->getServiceManager()->get('Zend\Db\Adapter\Adapter');
        $statement = $dbAdapter->query('SELECT acl_role.name, acl_resource.route FROM acl_resource INNER JOIN acl_role ON acl_role.id = acl_resource.roleid ORDER BY acl_role.inheritance_order');
        $results = $statement->execute();
        // making the roles array
        $roles = array();
        foreach($results as $result){            
            $roles[$result['name']][] = $result['route'];
        }
        return $roles;
    }
}
