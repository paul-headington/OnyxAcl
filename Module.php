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
use Zend\Stdlib\RequestInterface as Request;
use Zend\View\Model\JsonModel;

class Module
{
    protected $ACL_ERROR = 'Access denied to that resource';
    protected $loadFromDb = false;
    protected $tableName = 'acl';
    protected $denyUnlisted = FALSE;

    protected $contentTypes = array(
        self::CONTENT_TYPE_JSON => array(
            'application/hal+json',
            'application/json'
        )
    );

    const CONTENT_TYPE_JSON = 'json';

    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $this->initAcl($e);
        $e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, array($this, 'checkAcl'), -1000000);
        $e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR , array($this, 'aclError'), -1000000);
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
//                        ->setCredentialTreatment("SHA1(CONCAT(salt, ?, '".$config['onyx_user']['static_salt']. "')) AND isactive = 1"); // force out all non active accounts
                        ->setCredentialTreatment("SHA1(CONCAT(salt, ?, '".$config['onyx_user']['static_salt']. "'))"); // handle patial logins if the user jacks out half way through

                    $authService = new AuthenticationService();
                    $authService->setAdapter($dbTableAuthAdapter);
                    $authService->setStorage($sm->get('OnyxAcl\AuthStorage'));
                    return $authService;
                },
                'AuthServiceNonActive' => function($sm) {
                    $config = $sm->get('Config');
                    $dbAdapter           = $sm->get('Zend\Db\Adapter\Adapter');
                    $dbTableAuthAdapter  = new DbTableAuthAdapter($dbAdapter);
                    $dbTableAuthAdapter
                        ->setTableName($config['onyx_user']['auth_table'])
                        ->setIdentityColumn($config['onyx_user']['identity_column'])
                        ->setCredentialColumn($config['onyx_user']['credential_column'])
                        ->setCredentialTreatment("SHA1(CONCAT(salt, ?, '".$config['onyx_user']['static_salt']. "'))");

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
            $orginal = $route->getMatchedRouteName();

            $request = $e->getRequest();

            $isJson = $this->requestHasContentType($request, self::CONTENT_TYPE_JSON);

            if($isJson){
                $jsonData = array("status" => "FAIL", "message" => "Access Denied");
                echo json_encode($jsonData);
                exit();
            }

            if($ident == null){
                $route->setMatchedRouteName('user');
                $route->setParam('controller', 'OnyxUser\Controller\User');
                $route->setParam('action', 'login');
                $route->setParam('__CONTROLLER__', 'OnyxUser');
                $route->setParam('backto', $orginal);
                //var_dump($route);die;
                //$controller->plugin('redirect')->toUrl('/user/login?backto=' . $route->getMatchedRouteName());
                //$e->stopPropagation();
                //return false;
            }
            //$controller->plugin('redirect')->toUrl('/error/denied');
            //$e->stopPropagation();
                //return false;
        }

    }

    public function requestHasContentType(Request $request, $contentType = '')
    {
        /** @var $headerContentType \Zend\Http\Header\ContentType */
        $headerContentType = $request->getHeaders()->get('content-type');
        if (!$headerContentType) {
            return false;
        }

        $requestedContentType = $headerContentType->getFieldValue();
        if (strstr($requestedContentType, ';')) {
            $headerData = explode(';', $requestedContentType);
            $requestedContentType = array_shift($headerData);
        }
        $requestedContentType = trim($requestedContentType);
        if (array_key_exists($contentType, $this->contentTypes)) {
            foreach ($this->contentTypes[$contentType] as $contentTypeValue) {
                if (stripos($contentTypeValue, $requestedContentType) === 0) {
                    return true;
                }
            }
        }

        return false;
    }


}
