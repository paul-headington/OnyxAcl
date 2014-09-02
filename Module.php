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
        $e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, array($this, 'checkAcl'));
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
                'OnyxAclFactory' => 'OnyxAcl\Service\AclFactory',
            ),
        );
    }
    
    public function initAcl(MvcEvent $e) { 
        $e->getApplication()->getServiceManager()->get('OnyxAclFactory');   
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
        $OnyxAcl = $e->getApplication()->getServiceManager()->get('OnyxAcl');
        $ident = $OnyxAcl->getIdentity();
        $userRole = 'guest';
        if(isset($ident->role)){
            $userRole = $ident->role;
        }
        $denied = false;
        try{
            $acl = $e->getApplication()->getServiceManager()->get('OnyxAclFactory'); 
            if($acl->hasResource($route)) {
                if(!$acl->isAllowed($userRole, $route)){
                    $denied = true;
                }
            }else{
                $denied = $this->denyUnlisted;
            }
        }catch(Exception $e){
        }
        if($denied){  
            $controller = $e->getTarget(); // grab Controller instance from event 
            $app = $e->getTarget();
            $route = $e->getRouteMatch();
            if($ident == null){                
                $controller->plugin('redirect')->toUrl('/user/login?backto=' . $route->getMatchedRouteName());
                $e->stopPropagation();
                return false;
            }
            $controller->plugin('redirect')->toUrl('/error/denied');
            $e->stopPropagation();
                return false;
        }
        
    }    
    

}
