<?php

/*
 * Copyright (c) 2011 , Paul Headington
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. All advertising materials mentioning features or use of this software
 *    must display the following acknowledgement:
 *    This product includes software developed by the <organization>.
 * 4. Neither the name of the <organization> nor the
 *    names of its contributors may be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY Paul Headington \'AS IS\' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
namespace OnyxAcl;
/**
 * 
 *
 * @author paulh
 */
class Acl{
    
    protected $storage;
    protected $authservice;
    protected $serviceManager;
    public $message; 



    public function __construct($sm = null) {
        if($sm == null){
            throw new \Exception("Onyx Acl needs to be initalised with the service mananger");
        }
        $this->serviceManager = $sm;
    }
    
    public function checkAuth(){
        return $this->getAuthService()->hasIdentity();
    }
    
    public function authenticate($data = array()){
        if(!isset($data['password'])){
            throw new \Exception("No password set");
        }
        $config = $this->serviceManager->get('Config');
        $identityColumn = $config['user_settings']['identity_column'];
        if(!isset($data[$identityColumn])){
            throw new \Exception("No ".$identityColumn." set");
        }
        //check authentication...
        $this->getAuthService()->getAdapter()
                               ->setIdentity($data[$identityColumn])
                               ->setCredential($data['password']);

        $result = $this->getAuthService()->authenticate();
        foreach($result->getMessages() as $message)
        {
            $this->message = $message;
        }
        
        $output = $result->isValid();

        if ($output) {            
            //check if it has rememberMe :
            if (isset($data['remeber'])) {
                if($data['remeber'] == 1){
                    $this->getSessionStorage()
                         ->setRememberMe(1);
                    //set storage again 
                    $this->getAuthService()->setStorage($this->getSessionStorage());
                }
            }
            $userData = $this->getAuthService()->getAdapter()
                                   ->getResultRowObject(
                                    null,
                                    'password'
                                    );
            $this->getAuthService()->getStorage()->write($userData);
        }
        return $output;
    }
    
    public function logout(){
        $this->getSessionStorage()->forgetMe();
        $this->getAuthService()->clearIdentity();
    }

    private function getAuthService()
    {
        if (! $this->authservice) {
            $this->authservice = $this->serviceManager
                                      ->get('AuthService');
        }
         
        return $this->authservice;
    }
     
    private function getSessionStorage()
    {
        if (! $this->storage) {
            $this->storage = $this->serviceManager
                                  ->get('SanAuth\Model\MyAuthStorage');
        }
         
        return $this->storage;
    }
    
}

?>
