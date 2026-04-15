.PHONY: help test test-coverage test-file test-filter benchmark example clean install

# Docker command configuration
DOCKER_RUN = docker run --rm -t -v $$(pwd):/app --user $$(id -u):$$(id -g) -w /app mapper:latest
PHP = $(DOCKER_RUN) php

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-20s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Install dependencies
	docker build -f .develop/Dockerfile -t mapper:latest .

install: ## Install dependencies
	composer install

test: ## Run all tests
	$(PHP) vendor/bin/phpunit

test-coverage: ## Run tests with coverage report
	$(PHP) vendor/bin/phpunit --coverage-text

test-file: ## Run specific test file (usage: make test-file FILE=tests/Unit/MapperTest.php)
	@if [ -z "$(FILE)" ]; then \
		echo "Error: FILE parameter is required. Usage: make test-file FILE=tests/Unit/MapperTest.php"; \
		exit 1; \
	fi
	$(PHP) vendor/bin/phpunit $(FILE)

test-filter: ## Run tests matching filter (usage: make test-filter FILTER=testMethodName)
	@if [ -z "$(FILTER)" ]; then \
		echo "Error: FILTER parameter is required. Usage: make test-filter FILTER=testMethodName"; \
		exit 1; \
	fi
	$(PHP) vendor/bin/phpunit --filter $(FILTER)

benchmark: ## Run performance benchmarks
	$(PHP) tests/Benchmark.php

example: ## Run the basic usage example
	$(PHP) examples/BasicUsage.php

clean: ## Clean cache and temporary files
	rm -rf .phpunit.cache vendor

php: ## Run PHP command (usage: make php CMD="script.php")
	@if [ -z "$(CMD)" ]; then \
		echo "Error: CMD parameter is required. Usage: make php CMD=\"script.php\""; \
		exit 1; \
	fi
	$(PHP) $(CMD)
