<?php return array(
    'root' => array(
        'name' => 'akirk/personal-crm',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '5702a82524d7a6eaefef8630218c8439dd2bd018',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'akirk/personal-crm' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '5702a82524d7a6eaefef8630218c8439dd2bd018',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'akirk/wp-app' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'ac406460418b5d9da2123468254321a5b3bc527e',
            'type' => 'library',
            'install_path' => __DIR__ . '/../akirk/wp-app',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
    ),
);
