##########################################################
# User configurables:
##########################################################
# Location where gweb should be installed to (excluding conf, dwoo dirs).
GDESTDIR = /usr/share/ganglia-webfrontend

# Location where default apache configuration should be installed to.
GCONFDIR = /etc/ganglia-web

# Gweb statedir (where conf dir and Dwoo templates dir are stored)
GWEB_STATEDIR = /var/lib/ganglia-web

# Gmetad rootdir (parent location of rrd folder)
GMETAD_ROOTDIR = /var/lib/ganglia

# Name of the user that runs the Apache server 
APACHE_USER = www-data

# Name of the group that runs the Apache server 
APACHE_GROUP = $(APACHE_USER)

# PHP xUnit
PHPUNIT = phpunit

# PHP_CodeSniffer
PHPCS = phpcs

# PHP Code Beautifier and Fixer
PHPCBF = phpcbf
##########################################################

# Gweb version
GWEB_VERSION = 3.7.4

DIST_NAME = ganglia-web
DIST_DIR = $(DIST_NAME)-$(GWEB_VERSION)
DIST_TARBALL = $(DIST_DIR).tar.gz

TARGETS = conf_default.php ganglia-web.spec version.php apache.conf

# Coding standard
STANDARD = test/phpcs-ganglia-web.xml

CODE = *.php api graph.d lib nagios test

all: default

default:	$(TARGETS)

sniff:
	@$(PHPCS) --version && echo
	$(PHPCS) --standard=$(STANDARD) -p $(CODE)

# convert exclusive standard into inclusive rules
rules:
	@$(PHPCS) --standard=$(STANDARD) -e | grep "^  " | sed -e 's/^  / <rule ref="/' -e 's/$$/"\/>/'

fix:
	$(PHPCBF) --standard=$(STANDARD) $(CODE)

.PHONY: test
test:
	$(PHPUNIT) test

clean:
	rm -rf $(TARGETS) $(DIST_DIR) $(DIST_TARBALL) rpmbuild

conf_default.php:	conf_default.php.in
	sed -e "s|@vargmetadir@|$(GMETAD_ROOTDIR)|" -e "s|@vargwebstatedir@|$(GWEB_STATEDIR)|g" conf_default.php.in > conf_default.php

ganglia-web.spec:	ganglia-web.spec.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@vargwebdir@|$(GWEB_STATEDIR)|" -e "s|@varapacheuser@|$(APACHE_USER)|g" -e "s|@etcdir@|$(GCONFDIR)|g" ganglia-web.spec.in > ganglia-web.spec

version.php:	version.php.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php

apache.conf:	apache.conf.in
	sed -e "s|@GDESTDIR@|$(GDESTDIR)|g" apache.conf.in > apache.conf

dist-dir:	default
	rsync --exclude "rpmbuild" --exclude "*.gz" --exclude "Makefile" --exclude "*debian*" --exclude "$(DIST_DIR)" --exclude ".git*" --exclude "*.in" --exclude "*~" --exclude "#*#" --exclude "ganglia-web.spec" --exclude "apache.conf" -a . $(DIST_DIR)

install:	dist-dir
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/compiled && \
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/cache && \
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR) && \
	rsync -a $(DIST_DIR)/conf $(DESTDIR)/$(GWEB_STATEDIR) && \
	mkdir -p $(DESTDIR)/$(GDESTDIR) && \
	rsync --exclude "conf" -a $(DIST_DIR)/* $(DESTDIR)/$(GDESTDIR) && \
	chown -R $(APACHE_USER):$(APACHE_GROUP) $(DESTDIR)/$(GWEB_STATEDIR)

dist-gzip:	dist-dir
	if [ -f $(DIST_TARBALL) ]; then \
	rm -rf $(DIST_TARBALL) ;\
	fi ;\
	tar -czf $(DIST_TARBALL) $(DIST_DIR)/*

rpm: dist-gzip ganglia-web.spec apache.conf
	rm -rf rpmbuild
	mkdir rpmbuild
	mkdir rpmbuild/SOURCES 
	mkdir rpmbuild/BUILD 
	mkdir rpmbuild/RPMS 
	mkdir rpmbuild/SRPMS
	cp $(DIST_TARBALL) rpmbuild/SOURCES
	cp apache.conf rpmbuild/SOURCES
	rpmbuild --define '_topdir $(PWD)/rpmbuild' --define 'custom_web_prefixdir $(GDESTDIR)' -bb ganglia-web.spec

uninstall:
	rm -rf $(DESTDIR)/$(GDESTDIR)  $(DESTDIR)/$(GWEB_STATEDIR)

