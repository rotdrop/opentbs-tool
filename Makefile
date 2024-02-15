COMPOSER = composer
PHP = $(shell which php)
PHAR = opentbs-tool.phar

all: $(PHAR)

$(PHAR): composer.lock src/Main.php bin/opentbs-tool
	$(PHP) -d phar.readonly=0 vendor/bin/phar-composer build

composer.lock: composer.json
	{ [ -f $< ] && $(COMPOSER) update; } || $(COMPOSER) install

clean:
	rm -f $(PHAR)

realclean: clean
	rm -rf vendor

distclean: realclean
	rm -f composer.lock
	find . -name "*~" -exec rm -f {} \;
