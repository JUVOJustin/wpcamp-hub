# Repository Agent Notes

## Package Scope

- `packages/wpcamp-hub-plugin`: WordPress plugin package. Uses WordPress, PHP, Composer, npm, `@wordpress/scripts`, `wp-env`, PHPStan, PHPCS, and GitHub Actions workflows in the root `.github/workflows` directory. Includes a daily WordCamp session/speaker importer (`src/Import/`) built on Action Scheduler (`woocommerce/action-scheduler`, excluded from Strauss prefixing); see `packages/wpcamp-hub-plugin/docs/wordcamp-import.md`. It shares the `src/Import` namespace, `wpcamp-hub` AS group, `wpcamp_hub/…` hook convention, and WordPress.org-username identity model with the WordCamp attendee importer so both can later be driven by one master sync job per event that fans out independent schedule/speaker/attendee jobs.
- `packages/wpcamp-hub-theme`: WordPress theme package with PHP template files and theme styles.
