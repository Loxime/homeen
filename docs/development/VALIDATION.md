# Validation report

Date: 2026-09-02

This project was generated as a complete LOCAL application package before the first repository commit.

## Validated before delivery

- Every PHP source, migration and test file passes `php -l` with PHP 8.4.
- `composer.json`, `package.json` and `tsconfig.json` parse as valid JSON.
- Docker Compose and Symfony configuration files parse as valid YAML.
- `scripts/local-bootstrap.sh`, `scripts/security-check.sh` and the PHP container entrypoint pass shell syntax checks.
- Every TypeScript source and every `<script setup lang="ts">` block parses successfully with the TypeScript compiler.
- The Pomodoro timestamp/cycle algorithm was exercised directly for work, fixed five-minute break, repeated cycles and cross-boundary overlap.
- `.env` is ignored by the committed `.gitignore` in an isolated Git ignore check.
- No real `ACCESS_KEY`, PostgreSQL password or `APP_SECRET` is included in the package.

## LOCAL runtime gate

The packaging environment does not expose a Docker daemon and cannot execute the project's containers. The remaining runtime/integration validation is therefore deliberately encoded in `scripts/local-bootstrap.sh` and `make quality` and must run on the VM named LOCAL.

The bootstrap is fail-fast. It:

1. refuses to create `.env` unless `.gitignore` exists;
2. generates random LOCAL secrets and verifies `.env` is ignored by Git;
3. validates the resolved Compose model;
4. builds and starts PostgreSQL, PHP/Symfony, Vite, Nginx and the trash scheduler;
5. waits until both the database-backed `/api/health` endpoint and the frontend answer;
6. applies Doctrine migrations through the PHP entrypoint;
7. runs PHPUnit + PHPStan;
8. runs Vue TypeScript checks + Vitest + the production Vite build.

A non-zero result from any of these checks means LOCAL is not accepted as complete; the script prints recent Docker logs when startup health fails.
