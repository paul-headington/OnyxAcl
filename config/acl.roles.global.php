<?php
return array(
    "aclRoles" => array(
        'guest' => array(
            'home',
            'system',
            'system/createmodel', 
            'home/default',
            'about-us',
            'dashboard'
            ),
        'admin' => array(
            'admin',
            'add-user',
            'delete-user',
            'google/default'
            ),        
        ),
    "aclSettings" => array(
        "errorMessage" => 'Access denied to that resource',
        "loadFromDb" => false,
        "tableName" => 'acl',
        "denyUnlisted" => false,
        "loginRoute" => 'dashboard'
    )
);
?>