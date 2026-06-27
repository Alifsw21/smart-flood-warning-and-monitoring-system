.PHONY: help compose-up compose-down compose-dev test test-all seed build-images

COMPOSE ?= docker compose

help:
	@echo "Smart Flood Warning — Makefile shortcuts"
	@echo "  make compose-up     Start full stack (detached)"
	@echo "  make compose-down   Stop stack and remove volumes"
	@echo "  make compose-dev    Start stack with dev overrides"
	@echo "  make test-all       Run all service test suites"
	@echo "  make seed           Regenerate database/seed.sql"
	@echo "  make build-images   Build Kubernetes-tagged images"

compose-up:
	$(COMPOSE) up -d --build

compose-down:
	$(COMPOSE) down -v

compose-dev:
	$(COMPOSE) -f docker-compose.yml -f docker-compose.dev.yml up -d --build

test-all:
	./oauth-server/tests/http.sh
	./php-user/tests/run.sh
	./php-river/tests/run.sh
	./php-analytics/tests/run.sh
	./python-ml-service/tests/http.sh
	./express-gateway/tests/smoke.sh
	./express-gateway/tests/s3-ml-e2e.sh
	./monitoring/tests/smoke.sh

seed:
	cd database && python3 generateSeed.py

migrate:
	docker exec -i smartcity-mysql mysql -uroot -p$${MYSQL_ROOT_PASSWORD:-RootSecret} kelompok2 < database/migrate-spec-gap.sql

build-images:
	./k8s/build-images.sh
