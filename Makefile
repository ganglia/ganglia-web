# Location where gweb should be installed to
GDESTDIR = /var/www/html/ganglia

APACHE_USER = apache

# Gweb version
GWEB_MAJOR_VERSION = 3
GWEB_MINOR_VERSION = 5
GWEB_MICRO_VERSION = 3

# Gweb statedir (where RRD files, Dwoo templates are stored)
GWEB_STATEDIR = /var/lib
GANGLIA_STATEDIR = $(GWEB_STATEDIR)/ganglia

# Dwoo compile directory
GWEB_DWOO = $(GANGLIA_STATEDIR)/dwoo

GWEB_VERSION = $(GWEB_MAJOR_VERSION).$(GWEB_MINOR_VERSION).$(GWEB_MICRO_VERSION)

DIST_NAME = ganglia-web
DIST_DIR = $(DIST_NAME)-$(GWEB_VERSION)
DIST_TARBALL = $(DIST_DIR).tar.gz

TARGETS = conf_default.php ganglia-web.spec version.php

all: default

default:	$(TARGETS)

clean:
	rm -rf $(TARGETS) $(DIST_DIR) $(DIST_TARBALL) rpmbuild

conf_default.php:	conf_default.php.in
	sed -e "s|@varstatedir@|$(GWEB_STATEDIR)|" conf_default.php.in > conf_default.php

ganglia-web.spec:	ganglia-web.spec.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@varstatedir@|$(GWEB_STATEDIR)|" -e "s|@varapacheuser@|$(APACHE_USER)|g" ganglia-web.spec.in > ganglia-web.spec

version.php:	version.php.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php

dist-dir:	default
	rsync --exclude "rpmbuild" --exclude "*.gz" --exclude "Makefile" --exclude "*debian*" --exclude "$(DIST_DIR)" --exclude ".git*" --exclude "*.in" --exclude "*~" --exclude "#*#" --exclude "ganglia-web.spec" -a . $(DIST_DIR)

install:	dist-dir
	mkdir -p $(DESTDIR)/$(GWEB_DWOO)/compiled && \
	mkdir -p $(DESTDIR)/$(GWEB_DWOO)/cache && \
	mkdir -p $(DESTDIR)/$(GANGLIA_STATEDIR) && \
	rsync -a $(DIST_DIR)/conf/ $(DESTDIR)/$(GANGLIA_STATEDIR)/conf && \
	rsync --exclude "conf" -a $(DIST_DIR)/* $(DESTDIR)/$(GDESTDIR) && \
	chown -R $(APACHE_USER):$(APACHE_USER) $(DESTDIR)/$(GWEB_DWOO) $(DESTDIR)/$(GANGLIA_STATEDIR)/conf	

dist-gzip:	dist-dir
	if [ -f $(DIST_TARBALL) ]; then \
	rm -rf $(DIST_TARBALL) ;\
	fi ;\
	tar -czf $(DIST_TARBALL) $(DIST_DIR)/*

rpm: dist-gzip ganglia-web.spec
	rm -rf rpmbuild
	mkdir rpmbuild
	mkdir rpmbuild/SOURCES 
	mkdir rpmbuild/BUILD 
	mkdir rpmbuild/RPMS 
	mkdir rpmbuild/SRPMS
	cp $(DIST_TARBALL) rpmbuild/SOURCES
	rpmbuild --define '_topdir $(PWD)/rpmbuild' -bb ganglia-web.spec

uninstall:
	rm -rf $(GDESTDIR) $(GWEB_DWOO) $(GANGLIA_STATEDIR)/conf

