COMPOSER = composer
PHAR = opentbs-tool.phar

all: $(PHAR)

$(PHAR): composer.lock src/Main.php bin/opentbs-tool
	vendor/bin/phar-composer build

composer.lock: composer.json
	{ [ -f $< ] && $(COMPOSER) update; } || $(COMPOSER) install

clean:
	rm -f $(PHAR)

realclean: clean
	rm -rf vendor

distclean: realclean
	rm -f composer.lock
