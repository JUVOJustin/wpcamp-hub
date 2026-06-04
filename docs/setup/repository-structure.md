---
title: Repository Structure
description: How packages and development environments are organized in this monorepo.
visibility: internal
---

## Purpose

This repository keeps the client project code in one monorepo while allowing each development environment to stay local and replaceable.

- `.github/workflows` contains the GitHub workflows for the whole repo including per package workflows.
- `packages` contains the reusable project packages.
- `dev` contains ready-made local development environments.
- `docs` contains shared docs, setup docs, and package docs entry points.
- `.agents/skills` contains agent skills to help work on the monorepo.

## Agent Skills

Project-local agent skills live in `.agents/skills`. The `client-monorepo` skill explains how agents should add packages, development environments, GitHub Actions workflow wiring, and WordPress package mounts. Its `references/` folder links directly to the current `docs/setup/*.md` files so reference material stays current after this repository is cloned.

## Packages

Store project-owned code in `packages`.

```text title="Package directory layout"
packages/
  <package-name>/
  <another-package-name>/
```

Each package owns its own source code, dependency files, build tooling, tests, docs, and package-specific `.gitignore` rules. For example, a WordPress plugin package owns its own `composer.json`, `composer.lock`, `package.json`, docs, and source files when it needs those tools.

### Adding A Package

Create a new folder in `packages`:

```bash title="Create a new package"
mkdir packages/<package-name>
```

The folder name should describe the package role. It does not need to match the name used inside a development environment.

Examples:

- `packages/<plugin-package>` can be mounted into WordPress as `wp-content/plugins/<wordpress-plugin-folder>`.
- `packages/<theme-package>` can be mounted into WordPress as `wp-content/themes/<wordpress-theme-folder>`.
- `packages/<worker-package>` can contain a Cloudflare Worker package.

Add package-specific dependency files inside the package folder only when the package needs them.

```text title="Package with its own tooling"
packages/
  <package-name>/
    composer.json
    composer.lock
    docs/
    src/
```

### Package Docs

Package-specific docs are authored in the package that owns the knowledge:

```text title="Package docs source"
packages/
  <package-name>/
    docs/
      index.md
```

Expose package docs in the root docs tree with a symlink:

```text title="Root package docs entry point"
docs/
  packages/
    <package-name> -> ../../packages/<package-name>/docs
```

Edit package docs through `packages/<package-name>/docs`, not through the symlink path. Read `docs/setup/how-to-write-docs.md` for authoring rules, frontmatter requirements, and public/internal visibility metadata.

### Package Workflows

Use package-name-prefixed workflow files at the repository root:

```text title="Root workflow convention"
.github/
  workflows/
    <package-name>-ci.yml
    <package-name>-docs.yml
    <package-name>-release.yml

packages/
  <package-name>/
    src/
    package.json
    README.md
```

Do not use nested root workflow folders such as `.github/workflows/<package-name>/ci.yml`. Package workflow files live directly in `.github/workflows` with `<package-name>-<purpose>.yml` filenames.

Use triggers inside each package workflow to scope the workflow to the package paths:

```yaml title="Package workflow trigger"
on:
  push:
    branches:
      - dev
    paths:
      - packages/web/src/content/docs/*.mdx
```

Use the package folder name as the workflow filename prefix:

```text title="Package workflow filename"
.github/workflows/<package-name>-ci.yml
```

Workflow `name` values and job IDs should also include the package name so GitHub Actions history is easy to scan.

Read `docs/setup/github-workflows.md` for package-scoped trigger, CI, and release examples.

### Ignore Rules

The root `.gitignore` is a baseline for common generated directories that may appear anywhere in the repository, such as `node_modules`, `vendor`, `dist`, `build`, and `coverage`. Packages should keep their own `.gitignore` files for package-specific build outputs, caches, generated files, or exceptions where a generated directory must be committed.

## Development Environments

Store ready-made local environments in `dev`.

```text title="Development environment layout"
dev/
  wordpress-<name>/
    .ddev/
```

For WordPress specifics read `docs/setup/wordpress-package-mounts.md` before adding a new WordPress plugin or theme package to the local environment.

## Documentation

Use the root docs tree as the central documentation surface:

```text title="Root docs layout"
docs/
  setup/
  packages/
  <shared-topic>.md
```

- `docs/setup` contains repository setup and maintainer conventions.
- `docs/packages` contains symlinks to package-owned docs folders.
- Files directly under `docs` contain shared knowledge that applies across packages.
