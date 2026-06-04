---
title: How To Write Docs
description: How to place documentation and mark docs as public or internal in this monorepo.
visibility: internal
---

## Purpose

This repository keeps documentation close to the code or knowledge it describes while preserving a central docs tree for browsing and future docs platform builds.

## Where Docs Live

- Package-specific docs live in `packages/<package-name>/docs`.
- Package docs are exposed from `docs/packages/<package-name>` with a symlink to `../../packages/<package-name>/docs`.
- Shared cross-package docs live directly in `docs`.
- Repository setup and maintainer conventions live in `docs/setup`.

Edit package docs through `packages/<package-name>/docs`, not through the symlink path in `docs/packages`.

## Frontmatter

Every documentation Markdown or MDX file under `docs/**` or `packages/*/docs/**` needs frontmatter with `title`, `description`, and `visibility`.

```md title="Docs frontmatter"
---
title: My Doc Title
description: One sentence describing what this page helps readers understand or do.
visibility: public
---
```

Use these `visibility` values:

- `public` means the page is eligible to be shown in a centralized docs platform.
- `internal` means the page is developer-facing and should remain visible only in this repository.

Use `visibility: internal` for setup, maintainer, workflow, agent-facing, and local development docs. Use `visibility: public` only for content that should be published to readers outside the development team.

Do not add docs visibility frontmatter to machine-read Markdown files that have their own schema, such as `.agents/**/SKILL.md`.

## Package Docs

When a package needs docs:

1. Create `packages/<package-name>/docs`.
2. Add package docs with the required frontmatter.
3. Create a symlink from `docs/packages/<package-name>` to `../../packages/<package-name>/docs`.
4. Update shared docs only when the information applies across packages.

```bash title="Expose package docs in root docs"
ln -s ../../packages/<package-name>/docs docs/packages/<package-name>
```

## Shared Docs

Create files directly under `docs` only for shared knowledge that applies across packages. Prefer a package docs folder when the content explains one package's behavior, API, setup, or release process.