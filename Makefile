COMPOSER = composer
COMPOSER_FLAGS = --prefer-dist
PHP = $(shell which php)
PHAR = opentbs-tool.phar
BUILD_DIR = build

all: $(PHAR)

# phar-composer just copies everything, so here would be room for optimization
$(PHAR): composer.lock src/Main.php Makefile bin/opentbs-tool
	$(PHP) -d phar.readonly=0 vendor/bin/phar-composer build

composer.lock: composer.json
	{ [ -f $< ] && $(COMPOSER) $(COMPOSER_OPTIONS) update; } || $(COMPOSER) $(COMPOSER_OPTIONS) install

clean:
	rm -f $(PHAR)

realclean: clean
	rm -rf vendor

distclean: realclean
	rm -f composer.lock
	find . -name "*~" -exec rm -f {} \;
