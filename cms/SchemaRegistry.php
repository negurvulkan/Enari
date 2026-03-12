<?php

/**
 * Schema registry for loading entry types, relations, and normalized field definitions.
 */

declare(strict_types=1);

/**
 * Loads and normalizes schema definitions for entry types, fields, and relations.
 */
final class SchemaRegistry
{
    /** @var string[] */
    private const SUPPORTED_FIELD_TYPES = array(
        'text',
        'textarea',
        'number',
        'boolean',
        'date',
        'select',
        'multiselect',
        'reference',
        'reference-list',
        'tags',
    );

    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores schema sources.
     *
     * @var array<int, array<string, mixed>>
     */
    private $schemaSources = array();

    /**
     * Stores schema directory.
     *
     * @var string
     */
    private $schemaDirectory;

    /**
     * Stores schema directories.
     *
     * @var string[]
     */
    private $schemaDirectories = array();

    /**
     * Stores the types file path.
     *
     * @var string
     */
    private $typesFilePath;

    /**
     * Stores the types file paths.
     *
     * @var string[]
     */
    private $typesFilePaths = array();

    /**
     * Stores the relations file path.
     *
     * @var string
     */
    private $relationsFilePath;

    /**
     * Stores the relations file paths.
     *
     * @var string[]
     */
    private $relationsFilePaths = array();

    /**
     * Stores templates directory.
     *
     * @var string
     */
    private $templatesDirectory;

    /**
     * Stores YAML parser.
     *
     * @var SimpleYamlParser
     */
    private $yamlParser;

    /**
     * Stores types indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private $typesById = array();

    /**
     * Stores relations indexed by ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private $relationsById = array();

    /**
     * Stores cache signature.
     *
     * @var string
     */
    private $cacheSignature = '';

    /**
     * Initializes schema sources and normalizes type and relation definitions.
     */
    public function __construct(string $basePath, array $config = array(), ?SimpleYamlParser $yamlParser = null)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->yamlParser = $yamlParser ?? new SimpleYamlParser();

        $typesFile = trim((string) ($config['typesFile'] ?? 'types.yaml'));
        $relationsFile = trim((string) ($config['relationsFile'] ?? 'relations.yaml'));
        $templatesPath = $this->resolveRelativePath((string) ($config['templatesPath'] ?? 'cms/type-templates'));
        $schemaSources = $this->normalizeSchemaSources($config, $typesFile, $relationsFile);
        $resolvedSchemaDirectories = array();
        $typesFilePaths = array();
        $relationsFilePaths = array();

        foreach ($schemaSources as $source) {
            foreach ((array) ($source['paths'] ?? array()) as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $resolvedSchemaDirectories[$path] = $path;
            }

            foreach ((array) ($source['typesFiles'] ?? array()) as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $typesFilePaths[$path] = $path;
            }

            foreach ((array) ($source['relationsFiles'] ?? array()) as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $relationsFilePaths[$path] = $path;
            }
        }

        $schemaDirectories = array_values($resolvedSchemaDirectories);
        $schemaDirectory = $schemaDirectories[0] ?? '';

        $this->schemaSources = $schemaSources;
        $this->schemaDirectory = $schemaDirectory;
        $this->schemaDirectories = $schemaDirectories;
        $this->typesFilePaths = array_values($typesFilePaths);
        $this->relationsFilePaths = array_values($relationsFilePaths);
        $this->typesFilePath = $this->typesFilePaths[0] ?? '';
        $this->relationsFilePath = $this->relationsFilePaths[0] ?? '';
        $this->templatesDirectory = $templatesPath;

