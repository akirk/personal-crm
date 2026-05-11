<?php return array(
    'root' => array(
        'name' => 'akirk/personal-crm',
        'pretty_version' => 'dev-claude/mobile-fixes',
        'version' => 'dev-claude/mobile-fixes',
        'reference' => '82d32b51d59d1a2a9b8d0c1bea01c129cd2b50ed',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'akirk/personal-crm' => array(
            'pretty_version' => 'dev-claude/mobile-fixes',
            'version' => 'dev-claude/mobile-fixes',
            'reference' => '82d32b51d59d1a2a9b8d0c1bea01c129cd2b50ed',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'akirk/wp-app' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'e04f5a9e34b45bee989fda87c15bf37ce60e9861',
            'type' => 'library',
            'install_path' => __DIR__ . '/../akirk/wp-app',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
    ),
);
