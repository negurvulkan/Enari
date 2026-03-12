<?php

/**
 * Contract for module-provided type panel builders in typed entry views.
 */

declare(strict_types=1);

/**
 * Defines the contract for module type panel providers.
 */
interface TypePanelProviderInterface
{
    /**
     * Returns ID.
     */
    public function getId(): string;

    /**
     * Determines whether the requested value.
     *
     * @param array<string, mixed> $context
     */
    public function supports(array $document, array $context): bool;

    /**
     * Builds panel.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function buildPanel(array $document, array $context): ?array;
}