        $this->typesById = $this->loadTypes();
        $this->relationsById = $this->loadRelations();
        $this->cacheSignature = $this->buildCacheSignature();
    }

    /**
     * Returns types.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTypes(): array
    {
        return array_values($this->typesById);
    }

    /**
     * Returns type.
     *
     * @return array<string, mixed>|null
     */
    public function getType(?string $id): ?array
    {
        $normalizedId = $this->normalizeId((string) $id);
        if ($normalizedId === '') {
            return null;
        }

        return $this->typesById[$normalizedId] ?? null;
    }

    /**
     * Returns relations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return array_values($this->relationsById);
    }

    /**
     * Returns relation.
     *
     * @return array<string, mixed>|null
     */
    public function getRelation(?string $id): ?array
    {
        $normalizedId = $this->normalizeId((string) $id);
        if ($normalizedId === '') {
            return null;
        }

        return $this->relationsById[$normalizedId] ?? null;
    }

    /**
     * Returns cache signature.
     */
    public function getCacheSignature(): string
    {
        return $this->cacheSignature;
    }

    /**
     * Returns schema sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSchemaSources(): array
    {
        return $this->schemaSources;
    }

    /**
     * Processes relation allows.
     */
    public function relationAllows(?string $relationId, ?string $fromTypeId, ?string $toTypeId): bool
    {
        $relation = $this->getRelation($relationId);
        if ($relation === null) {
            return true;
        }

        $fromTypeId = $this->normalizeId((string) $fromTypeId);
        $toTypeId = $this->normalizeId((string) $toTypeId);
        $allowedFromTypes = is_array($relation['from_types'] ?? null) ? $relation['from_types'] : array();
        $allowedToTypes = is_array($relation['to_types'] ?? null) ? $relation['to_types'] : array();

        if ($allowedFromTypes !== array() && ($fromTypeId === '' || !in_array($fromTypeId, $allowedFromTypes, true))) {
            return false;
        }

        if ($allowedToTypes !== array() && ($toTypeId === '' || !in_array($toTypeId, $allowedToTypes, true))) {
            return false;
        }

        return true;
    }

    /**
     * Resolves the matching entry type and typed field values from document frontmatter.
     *
     * @return array<string, mixed>
     */
    public function resolveEntryType(array $frontmatter): array
    {
        $rawType = $frontmatter['type'] ?? null;
        if (!is_scalar($rawType)) {
            return array(
                'typeId' => '',
                'type' => null,
                'typedFields' => array(),
            );
        }

        $typeId = $this->normalizeId((string) $rawType);
        if ($typeId === '') {
            return array(
                'typeId' => '',
                'type' => null,
                'typedFields' => array(),
            );
        }

        $type = $this->getType($typeId);
        if ($type === null) {
            return array(
                'typeId' => '',
                'type' => null,
                'typedFields' => array(),
            );
        }

        return array(
            'typeId' => $typeId,
            'type' => $type,
            'typedFields' => $this->resolveTypedFieldValues($type, $frontmatter),
        );
    }

    /**
     * Builds template candidates.
     *
     * @param array<string, mixed> $type
     * @return string[]
     */
    public function buildTemplateCandidates(array $type, string $layoutName = ''): array
    {
        $templateId = trim((string) ($type['template'] ?? ''));
        $typeId = trim((string) ($type['id'] ?? ''));
        $candidates = array();
        $bases = array();

        if ($templateId !== '') {
            $bases[] = $templateId;
        }
        if ($typeId !== '' && !in_array($typeId, $bases, true)) {
            $bases[] = $typeId;
        }
        if (!in_array('entity-default', $bases, true)) {
            $bases[] = 'entity-default';
        }

        foreach ($bases as $base) {
            if ($layoutName !== '') {
                $candidates[] = 'types/' . $layoutName . '/' . $base . '.tpl';
                $candidates[] = 'types/' . $layoutName . '/' . $base . '.php';
            }

            $candidates[] = 'types/' . $base . '.tpl';
            $candidates[] = 'types/' . $base . '.php';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Returns templates directory.
     */
    public function getTemplatesDirectory(): string
    {
        return $this->templatesDirectory;
    }

    /**
     * Loads types.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadTypes(): array
    {
        $types = array();

        foreach ($this->typesFilePaths as $typesFilePath) {
            $rawPayload = $this->loadYamlFile($typesFilePath);
            $rawTypes = $this->extractDefinitionList($rawPayload, 'types');

            foreach ($rawTypes as $id => $rawType) {
                $normalized = $this->normalizeTypeDefinition($id, $rawType);
                if ($normalized === null) {
                    continue;
                }

                $types[(string) $normalized['id']] = $normalized;
            }
        }

        return $types;
    }

    /**
     * Loads relations.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadRelations(): array
    {
        $relations = array();

        foreach ($this->relationsFilePaths as $relationsFilePath) {
            $rawPayload = $this->loadYamlFile($relationsFilePath);
            $rawRelations = $this->extractDefinitionList($rawPayload, 'relations');

            foreach ($rawRelations as $id => $rawRelation) {
                $normalized = $this->normalizeRelationDefinition($id, $rawRelation);
                if ($normalized === null) {
                    continue;
                }

                $relations[(string) $normalized['id']] = $normalized;
            }
        }

        return $relations;
    }

    /**
     * Extracts definition list.
     *
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function extractDefinitionList($payload, string $key): array
    {
        if (!is_array($payload)) {
            return array();
        }

        $candidate = $payload[$key] ?? $payload;
        if (!is_array($candidate)) {
            return array();
        }

        if ($this->isAssociativeArray($candidate)) {
            /** @var array<string, mixed> $candidate */
            return $candidate;
        }

        $indexed = array();
        foreach ($candidate as $index => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $id = is_scalar($definition['id'] ?? null) ? (string) $definition['id'] : (string) $index;
            $indexed[$id] = $definition;
        }

        return $indexed;
    }

    /**
     * Normalizes type definition.
     *
     * @param mixed $rawType
     * @return array<string, mixed>|null
     */
    private function normalizeTypeDefinition(string $fallbackId, $rawType): ?array
    {
        if (!is_array($rawType)) {
            return null;
        }

        $id = $this->normalizeId((string) ($rawType['id'] ?? $fallbackId));
        if ($id === '') {
            return null;
        }

        $label = trim((string) ($rawType['label'] ?? $this->humanize($id)));
        $groups = $this->normalizeStringList($rawType['groups'] ?? $rawType['categories'] ?? array());
        $fields = $this->normalizeFields($rawType['fields'] ?? array(), $rawType['defaults'] ?? array());

        return array(
            'id' => $id,
            'label' => $label !== '' ? $label : $id,
            'icon' => trim((string) ($rawType['icon'] ?? '')),
            'color' => trim((string) ($rawType['color'] ?? '')),
            'description' => trim((string) ($rawType['description'] ?? '')),
            'template' => trim((string) ($rawType['template'] ?? '')),
            'groups' => $groups,
            'defaults' => is_array($rawType['defaults'] ?? null) ? $rawType['defaults'] : array(),
            'fields' => array_values($fields),
            'fieldMap' => $fields,
        );
    }

    /**
     * Normalizes relation definition.
     *
     * @param mixed $rawRelation
     * @return array<string, mixed>|null
     */
    private function normalizeRelationDefinition(string $fallbackId, $rawRelation): ?array
    {
        if (!is_array($rawRelation)) {
            return null;
        }

        $id = $this->normalizeId((string) ($rawRelation['id'] ?? $fallbackId));
        if ($id === '') {
            return null;
        }

        return array(
            'id' => $id,
            'label' => trim((string) ($rawRelation['label'] ?? $this->humanize($id))),
            'inverse_label' => trim((string) ($rawRelation['inverse_label'] ?? $rawRelation['inverseLabel'] ?? '')),
            'from_types' => $this->normalizeStringList($rawRelation['from_types'] ?? $rawRelation['fromTypes'] ?? array()),
            'to_types' => $this->normalizeStringList($rawRelation['to_types'] ?? $rawRelation['toTypes'] ?? array()),
            'cardinality' => trim((string) ($rawRelation['cardinality'] ?? '')),
            'color' => trim((string) ($rawRelation['color'] ?? '')),
            'style' => trim((string) ($rawRelation['style'] ?? '')),
        );
    }

    /**
     * Normalizes fields.
     *
     * @param mixed $rawFields
     * @param mixed $defaults
     * @return array<string, array<string, mixed>>
     */
    private function normalizeFields($rawFields, $defaults): array
    {
        $defaultValues = is_array($defaults) ? $defaults : array();
        $normalizedFields = array();

        if (is_array($rawFields) && $this->isAssociativeArray($rawFields)) {
            foreach ($rawFields as $fieldId => $rawField) {
                if (!is_array($rawField)) {
                    $rawField = array('type' => (string) $rawField);
                }

                $field = $this->normalizeFieldDefinition((string) $fieldId, $rawField, $defaultValues);
                if ($field !== null) {
                    $normalizedFields[(string) $field['id']] = $field;
                }
            }

            return $normalizedFields;
        }

        foreach ((array) $rawFields as $index => $rawField) {
            if (is_scalar($rawField)) {
                $rawField = array('id' => (string) $rawField, 'type' => 'text');
            }

            if (!is_array($rawField)) {
                continue;
            }

            $field = $this->normalizeFieldDefinition((string) ($rawField['id'] ?? $index), $rawField, $defaultValues);
            if ($field !== null) {
                $normalizedFields[(string) $field['id']] = $field;
            }
        }

        return $normalizedFields;
    }

    /**
     * Normalizes field definition.
     *
     * @param array<string, mixed> $rawField
     * @param array<string, mixed> $defaultValues
     * @return array<string, mixed>|null
     */
    private function normalizeFieldDefinition(string $fallbackId, array $rawField, array $defaultValues): ?array
    {
        $id = $this->normalizeId((string) ($rawField['id'] ?? $fallbackId));
        if ($id === '') {
            return null;
        }

        $fieldType = $this->normalizeFieldType((string) ($rawField['type'] ?? 'text'));
        $options = $this->normalizeFieldOptions($rawField['options'] ?? array());
        $referenceTypes = $this->normalizeStringList($rawField['accept_types'] ?? $rawField['acceptTypes'] ?? array());
        $defaultValue = array_key_exists($id, $defaultValues)
            ? $defaultValues[$id]
            : ($rawField['default'] ?? null);

        return array(
            'id' => $id,
            'label' => trim((string) ($rawField['label'] ?? $this->humanize($id))),
            'type' => $fieldType,
            'description' => trim((string) ($rawField['description'] ?? '')),
            'group' => $this->normalizeId((string) ($rawField['group'] ?? '')) ?: 'details',
            'placeholder' => trim((string) ($rawField['placeholder'] ?? '')),
            'required' => !empty($rawField['required']),
            'default' => $defaultValue,
            'options' => $options,
            'referenceTypes' => $referenceTypes,
        );
    }

    /**
     * Resolves typed field values.
     *
     * @param array<string, mixed> $type
     * @return array<string, mixed>
     */
    private function resolveTypedFieldValues(array $type, array $frontmatter): array
    {
        $typedFields = array();
        $fieldMap = is_array($type['fieldMap'] ?? null) ? $type['fieldMap'] : array();

        foreach ($fieldMap as $fieldId => $fieldDefinition) {
            $defaultValue = $fieldDefinition['default'] ?? null;
            $value = array_key_exists($fieldId, $frontmatter) ? $frontmatter[$fieldId] : $defaultValue;
            $typedFields[$fieldId] = $this->normalizeFieldValue($fieldDefinition, $value);
        }

        return $typedFields;
    }

    /**
     * Normalizes field value.
     *
     * @param array<string, mixed> $fieldDefinition
     * @param mixed $value
     * @return mixed
     */
    private function normalizeFieldValue(array $fieldDefinition, $value)
    {
        $fieldType = (string) ($fieldDefinition['type'] ?? 'text');

        if ($fieldType === 'number') {
            if (is_numeric($value)) {
                return strpos((string) $value, '.') !== false ? (float) $value : (int) $value;
            }

            return $value;
        }

        if ($fieldType === 'boolean') {
            if (is_bool($value)) {
                return $value;
            }

            if (is_scalar($value)) {
                $normalized = strtolower(trim((string) $value));
                if (in_array($normalized, array('1', 'true', 'yes', 'on', 'ja'), true)) {
                    return true;
                }

                if (in_array($normalized, array('0', 'false', 'no', 'off', 'nein'), true)) {
                    return false;
                }
            }

            return (bool) $value;
        }

        if (in_array($fieldType, array('multiselect', 'reference-list', 'tags'), true)) {
            return $this->normalizeStringList($value);
        }

        if ($fieldType === 'select') {
            $options = array_column((array) ($fieldDefinition['options'] ?? array()), 'value');
            $normalizedValue = is_scalar($value) ? trim((string) $value) : '';
            if ($normalizedValue !== '' && ($options === array() || in_array($normalizedValue, $options, true))) {
                return $normalizedValue;
            }

            return $normalizedValue;
        }

        if ($fieldType === 'reference') {
            return is_scalar($value) ? trim((string) $value) : '';
        }

        if ($fieldType === 'date') {
            return is_scalar($value) ? trim((string) $value) : '';
        }

        if ($fieldType === 'textarea' || $fieldType === 'text') {
            return is_scalar($value) ? trim((string) $value) : $value;
        }

        return $value;
    }

    /**
     * Normalizes field type.
     */
    private function normalizeFieldType(string $fieldType): string
    {
        $fieldType = strtolower(trim($fieldType));
        return in_array($fieldType, self::SUPPORTED_FIELD_TYPES, true) ? $fieldType : 'text';
    }

    /**
     * Normalizes field options.
     *
     * @param mixed $options
     * @return array<int, array<string, string>>
     */
    private function normalizeFieldOptions($options): array
    {
        $normalizedOptions = array();

        if (is_array($options) && $this->isAssociativeArray($options)) {
            foreach ($options as $value => $label) {
                $optionValue = trim((string) $value);
                if ($optionValue === '') {
                    continue;
                }

                $normalizedOptions[] = array(
                    'value' => $optionValue,
                    'label' => is_scalar($label) ? trim((string) $label) : $this->humanize($optionValue),
                );
            }

            return $normalizedOptions;
        }

        foreach ((array) $options as $option) {
            if (is_scalar($option)) {
                $optionValue = trim((string) $option);
                if ($optionValue === '') {
                    continue;
                }

                $normalizedOptions[] = array(
                    'value' => $optionValue,
                    'label' => $this->humanize($optionValue),
                );
                continue;
            }

            if (!is_array($option)) {
                continue;
            }

            $optionValue = trim((string) ($option['value'] ?? ''));
            if ($optionValue === '') {
                continue;
            }

            $normalizedOptions[] = array(
                'value' => $optionValue,
                'label' => trim((string) ($option['label'] ?? $this->humanize($optionValue))),
            );
        }

        return $normalizedOptions;
    }

    /**
     * Normalizes string list.
     *
     * @param mixed $value
     * @return string[]
     */
    private function normalizeStringList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_scalar($value)) {
            $raw = trim((string) $value);
            $items = strpos($raw, ',') !== false ? preg_split('/\s*,\s*/', $raw) : array($raw);
        } else {
            $items = array();
        }

        $normalized = array();
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);
            if ($string === '') {
                continue;
            }

            $normalized[$string] = $string;
        }

        return array_values($normalized);
    }

    /**
     * Loads YAML file.
     *
     * @return array<string, mixed>
     */
    private function loadYamlFile(string $relativePath): array
    {
        if ($relativePath === '') {
            return array();
        }

        $fullPath = $this->fullPath($relativePath);
        if (!is_file($fullPath)) {
            return array();
        }

        $content = @file_get_contents($fullPath);
        if (!is_string($content) || $content === '') {
            return array();
        }

        $parsed = $this->yamlParser->parse($content);
        return is_array($parsed) ? $parsed : array();
    }

    /**
     * Builds cache signature.
     */
    private function buildCacheSignature(): string
    {
        $signatureData = array(
            'schemaSources' => $this->schemaSources,
            'schemaDirectory' => $this->schemaDirectory,
            'schemaDirectories' => $this->schemaDirectories,
            'templatesDirectory' => $this->templatesDirectory,
            'typesFile' => $this->typesFilePath,
            'typesFiles' => $this->typesFilePaths,
            'relationsFile' => $this->relationsFilePath,
            'relationsFiles' => $this->relationsFilePaths,
            'typesMtime' => $this->readFileMtime($this->fullPath($this->typesFilePath)),
            'typesMtimes' => $this->readFileMtimes($this->typesFilePaths),
            'relationsMtime' => $this->readFileMtime($this->fullPath($this->relationsFilePath)),
            'relationsMtimes' => $this->readFileMtimes($this->relationsFilePaths),
            'types' => $this->typesById,
            'relations' => $this->relationsById,
        );

        $encoded = json_encode($signatureData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? sha1($encoded) : '';
    }

    /**
     * Normalizes schema sources.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSchemaSources(array $config, string $defaultTypesFile, string $defaultRelationsFile): array
    {
        $rawSources = is_array($config['sources'] ?? null) ? $config['sources'] : array();
        $sources = array();

        if ($rawSources !== array()) {
            foreach ($rawSources as $index => $rawSource) {
                $source = $this->normalizeSchemaSourceConfig($rawSource, $defaultTypesFile, $defaultRelationsFile, (string) $index);
                if ($source === null) {
                    continue;
                }

                $sources[] = $source;
            }
        }

        if ($sources !== array()) {
            return $sources;
        }

        $legacySource = $this->normalizeSchemaSourceConfig(array(
            'id' => 'project',
            'paths' => is_array($config['paths'] ?? null) ? $config['paths'] : array($config['path'] ?? 'config/schema'),
            'typesFiles' => $config['typesFiles'] ?? $config['typeFiles'] ?? array(),
            'relationsFiles' => $config['relationsFiles'] ?? $config['relationFiles'] ?? array(),
        ), $defaultTypesFile, $defaultRelationsFile, 'project');

        return $legacySource !== null ? array($legacySource) : array();
    }

    /**
     * Normalizes schema source config.
     *
     * @param mixed $rawSource
     * @return array<string, mixed>|null
     */
    private function normalizeSchemaSourceConfig($rawSource, string $defaultTypesFile, string $defaultRelationsFile, string $fallbackId): ?array
    {
        if (!is_array($rawSource)) {
            return null;
        }

        $paths = $this->collectSourcePaths($rawSource);
        $typesFiles = array_values(array_unique(array_merge(
            $this->buildSchemaFilePathList($paths, trim((string) ($rawSource['typesFile'] ?? $defaultTypesFile))),
            $this->collectSourceFiles($rawSource['typesFiles'] ?? $rawSource['typeFiles'] ?? array())
        )));
        $relationsFiles = array_values(array_unique(array_merge(
            $this->buildSchemaFilePathList($paths, trim((string) ($rawSource['relationsFile'] ?? $defaultRelationsFile))),
            $this->collectSourceFiles($rawSource['relationsFiles'] ?? $rawSource['relationFiles'] ?? array())
        )));

        if ($paths === array() && $typesFiles === array() && $relationsFiles === array()) {
            return null;
        }

        return array(
            'id' => trim((string) ($rawSource['id'] ?? $fallbackId)),
            'paths' => $paths,
            'typesFiles' => $typesFiles,
            'relationsFiles' => $relationsFiles,
        );
    }

    /**
     * Resolves relative path.
     */
    private function resolveRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return $path;
        }

        return $this->normalizePath($path);
    }

    /**
     * Collects source paths.
     *
     * @param array<string, mixed> $rawSource
     * @return string[]
     */
    private function collectSourcePaths(array $rawSource): array
    {
        $rawPaths = $rawSource['paths'] ?? $rawSource['directories'] ?? array();
        if (!is_array($rawPaths)) {
            $rawPaths = array($rawPaths);
        }

        $paths = array();
        foreach ($rawPaths as $path) {
            if (!is_scalar($path)) {
                continue;
            }

            $resolvedPath = $this->resolveRelativePath((string) $path);
            if ($resolvedPath === '') {
                continue;
            }

            $paths[$resolvedPath] = $resolvedPath;
        }

        return array_values($paths);
    }

    /**
     * Builds schema file path list.
     *
     * @return string[]
     */
    private function buildSchemaFilePathList(array $directories, string $fileName): array
    {
        if ($fileName === '') {
            return array();
        }

        $paths = array();

        foreach ($directories as $directory) {
            $directory = $this->resolveRelativePath($directory);
            if ($directory === '') {
                continue;
            }

            $path = $this->joinPath($directory, $fileName);
            $paths[$path] = $path;
        }

        return array_values($paths);
    }

    /**
     * Collects source files.
     *
     * @param mixed $files
     * @return string[]
     */
    private function collectSourceFiles($files): array
    {
        if (!is_array($files)) {
            $files = array($files);
        }

        $paths = array();
        foreach ($files as $file) {
            if (!is_scalar($file)) {
                continue;
            }

            $resolvedPath = $this->resolveRelativePath((string) $file);
            if ($resolvedPath === '') {
                continue;
            }

            $paths[$resolvedPath] = $resolvedPath;
        }

        return array_values($paths);
    }

    /**
     * Processes full path.
     */
    private function fullPath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '') {
            return $this->basePath;
        }

        if (preg_match('/^[A-Za-z]:\//', $relativePath) === 1) {
            return $relativePath;
        }

        return $this->basePath . '/' . ltrim($relativePath, '/');
    }

    /**
     * Joins path.
     */
    private function joinPath(string $left, string $right): string
    {
        $left = rtrim(str_replace('\\', '/', $left), '/');
        $right = ltrim(str_replace('\\', '/', $right), '/');

        if ($left === '') {
            return $right;
        }

        if ($right === '') {
            return $left;
        }

        return $left . '/' . $right;
    }

    /**
     * Normalizes path.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = array();

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Normalizes ID.
     */
    private function normalizeId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Humanizes the requested value.
     */
    private function humanize(string $value): string
    {
        $value = str_replace('_', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? ucwords($value) : $value;
    }

    /**
     * Reads file mtime.
     */
    private function readFileMtime(string $path): int
    {
        $mtime = @filemtime($path);
        return $mtime === false ? 0 : (int) $mtime;
    }

    /**
     * Reads file mtimes.
     *
     * @return array<string, int>
     */
    private function readFileMtimes(array $paths): array
    {
        $mtimes = array();

        foreach ($paths as $path) {
            $mtimes[$path] = $this->readFileMtime($this->fullPath($path));
        }

        return $mtimes;
    }

    /**
     * Determines whether associative array.
     */
    private function isAssociativeArray(array $value): bool
    {
        if ($value === array()) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
