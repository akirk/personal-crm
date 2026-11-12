<?php return array(
    'root' => array(
        'name' => 'akirk/personal-crm',
        'pretty_version' => 'dev-fix/enqueue-assets-hook',
        'version' => 'dev-fix/enqueue-assets-hook',
        'reference' => '447ed979ca3c335419ebcbe42e2de09f569bcaea',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'akirk/personal-crm' => array(
            'pretty_version' => 'dev-fix/enqueue-assets-hook',
            'version' => 'dev-fix/enqueue-assets-hook',
            'reference' => '447ed979ca3c335419ebcbe42e2de09f569bcaea',
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
