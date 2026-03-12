<?php

/**
 * View helpers for turning typed entry metadata into template-ready field and relation payloads.
 */

declare(strict_types=1);

/**
 * Builds entry view.
 *
 * @return array<string, mixed>
 */
function build_entry_view(ContentRepository $repository, SchemaRegistry $schemaRegistry, ?array $document): array
{
    $relations = $document !== null ? $repository->getDocumentRelations($document) : build_empty_entry_relations_view();

    if ($document === null || !is_array($document['entryType'] ?? null)) {
        return array(
            'hasType' => false,
            'type' => null,
            'groups' => array(),
            'fields' => array(),
            'relations' => $relations,
        );
    }

    /** @var array<string, mixed> $type */
    $type = $document['entryType'];
    $typedFields = is_array($document['typedFields'] ?? null) ? $document['typedFields'] : array();
    $fieldDefinitions = is_array($type['fields'] ?? null) ? $type['fields'] : array();
    $fieldGroups = array();
    $fieldViews = array();

    foreach ($fieldDefinitions as $fieldDefinition) {
        if (!is_array($fieldDefinition)) {
            continue;
        }

        $fieldId = (string) ($fieldDefinition['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }

        $value = array_key_exists($fieldId, $typedFields) ? $typedFields[$fieldId] : ($fieldDefinition['default'] ?? null);
        $fieldView = build_entry_field_view($repository, $document, $fieldDefinition, $value);
        if (!empty($fieldView['isEmpty'])) {
            continue;
        }

        $fieldViews[] = $fieldView;
        $groupId = (string) ($fieldView['group'] ?? 'details');
        if (!isset($fieldGroups[$groupId])) {
            $fieldGroups[$groupId] = array(
                'id' => $groupId,
                'label' => humanize_entry_group_label($groupId),
                'fields' => array(),
            );
        }

        $fieldGroups[$groupId]['fields'][] = $fieldView;
    }

    return array(
        'hasType' => true,
        'type' => array(
            'id' => (string) ($type['id'] ?? ''),
            'label' => (string) ($type['label'] ?? ''),
            'icon' => (string) ($type['icon'] ?? ''),
            'color' => (string) ($type['color'] ?? ''),
            'description' => (string) ($type['description'] ?? ''),
            'template' => (string) ($type['template'] ?? ''),
            'groups' => is_array($type['groups'] ?? null) ? array_values($type['groups']) : array(),
        ),
        'groups' => array_values($fieldGroups),
        'fields' => $fieldViews,
        'relations' => $relations,
    );
}

/**
 * Builds empty entry relations view.
 *
 * @return array<string, mixed>
 */
function build_empty_entry_relations_view(): array
{
    return array(
        'hasRelations' => false,
        'outgoing' => array(),
        'incoming' => array(),
        'groupedOutgoing' => array(),
        'groupedIncoming' => array(),
        'counts' => array(
            'outgoing' => 0,
            'incoming' => 0,
            'total' => 0,
        ),
    );
}

/**
 * Builds entry field view.
 *
 * @param array<string, mixed> $document
 * @param array<string, mixed> $fieldDefinition
 * @param mixed $value
 * @return array<string, mixed>
 */
function build_entry_field_view(ContentRepository $repository, array $document, array $fieldDefinition, $value): array
{
    $fieldType = (string) ($fieldDefinition['type'] ?? 'text');
    $group = (string) ($fieldDefinition['group'] ?? 'details');
    $label = (string) ($fieldDefinition['label'] ?? ($fieldDefinition['id'] ?? 'Feld'));
    $description = (string) ($fieldDefinition['description'] ?? '');

    $view = array(
        'id' => (string) ($fieldDefinition['id'] ?? ''),
        'label' => $label,
        'type' => $fieldType,
        'description' => $description,
        'group' => $group !== '' ? $group : 'details',
        'value' => $value,
        'displayText' => '',
        'items' => array(),
        'reference' => null,
        'isList' => false,
        'isEmpty' => true,
    );

    if ($fieldType === 'boolean') {
        if ($value === null || $value === '') {
            return $view;
        }

        $view['displayText'] = !empty($value) ? 'Ja' : 'Nein';
        $view['isEmpty'] = false;
        return $view;
    }

    if ($fieldType === 'number' || $fieldType === 'date' || $fieldType === 'text' || $fieldType === 'textarea' || $fieldType === 'select') {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return $view;
        }

        if ($fieldType === 'select') {
            $text = map_entry_option_label($fieldDefinition, $text);
        }

        $view['displayText'] = $text;
        $view['isEmpty'] = false;
        return $view;
    }

    if ($fieldType === 'reference') {
        $reference = is_scalar($value) ? trim((string) $value) : '';
        if ($reference === '') {
            return $view;
        }

        $resolvedReference = resolve_entry_reference_item($repository, $document, $reference);
        $view['reference'] = $resolvedReference;
        $view['displayText'] = (string) ($resolvedReference['label'] ?? $reference);
        $view['isEmpty'] = false;
        return $view;
    }

    if (in_array($fieldType, array('multiselect', 'reference-list', 'tags'), true)) {
        $items = is_array($value) ? $value : (is_scalar($value) ? array(trim((string) $value)) : array());
        $resolvedItems = array();

        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);
            if ($string === '') {
                continue;
            }

            $resolvedItems[] = $fieldType === 'reference-list'
                ? resolve_entry_reference_item($repository, $document, $string)
                : array(
                    'value' => $string,
                    'label' => $fieldType === 'multiselect' ? map_entry_option_label($fieldDefinition, $string) : $string,
                    'url' => '',
                );
        }

        if ($resolvedItems === array()) {
            return $view;
        }

        $view['items'] = $resolvedItems;
        $view['displayText'] = implode(', ', array_map(static function (array $item): string {
            return (string) ($item['label'] ?? $item['value'] ?? '');
        }, $resolvedItems));
        $view['isList'] = true;
        $view['isEmpty'] = false;
        return $view;
    }

    if (is_scalar($value) && trim((string) $value) !== '') {
        $view['displayText'] = trim((string) $value);
        $view['isEmpty'] = false;
    }

    return $view;
}

/**
 * Maps entry option label.
 *
 * @param array<string, mixed> $fieldDefinition
 */
function map_entry_option_label(array $fieldDefinition, string $value): string
{
    foreach ((array) ($fieldDefinition['options'] ?? array()) as $option) {
        if (!is_array($option)) {
            continue;
        }

        if ((string) ($option['value'] ?? '') === $value) {
            return trim((string) ($option['label'] ?? $value));
        }
    }

    return $value;
}

/**
 * Resolves entry reference item.
 *
 * @param array<string, mixed> $document
 * @return array<string, string>
 */
function resolve_entry_reference_item(ContentRepository $repository, array $document, string $reference): array
{
    $resolved = $repository->resolveGraphDocumentReference($reference, (string) ($document['relativePath'] ?? ''));
    if ($resolved === null) {
        return array(
            'value' => $reference,
            'label' => $reference,
            'url' => '',
        );
    }

    return array(
        'value' => (string) ($resolved['slug'] ?? $reference),
        'label' => (string) ($resolved['title'] ?? $reference),
        'url' => $repository->pageUrlForDocument($resolved),
    );
}

/**
 * Humanizes entry group label.
 */
function humanize_entry_group_label(string $value): string
{
    $value = str_replace(array('_', '-'), ' ', trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value !== '' ? ucwords($value) : 'Details';
}
