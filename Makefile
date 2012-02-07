# Location where gweb should be installed to
DESTDIR = /var/www/html/gweb

APACHE_USER = apache

# Gweb version
GWEB_MAJOR_VERSION = 3
GWEB_MINOR_VERSION = 3
GWEB_MICRO_VERSION = 1

# Gweb statedir (where RRD files, Dwoo templates are stored)
GWEB_STATEDIR = /var/lib
GANGLIA_STATEDIR = $(GWEB_STATEDIR)/ganglia

# Dwoo compile directory
GWEB_DWOO = $(GANGLIA_STATEDIR)/dwoo

GWEB_VERSION = $(GWEB_MAJOR_VERSION).$(GWEB_MINOR_VERSION).$(GWEB_MICRO_VERSION)

DIST_DIR = gweb-$(GWEB_VERSION)
DIST_TARBALL = $(DIST_DIR).tar.gz

TARGETS = conf_default.php gweb.spec version.php

all: default

default:	$(TARGETS)

clean:
	rm -rf $(TARGETS) $(DIST_DIR) $(DIST_TARBALL) rpmbuild

conf_default.php:	conf_default.php.in
	sed -e "s|@varstatedir@|$(GWEB_STATEDIR)|" conf_default.php.in > conf_default.php

gweb.spec:	gweb.spec.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@varstatedir@|$(GWEB_STATEDIR)|" -e "s|@varapacheuser@|$(APACHE_USER)|g" gweb.spec.in > gweb.spec

version.php:	version.php.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php

dist-dir:	default
	rsync --exclude "rpmbuild" --exclude "*.gz" --exclude "Makefile" --exclude "$(DIST_DIR)" --exclude ".git*" --exclude "*.in" --exclude "*~" --exclude "#*#" --exclude "gweb.spec" -a . $(DIST_DIR)

install:	dist-dir
	mkdir -p $(GWEB_DWOO)/compiled && \
	mkdir -p $(GWEB_DWOO)/cache && \
	rsync -a $(DIST_DIR)/conf/ $(GANGLIA_STATEDIR)/conf && \
	rsync --exclude "conf" -a $(DIST_DIR)/* $(DESTDIR) && \
	chown -R $(APACHE_USER):$(APACHE_USER) $(GWEB_DWOO) $(GANGLIA_STATEDIR)/conf	

dist-gzip:	dist-dir
	if [ -f $(DIST_TARBALL) ]; then \
	rm -rf $(DIST_TARBALL) ;\
	fi ;\
	tar -czf $(DIST_TARBALL) $(DIST_DIR)/*

rpm: dist-gzip gweb.spec
	rm -rf rpmbuild
	mkdir rpmbuild
	mkdir rpmbuild/SOURCES 
	mkdir rpmbuild/BUILD 
	mkdir rpmbuild/RPMS 
	mkdir rpmbuild/SRPMS
	cp $(DIST_TARBALL) rpmbuild/SOURCES
	rpmbuild --define '_topdir $(PWD)/rpmbuild' -bb gweb.spec

uninstall:
	rm -rf $(DESTDIR) $(GWEB_DWOO) $(GANGLIA_STATEDIR)/conf

