<?php

declare(strict_types=1);

require_once __DIR__ . '/../../TypePanelProviderInterface.php';
require_once __DIR__ . '/providers/SpeciesTaxonomyPanelProvider.php';
require_once __DIR__ . '/providers/PlanetOrbitalPanelProvider.php';

return array(
    'id' => 'worldbuilding-core',
    'label' => 'Worldbuilding Core',
    'description' => 'Basisbausteine fuer schema-getriebenes Worldbuilding mit Panels, Zusatztypen und Relationserweiterungen.',
    'schema' => array(
        'paths' => array(
            'schema',
        ),
    ),
    'templates' => array(
        'paths' => array(
            'type-templates',
        ),
    ),
    'assets' => array(
        'publicPaths' => array(
            'assets',
        ),
        'stylesheets' => array(
            'worldbuilding-core.css',
        ),
    ),
    'panelProviders' => array(
        new SpeciesTaxonomyPanelProvider(),
        new PlanetOrbitalPanelProvider(),
    ),
);
