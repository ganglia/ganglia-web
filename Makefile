##########################################################
# User configurables:
##########################################################
# Location where gweb should be installed to (excluding conf, dwoo dirs).
GDESTDIR = /var/www/html/ganglia

# Gweb statedir (where conf dir and Dwoo templates dir are stored)
GWEB_STATEDIR = /var/lib/ganglia-web

# Gmetad rootdir (parent location of rrd folder)
GMETAD_ROOTDIR = /var/lib/ganglia

APACHE_USER =  apache
##########################################################

# Gweb version
GWEB_MAJOR_VERSION = 3
GWEB_MINOR_VERSION = 5
GWEB_MICRO_VERSION = 7

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
	sed -e "s|@vargmetadir@|$(GMETAD_ROOTDIR)|" -e "s|@vargwebstatedir@|$(GWEB_STATEDIR)|g" conf_default.php.in > conf_default.php

ganglia-web.spec:	ganglia-web.spec.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@vargwebdir@|$(GWEB_STATEDIR)|" -e "s|@varapacheuser@|$(APACHE_USER)|g" ganglia-web.spec.in > ganglia-web.spec

version.php:	version.php.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php

dist-dir:	default
	rsync --exclude "rpmbuild" --exclude "*.gz" --exclude "Makefile" --exclude "*debian*" --exclude "$(DIST_DIR)" --exclude ".git*" --exclude "*.in" --exclude "*~" --exclude "#*#" --exclude "ganglia-web.spec" -a . $(DIST_DIR)

install:	dist-dir
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/compiled && \
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/cache && \
	rsync -a $(DIST_DIR)/conf $(DESTDIR)/$(GWEB_STATEDIR) && \
	rsync --exclude "conf" -a $(DIST_DIR)/* $(DESTDIR)/$(GDESTDIR) && \
	chown -R $(APACHE_USER):$(APACHE_USER) $(DESTDIR)/$(GWEB_STATEDIR)

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
	rm -rf $(DESTDIR)/$(GDESTDIR)  $(DESTDIR)/$(GWEB_STATEDIR)

