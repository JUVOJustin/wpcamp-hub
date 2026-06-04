---
title: WordPress Package Mounts
description: How to expose monorepo packages inside the DDEV WordPress environment with bind mounts.
visibility: internal
---

## Purpose

WordPress development environments should live in `dev/wordpress-*` folders, but plugin and theme source code lives in `packages`. DDEV mounts selected package folders into WordPress so the local site can run them without copying files or committing generated WordPress content.

## Mount File

Map WordPress plugin and theme packages with `dev/wordpress-<name>/.ddev/docker-compose.mounts.yaml`.

```yaml title="dev/wordpress-<name>/.ddev/docker-compose.mounts.yaml"
services:
  web:
    volumes:
      - type: bind
        source: ${DDEV_APPROOT}/../../packages/<plugin-package>
        target: /var/www/html/wp-content/plugins/<wordpress-plugin-folder>
      - type: bind
        source: ${DDEV_APPROOT}/../../packages/<theme-package>
        target: /var/www/html/wp-content/themes/<wordpress-theme-folder>
```

`DDEV_APPROOT` points to the active `dev/wordpress-<name>` folder on the host. The `../../packages/...` part walks from that WordPress environment back to the repository root and then into `packages`.
