.PHONY: up install-pressbooks install enable-lti seed seed-books simulate-ags test-deep-linking test-ags credentials setup-nginx

all:
	make setup-nginx up install-pressbooks install enable-lti seed seed-books simulate-ags test-deep-linking test-ags credentials

setup-nginx:
	sudo bash scripts/setup-nginx.sh

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

simulate-ags:
	sudo docker cp scripts/create-ags-activity.php moodle:/tmp/
	sudo docker cp scripts/simulate-ags-grade.php moodle:/tmp/
	sudo docker cp scripts/create-deep-linking-activity.php moodle:/tmp/
	sudo docker exec moodle php /tmp/create-ags-activity.php
	sudo docker exec moodle php /tmp/simulate-ags-grade.php
	bash -c 'source scripts/load-env.sh && sudo docker exec -e MOODLE_URL="$$MOODLE_URL" moodle php /tmp/create-deep-linking-activity.php'

test:
	bash scripts/lti-smoke-test.sh

test-deep-linking:
	bash scripts/ci-test-deep-linking.sh

test-ags:
	bash scripts/ci-test-ags-grade.sh

credentials:
	bash scripts/show-credentials.sh

collect-artifacts:
	bash scripts/ci-collect-artifacts.sh

clean:
	@echo "🛑 Stopping containers, removing volumes, and cleaning built images..."
	bash lti-local-lab/reset.sh
	@echo "✅ Cleaned up development environment"

rebuild:
	make clean all
