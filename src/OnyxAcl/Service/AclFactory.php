<?php

/*
 * The MIT License
 *
 * Copyright Error: on line 6, column 29 in Templates/Licenses/license-mit.txt
  The string doesn't match the expected date/time format. The string to parse was: "2/09/2014". The expected format was: "MMM d, yyyy". pheadington.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace OnyxAcl\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Permissions\Acl\Resource\GenericResource;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Acl;

/**
 * Description of AclFactory
 *
 * @author pheadington
 */
class AclFactory implements FactoryInterface
{
    
     /**
     * Create a new ACL Instance
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return acl object
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {       
        $acl = new Acl();
                
        $config = $serviceLocator->get('config');
        
        
        $roles = $config['onyx_acl_roles'];
        $this->ACL_ERROR = $config['onyx_acl']['error_message'];
        $this->loadFromDb = $config['onyx_acl']['load_from_db'];
        $this->denyUnlisted = $config['onyx_acl']['deny_unlisted'];
        
        if($this->loadFromDb){
            $roles = $this->getDbRoles($serviceLocator);// for db accesss retrieve
        }
        
        
        
        $allResources = array();
        foreach ($roles as $role => $resources) {

            $role = new GenericRole($role);
            $acl->addRole($role);

            //this allows for inheritance
            $allResources = array_merge($resources, $allResources);

            //adding resources
            foreach ($resources as $resource) {
                
                 if(!$acl->hasResource($resource)){
                    $acl->addResource(new GenericResource($resource));
                 }
            }
            //adding restrictions
            foreach ($allResources as $resource) {
                $acl->allow($role, $resource);
            }
        } 
        
        return $acl;
    }
    
        //this function can be used to pull roles and access from db instead of array file         
    private function getDbRoles(ServiceLocatorInterface $serviceLocator){
        // I take it that your adapter is already configured
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');
        $statement = $dbAdapter->query('SELECT onyx_acl_role.name, onyx_acl_resource.route FROM onyx_acl_resource INNER JOIN onyx_acl_role ON onyx_acl_role.id = onyx_acl_resource.roleid ORDER BY onyx_acl_role.inheritance_order');
        $results = $statement->execute();
        // making the roles array
        $roles = array();
        foreach($results as $result){            
            $roles[$result['name']][] = $result['route'];
        }
        return $roles;
    }
}