.PHONY: up install-pressbooks install enable-lti seed seed-books test test-deep-linking test-ags collect-artifacts

all:
	make up install-pressbooks install enable-lti seed seed-books test-deep-linking test-ags

up:
	bash scripts/lab-up.sh

install-pressbooks:
	bash scripts/install-pressbooks.sh

install:
	bash scripts/install-plugin.sh

enable-lti:
	bash scripts/moodle-register-lti.sh

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


