<?php return array(
    'root' => array(
        'name' => 'akirk/personal-crm',
        'pretty_version' => 'dev-claude/fix-dist-workflow-and-add-blueprint',
        'version' => 'dev-claude/fix-dist-workflow-and-add-blueprint',
        'reference' => 'e6e3a8d5dfb79d45fdde9f446dcaa438f1252bcb',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'akirk/personal-crm' => array(
            'pretty_version' => 'dev-claude/fix-dist-workflow-and-add-blueprint',
            'version' => 'dev-claude/fix-dist-workflow-and-add-blueprint',
            'reference' => 'e6e3a8d5dfb79d45fdde9f446dcaa438f1252bcb',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'akirk/wp-app' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => 'd10a04f24d987ee35b7b768aa26b860186dd04db',
            'type' => 'library',
            'install_path' => __DIR__ . '/../akirk/wp-app',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
    ),
);
