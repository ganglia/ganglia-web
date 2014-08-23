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

#APACHE_USER = www-data
#APACHE_GROUP = www-data
##########################################################

ifndef APACHE_USER
$(info APACHE_USER is not set using www-data)
APACHE_USER = www-data
endif

ifndef APACHE_GROUP
$(info APACHE_GROUP is not set, using $(APACHE_USER))
APACHE_GROUP = $(APACHE_USER)
endif

# Gweb version
GWEB_VERSION = 3.7.0

DIST_NAME = ganglia-web
DIST_DIR = $(DIST_NAME)-$(GWEB_VERSION)
DIST_TARBALL = $(DIST_DIR).tar.gz

# TARGETS: Files that needs to be patched with user chosen variables
TARGETS = conf_default.php conf_redirect.php apache.conf

# DIST_GZIP_TARGETS targets that needs to be patched once before dist-gzip
# basicaly version and default values.
DIST_GZIP_TARGETS = ganglia-web.spec version.php

all: default

default:	$(TARGETS)

clean:
	@echo -n "Cleaning build files ............................... "
	@rm -rf $(TARGETS) $(DIST_DIR) $(DIST_TARBALL) rpmbuild
	@echo "DONE."

conf_default.php:	conf_default.php.in
	@echo -n "Generating conf_default.php ........................ "
	@sed -e "s|@vargmetadir@|$(GMETAD_ROOTDIR)|" -e "s|@vargwebdir@|$(GWEB_STATEDIR)|g" conf_default.php.in > conf_default.php
	@echo "DONE."

conf_redirect.php:	conf_redirect.php.in
	@echo -n "Generating conf_redirect.php ....................... "
	@sed -e "s|@etcdir@|$(GCONFDIR)|" conf_redirect.php.in > conf_redirect.php
	@echo "DONE."

ganglia-web.spec:	ganglia-web.spec.in
	@echo -n "Generating ganglia-web.spec ........................ "
	@sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@vargwebdir@|$(GWEB_STATEDIR)|" -e "s|@GDESTDIR@|$(GDESTDIR)|g" -e "s|@etcdir@|$(GCONFDIR)|g" ganglia-web.spec.in > ganglia-web.spec
	@echo "DONE."

version.php:	version.php.in
	@echo -n "Generating version.php ............................. "
	@sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php
	@echo "DONE."

apache.conf:	apache.conf.in
	@echo -n "Generating apache.conf ............................. "
	@sed -e "s|@GDESTDIR@|$(GDESTDIR)|g" apache.conf.in > apache.conf
	@echo "DONE."

dist-dir:	default
	@echo -n "Filling dist dir ................................... "
	@rsync	--exclude "rpmbuild" \
		--exclude "debian/ganglia-webfrontend" \
		--exclude "*.gz" \
		--exclude "$(DIST_DIR)" \
		--exclude ".git*" \
		--exclude "version.php.in" \
		--exclude "ganglia-web.spec.in" \
		--exclude "apache.conf" \
		--exclude "conf_default.php" \
		--exclude "*~" \
		--exclude "#*#" \
		-a . $(DIST_DIR)
	@echo "DONE."

install:	install-files
	@echo -n "Setting ownership to the sharedstattedir files ..... "
	@chown -R $(APACHE_USER):$(APACHE_GROUP) $(DESTDIR)/$(GWEB_STATEDIR)
	@echo "DONE."


install-files:	dist-dir
	@echo -n "Creating dwoo sharedstattedir tree ................. "
	@mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/compiled
	@mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/cache
	@mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/filters
	@echo "DONE."
	@echo -n "Installing ganglia-webfrontend low level conf ...... "
	@rsync -a $(DIST_DIR)/conf $(DESTDIR)/$(GWEB_STATEDIR)
	@echo "DONE."
	@echo -n "Installing php files ............................... "
	@mkdir -p $(DESTDIR)/$(GDESTDIR)
	@rsync 	--exclude "conf" \
		--exclude conf_redirect.php \
		--exclude "*.in" \
		--exclude "*.spec" \
		-a $(DIST_DIR)/* $(DESTDIR)/$(GDESTDIR)
	@echo "DONE."
	@echo -n "Intalling redirect conf.php ........................ "
	@# so it can be seen as a config file under debian packages.
	@# Do the same for all distro to avoid specific case for debian.
	@cp -f conf_redirect.php $(DESTDIR)/$(GDESTDIR)/conf.php
	@echo "DONE."
	@echo -n "Installing the generated conf_default.php file ..... "
	@cp -f conf_default.php $(DESTDIR)/$(GDESTDIR)/conf_default.php
	@echo "DONE."
	@echo -n "Creating the etc/ganglia-webfrontend directory ..... "
	@mkdir -p $(DESTDIR)/$(GCONFDIR)
	@echo "DONE."
	@echo -n "Installing apache.conf ............................. "
	@cp -f apache.conf $(DESTDIR)/$(GCONFDIR)/
	@echo "DONE."
	@echo -n "Installing the editable copy of conf_default.php ... "
	@cp -f conf_default.php $(DESTDIR)/$(GCONFDIR)/conf.php
	@echo "DONE."

dist-gzip:	dist-dir $(DIST_GZIP_TARGETS)
	@echo -n "Creating tarball ................................... "
	@if [ -f $(DIST_TARBALL) ]; then \
		rm -rf $(DIST_TARBALL) ;\
	fi
	@cp -pf $(DIST_GZIP_TARGETS) $(DIST_DIR)
	@tar -czf $(DIST_TARBALL) $(DIST_DIR)/*
	@echo "DONE."

rpm: dist-gzip ganglia-web.spec apache.conf
	@echo -n "Creating binary rpm ................................ "
	@rm -rf rpmbuild
	@mkdir rpmbuild
	@mkdir rpmbuild/SOURCES 
	@mkdir rpmbuild/BUILD 
	@mkdir rpmbuild/RPMS 
	@mkdir rpmbuild/SRPMS
	@ln -s ../../$(DIST_TARBALL) rpmbuild/SOURCES/$(DIST_TARBALL)
	@rpmbuild --define '_topdir $(PWD)/rpmbuild' --define 'web_prefixdir $(GDESTDIR)' -bb ganglia-web.spec
	@echo "DONE."

uninstall:
	@echo -n "Uninstalling ganglia-web. (conf files untouched) ... "
	rm -rf $(DESTDIR)/$(GDESTDIR)  $(DESTDIR)/$(GWEB_STATEDIR)
	@echo "DONE."

