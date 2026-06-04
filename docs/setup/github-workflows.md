---
title: GitHub Workflows
description: How to add package workflows as real root workflow files with package-scoped triggers.
visibility: internal
---

## Workflow Model

GitHub Actions workflows live as real files in the repository root `.github/workflows` directory. Do not store GitHub Actions workflow files inside packages and do not expose package workflows through symlinks.

Use this model:

- Each package workflow is a physical `.yml` file in `.github/workflows`.
- Workflow filenames start with the package folder name, such as `web-ci.yml` or `web-release.yml`.
- Package workflows own their own `on` triggers, path filters, jobs, and release rules.
- Packages keep source code, docs, dependencies, and tests under `packages/<package-name>`.

## Repository Layout

```text title="Workflow layout"
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

The workflow file belongs to the central repository, but the filename, workflow name, and job names should make the package ownership obvious.

## Naming Convention

Use the package folder name as the workflow filename prefix:

```text title="Package workflow names"
.github/workflows/<package-name>-ci.yml
.github/workflows/<package-name>-docs.yml
.github/workflows/<package-name>-release.yml
```

Name the workflow and jobs with the same package prefix:

```yaml title=".github/workflows/web-ci.yml"
name: web CI

on:
  workflow_dispatch:

jobs:
  web-ci:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
```

Do not use nested workflow folders such as `.github/workflows/<package-name>/ci.yml`; GitHub Actions only loads workflow files directly in `.github/workflows`.

## Trigger Pattern

Every package workflow declares its own trigger. Scope push triggers with both `branches` and `paths` so unrelated package changes do not run the workflow.

```yaml title="Package-scoped push trigger"
on:
  push:
    branches:
      - dev
    paths:
      - packages/web/src/content/docs/*.mdx
```

Adjust `web` and the path glob for the package and workflow purpose. Add additional paths only when those files affect the workflow.

## Package CI Workflow

Create CI workflows as real root workflow files:

```yaml title=".github/workflows/web-ci.yml"
name: web CI

on:
  push:
    branches:
      - dev
    paths:
      - packages/web/**
      - .github/workflows/web-ci.yml
  pull_request:
    paths:
      - packages/web/**
      - .github/workflows/web-ci.yml

concurrency:
  group: web-ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  web-ci:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run package checks
        working-directory: packages/web
        run: npm test
```

Use the package directory as `working-directory` for commands that belong to the package.

## Package Release Workflow

Create release workflows as real root workflow files when a package needs tag-based releases:

```yaml title=".github/workflows/web-release.yml"
name: web Release

on:
  push:
    tags:
      - web-v*

jobs:
  web-release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Build release
        working-directory: packages/web
        run: npm run build
```

Use a package-specific tag prefix such as `web-v1.2.3` so one package release does not trigger another package release.

## Adding A Package To CI/CD

When adding a package, add package-named workflow files directly to the central workflow directory.

1. Create the package folder in `packages/<package-name>`.
2. Add one real workflow file per package workflow in `.github/workflows/<package-name>-<purpose>.yml`.
3. Put the package name in the workflow filename, `name`, and job names.
4. Add `on.push.branches` and `on.push.paths` triggers that target the package paths.
5. Add `pull_request.paths` only when the package needs pull request validation.
6. Add a package-specific release workflow and tag prefix only when the package is releasable.
