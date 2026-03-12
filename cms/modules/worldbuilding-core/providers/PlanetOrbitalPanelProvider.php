<?php

/**
 * Type panel provider for rendering planetary orbital data.
 */

declare(strict_types=1);

/**
 * Provides planet orbital panel provider services.
 */
final class PlanetOrbitalPanelProvider implements TypePanelProviderInterface
{
    /**
     * Returns ID.
     */
    public function getId(): string
    {
        return 'worldbuilding-core.planet-orbital';
    }

    /**
     * Determines whether the requested value.
     */
    public function supports(array $document, array $context): bool
    {
        return (string) ($document['entryTypeId'] ?? '') === 'planet';
    }

    /**
     * Builds panel.
     */
    public function buildPanel(array $document, array $context): ?array
    {
        $entryView = is_array($context['entryView'] ?? null) ? $context['entryView'] : array();
        $fieldMap = $this->mapFieldsById((array) ($entryView['fields'] ?? array()));
        $biomes = $this->extractItemLabels((array) (($fieldMap['biomes']['items'] ?? array())));

        return array(
            'id' => $this->getId(),
            'title' => 'Orbitalprofil',
            'eyebrow' => 'Astronomy Panel',
            'priority' => 20,
            'className' => 'entity-addon entity-addon--planet',
            'template' => 'panels/planet-orbital.tpl',
            'data' => array(
                'starSystem' => (string) ($fieldMap['star_system']['displayText'] ?? ''),
                'planetClass' => (string) ($fieldMap['planet_class']['displayText'] ?? ''),
                'gravity' => (string) ($fieldMap['gravity']['displayText'] ?? ''),
                'atmosphere' => (string) ($fieldMap['atmosphere']['displayText'] ?? ''),
                'governingBody' => is_array($fieldMap['governing_body']['reference'] ?? null) ? $fieldMap['governing_body']['reference'] : null,
                'biomes' => $biomes,
            ),
        );
    }

    /**
     * Maps fields by ID.
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapFieldsById(array $fields): array
    {
        $fieldMap = array();

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldId = (string) ($field['id'] ?? '');
            if ($fieldId !== '') {
                $fieldMap[$fieldId] = $field;
            }
        }

        return $fieldMap;
    }

    /**
     * Extracts item labels.
     *
     * @return string[]
     */
    private function extractItemLabels(array $items): array
    {
        $labels = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? $item['value'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
