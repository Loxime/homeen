SHELL := /bin/bash

.PHONY: bootstrap up down restart logs ps migrate test quality security-check reset-db shell-php shell-node

bootstrap:
	./scripts/local-bootstrap.sh

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f --tail=150

ps:
	docker compose ps

migrate:
	docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec -T php composer test
	docker compose exec -T frontend npm run test:run

quality: security-check
	docker compose exec -T php composer quality
	docker compose exec -T frontend npm run lint
	docker compose exec -T frontend npm run test:run
	docker compose exec -T frontend npm run build
	docker compose exec -T frontend sh -c 'chown -R "$$LOCAL_UID:$$LOCAL_GID" dist 2>/dev/null || true'

security-check:
	./scripts/security-check.sh

reset-db:
	docker compose down -v
	docker compose up -d --build

shell-php:
	docker compose exec php sh

shell-node:
	docker compose exec frontend sh
