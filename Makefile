.PHONY: up install-pressbooks install enable-lti seed seed-books test test-deep-linking test-ags collect-artifacts clean

all:
	make up install-pressbooks install enable-lti seed seed-books test-deep-linking test-ags

up:
	bash scripts/lab-up.sh

install-pressbooks:
	bash scripts/install-pressbooks.sh

install:
	bash scripts/install-plugin.sh

enable-lti:
	bash scripts/register-lti-tool.sh
	bash scripts/enable-email-sharing.sh

seed:
	bash scripts/seed-moodle.sh

seed-books:
	bash scripts/seed-pressbooks.sh

test:
	bash scripts/lti-smoke-test.sh

test-deep-linking:
	bash scripts/ci-test-deep-linking.sh

test-ags:
	bash scripts/ci-test-ags-grade.sh

collect-artifacts:
	bash scripts/ci-collect-artifacts.sh

clean:
	@echo "🛑 Stopping containers and removing volumes..."
	bash lti-local-lab/reset.sh
	@echo "✅ Cleaned up development environment"
