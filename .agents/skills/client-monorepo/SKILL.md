---
name: client-monorepo
description: "Client monorepo operating guide. Use whenever adding or modifying packages, package docs, dev/* environments, root GitHub Actions package workflows, README/docs, or when an agent needs to understand this repo layout before structural changes."
---

# Client Monorepo

Use this skill as the operating guide for the current repository.

## Structure
- packages: Contains all packages. No matter if wordpress plugin, theme, n8n workflows or cloudflare workers. Packages manage their own dependencies, source, tests, docs, and package-specific `.gitignore` files. GitHub Actions workflows live as real files in `.github/workflows`, not inside packages and not as symlinks.
- dev: Contains portable but strongly opinionated dev environments that quickly allow working with the packages
- docs: Central docs tree. `docs/setup` contains repository setup and maintainer conventions, `docs/packages/<package-name>` symlinks to `packages/<package-name>/docs`, and files directly under `docs` contain shared cross-package knowledge.

## Reference Routing

| Task | Reference |
| --- | --- |
| Understand repository layout | `references/repository-structure.md` |
| Add a package under `packages/` | `references/repository-structure.md` |
| Add or update package docs | `references/repository-structure.md`, `references/how-to-write-docs.md` |
| Add or update shared docs | `references/how-to-write-docs.md` |
| Decide public or internal docs visibility | `references/how-to-write-docs.md` |
| Add package workflow files under `.github/workflows` | `references/repository-structure.md`, `references/github-workflows.md` |
| Update package CI or release workflows | `references/github-workflows.md` |
| Add a WordPress plugin or theme package to DDEV | `references/repository-structure.md`, `references/wordpress-package-mounts.md` |
| Add or change a `dev/wordpress-*` environment | `references/repository-structure.md`, `references/wordpress-package-mounts.md` |
| Update setup documentation | The setup doc for the affected behavior |

Use the files in `references/` for the current setup details before changing repository structure, package docs, or package workflows.

## Final Step For Changes

After any repository-changing operation, update the root `AGENTS.md` file, creating it if needed. Include a brief package scope section that lets future agents quickly identify what each package is and which major tools, platforms, or domain skills may apply.

For each package, record enough context to route usage accurately, such as:
- package name and path
- package type, such as WordPress plugin, WordPress theme, Cloudflare Worker, n8n workflow, or shared library
- primary tools or platforms used, such as DDEV, WordPress, Cloudflare, n8n, Node, PHP, Composer, or npm

Keep this section short and factual. Its purpose is to help agents decide whether specialized skills should be used.

When adding or changing docs, also check that docs live in the correct source location, package docs are symlinked under `docs/packages`, and docs frontmatter includes the required visibility metadata.
