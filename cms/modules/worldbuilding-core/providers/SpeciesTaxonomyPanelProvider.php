<?php

/**
 * Type panel provider for rendering species taxonomy summaries.
 */

declare(strict_types=1);

/**
 * Provides species taxonomy panel provider services.
 */
final class SpeciesTaxonomyPanelProvider implements TypePanelProviderInterface
{
    /**
     * Returns ID.
     */
    public function getId(): string
    {
        return 'worldbuilding-core.species-taxonomy';
    }

    /**
     * Determines whether the requested value.
     */
    public function supports(array $document, array $context): bool
    {
        return (string) ($document['entryTypeId'] ?? '') === 'species';
    }

    /**
     * Builds panel.
     */
    public function buildPanel(array $document, array $context): ?array
    {
        $entryView = is_array($context['entryView'] ?? null) ? $context['entryView'] : array();
        $fieldMap = $this->mapFieldsById((array) ($entryView['fields'] ?? array()));
        $traits = $this->extractItemLabels((array) (($fieldMap['key_traits']['items'] ?? array())));
        $relatedSpecies = $this->extractReferenceLabels((array) (($fieldMap['related_species']['items'] ?? array())));

        return array(
            'id' => $this->getId(),
            'title' => 'Taxonomie und Speziesprofil',
            'eyebrow' => 'Biology Panel',
            'priority' => 20,
            'className' => 'entity-addon entity-addon--species',
            'template' => 'panels/species-taxonomy.tpl',
            'data' => array(
                'scientificName' => (string) ($fieldMap['scientific_name']['displayText'] ?? ''),
                'homeworld' => is_array($fieldMap['homeworld']['reference'] ?? null) ? $fieldMap['homeworld']['reference'] : null,
                'sentience' => (string) ($fieldMap['sentient']['displayText'] ?? ''),
                'conservationStatus' => (string) ($fieldMap['conservation_status']['displayText'] ?? ''),
                'traitCount' => count($traits),
                'traits' => $traits,
                'relatedSpecies' => $relatedSpecies,
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

    /**
     * Extracts reference labels.
     *
     * @return array<int, array<string, string>>
     */
    private function extractReferenceLabels(array $items): array
    {
        $references = array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $references[] = array(
                'label' => $label,
                'url' => trim((string) ($item['url'] ?? '')),
            );
        }

        return $references;
    }
}
