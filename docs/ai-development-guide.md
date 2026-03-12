# AI Development Guide

This document defines how AI coding agents should evolve the codebase,
track changes, evaluate version impact, and propose commits.

This file complements the repository root `AGENTS.md`.

`AGENTS.md` defines *where and how to work in the repository*.
This file defines *how to evolve the code safely and consistently*.

Agents must consult this guide when modifying runtime behavior, schema,
themes, templates, or core CMS code.

---

# 1. Development Philosophy

The CMS is designed as a **stable, structured authoring system**.

Agents must prioritize:

- predictability
- backward compatibility
- minimal surprise for content authors
- reuse of existing architecture

Avoid introducing complexity unless it clearly improves the system.

Prefer **evolution of existing structures** over replacing them.

---

# 2. Versioning Model

The project follows a pragmatic **Semantic Versioning model**:

```

MAJOR.MINOR.PATCH

```

Examples:

```

0.7.2
0.8.0
1.0.0
1.1.3
2.0.0

```

Meaning:

| Part | Meaning |
|-----|-----|
| MAJOR | structural or incompatible change |
| MINOR | new feature without breaking existing usage |
| PATCH | bugfix or small improvement |

---

# 3. Development Phase (0.x)

Versions below `1.0.0` are considered **active development**.

During this phase:

- internal refactoring is allowed
- structures may still evolve
- version increments remain meaningful

Recommended rules:

Feature added → increase **MINOR**

```

0.6.0 → 0.7.0

```

Bugfix → increase **PATCH**

```

0.7.1 → 0.7.2

```

---

# 4. Stable Phase (1.x)

From `1.0.0` onward the system should behave predictably.

Agents must be more conservative with changes.

Breaking changes should be avoided unless absolutely necessary.

When a breaking change is unavoidable, agents must clearly mark it.

---

# 5. Evaluating Version Impact

After completing non-trivial changes, agents must evaluate the impact.

Possible outcomes:

- **No version change**
- **PATCH recommended**
- **MINOR recommended**
- **MAJOR recommended**

### PATCH examples

- bugfix
- typo correction with functional impact
- CSS fix
- validation improvement
- small performance improvement

### MINOR examples

- new feature
- new schema field
- new template capability
- new CMS extension
- new optional configuration

### MAJOR examples

- schema structure change
- breaking template expectations
- removal or renaming of stable configuration keys
- incompatible runtime behavior change
- migration required for existing content

---

# 6. Change Logging by Agents

Agents must produce a concise change report after significant work.

Minimum format:

```

## Change Report

### Modified

* path/file.ext — short description
* path/file.ext — short description

### Change Type

Feature / Bugfix / Refactor / Documentation / Architecture

### Impact

Short explanation of system behavior change.

### Risks

Potential compatibility or migration issues.

### Version Impact

none / PATCH / MINOR / MAJOR

```

This helps maintainers understand the change quickly.

---

# 7. Commit Recommendations

Agents should propose commits when a **coherent change unit** is complete.

A commit should represent **one logical change**.

Good commit points include:

- feature completion
- bugfix completion
- completed refactor
- stable intermediate state after risky change
- schema + renderer updates that belong together

Avoid suggesting commits when:

- work is incomplete
- the system is temporarily broken
- several unrelated changes are mixed

---

# 8. Commit Message Suggestions

When recommending a commit, agents must propose a concise summary.

Format:

```

## Commit Recommendation

Commit is recommended.

Summary:
add schema validation for relation fields

```

Optional extended explanation:

```

Reason:
Improves validation for relation fields during content loading
and prevents runtime rendering errors.

```

---

# 9. Commit Summary Style

Commit summaries should be:

- short
- clear
- descriptive
- written in present tense

Preferred examples:

```

add markdown embed parser
fix navigation cache invalidation
refactor content repository loading
add schema validation for relations
update AGENTS rules

```

Avoid vague messages:

```

changes
updates
misc fixes
work in progress

```

---

# 10. Breaking Change Reporting

Agents must explicitly report possible breaking changes.

Breaking changes include:

- schema structure changes
- renamed configuration keys
- changed frontmatter expectations
- changed template field usage
- modified runtime behavior that affects existing content

Example report:

```

### Risks

Potential breaking change:
Existing templates referencing `summary` may need to use
`excerpt` instead.

```

Never introduce a breaking change silently.

---

# 11. Refactoring Rules

Refactoring is allowed but must follow these principles:

- do not change behavior unintentionally
- do not mix refactoring and new features in one change set
- keep commits logically separated
- ensure validation scripts still pass

Refactoring should improve:

- readability
- maintainability
- architectural clarity

without increasing complexity.

---

# 12. Validation After Code Changes

Agents must follow validation rules from `AGENTS.md`.

Typical checks:

```

php scripts/validate-content.php
php scripts/release-check.php --strict

```

If the change touches schema, runtime, themes, templates,
graphs, or i18n behavior, the strict release check must be used.

---

# 13. Agent Responsibility

Agents are responsible for ensuring that every change answers:

- What changed?
- Why was it changed?
- Is the change complete?
- Should a commit be made now?
- What version impact does it have?

Clear change descriptions are considered part of the task.

---

# 14. When to Do Nothing

Sometimes the correct action is **no structural change**.

If an existing structure already solves the problem:

- reuse it
- extend it
- document it

Do not introduce new systems unnecessarily.

Simplicity and consistency take priority over novelty.
```

---

## Ergänzung für deine bestehende AGENTS.md

Am Ende deiner aktuellen Datei würde ich nur das hier ergänzen:

```md
## Development and Code Evolution

Guidelines for code evolution, versioning, commit recommendations,
and change logging are defined in:

docs/ai-development-guide.md

Agents must consult this document when:

- modifying CMS runtime code
- introducing new features
- refactoring existing logic
- proposing commits
- evaluating version impact
