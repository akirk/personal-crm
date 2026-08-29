<?php return array(
    'root' => array(
        'name' => 'akirk/personal-crm',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '996e6ad055254bf1fe0cb3c29a79b5d7a35e447e',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'akirk/personal-crm' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '996e6ad055254bf1fe0cb3c29a79b5d7a35e447e',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'akirk/wp-app' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '110f51d2df9cdb4cd055ae0ef52ed48c5d3900b5',
            'type' => 'library',
            'install_path' => __DIR__ . '/../akirk/wp-app',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
    ),
);
