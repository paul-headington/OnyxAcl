<?php
return array(
    "onyx_acl_roles" => array(
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
    "onyx_acl" => array(
        "errorMessage" => 'Access denied to that resource',
        "loadFromDb" => false,
        "tableName" => 'acl',
        "denyUnlisted" => false,
        "loginRoute" => 'dashboard'
    )
);
?>