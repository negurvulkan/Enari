# Schema Modeling Guide

This guide applies to `config/schema/types.yaml` and `config/schema/relations.yaml`.

## Reuse Before Creation

Before creating a new type or relation, inspect the existing schema first.

Create a new type only if:

- the current types do not model the concept cleanly
- the new concept has a stable identity and reusable field structure
- the rendering implications are understood

Create a new relation only if:

- no existing relation expresses the same semantics
- direction, cardinality, and allowed endpoint types are clear
- the relation will improve structured querying, graph output, or typed rendering

## Stable ID Rules

Use stable, lowercase, slug-like IDs.

- type IDs: lowercase with hyphens if needed
- relation IDs: lowercase with underscores or the project’s prevailing style, but keep them stable once introduced
- field IDs: lowercase snake_case and content-model oriented

Do not rename IDs casually. A rename can break existing content, type templates, relations, graph rendering, and cross-file assumptions.

## Safe Evolution Rules

Prefer additive evolution.

- add fields rather than renaming or removing them
- add options conservatively to selects and multiselects
- preserve existing groups when possible
- extend templates only when the model really needs different rendering

If you must make a breaking schema change, treat it as a coordinated migration, not a small edit.

## Modeling Checklist for New Types

A new type is allowed only after confirming all of the following:

- the existing types are unsuitable
- the proposed fields are coherent and reusable
- field groups support readable editing and rendering
- references and reference-lists point to meaningful target types
- a type template strategy exists: reuse a generic template or add a dedicated one intentionally

Good questions:

- Is this really a new entity type, or just a differently titled page?
- Does it need structured fields, or would prose plus explicit relations be enough?
- Will more than one page use this type?

## Modeling Checklist for New Relations

Confirm all of the following:

- the relation semantics are distinct from existing relations
- `from_types` and `to_types` are intentionally restricted
- cardinality reflects the model, not just one example page
- label and inverse label are both meaningful
- color and style fit the graph vocabulary without creating noise

## Template and Rendering Implications

When adding or changing a type:

- check whether `cms/type-templates/types/` already has a suitable template
- prefer reusing `entity-default.tpl` or another existing template unless the body structure genuinely differs
- avoid creating a type-specific template only for minor copy or field-order changes

## Warning Signs That Require Escalation

Pause and propose the change instead of silently doing it if:

- a new type overlaps heavily with an existing one
- the relation direction is ambiguous
- the change would require renaming existing IDs
- the field model depends on unclear product semantics
- the change implies new global UI or graph conventions

## Validation

Schema changes are runtime changes. Always run:

```bash
php scripts/release-check.php --strict
```
