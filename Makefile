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
$(warning APACHE_USER is not set using www-data)
APACHE_USER = www-data
endif

ifndef APACHE_GROUP
$(warning APACHE_GROUP is not set, using $(APACHE_USER))
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
	rm -rf $(TARGETS) $(DIST_DIR) $(DIST_TARBALL) rpmbuild

conf_default.php:	conf_default.php.in
	sed -e "s|@vargmetadir@|$(GMETAD_ROOTDIR)|" -e "s|@vargwebdir@|$(GWEB_STATEDIR)|g" conf_default.php.in > conf_default.php

conf_redirect.php:	conf_redirect.php.in
	sed -e "s|@etcdir@|$(GCONFDIR)|" conf_redirect.php.in > conf_redirect.php

ganglia-web.spec:	ganglia-web.spec.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ -e "s|@vargwebdir@|$(GWEB_STATEDIR)|" -e "s|@GDESTDIR@|$(GDESTDIR)|g" -e "s|@etcdir@|$(GCONFDIR)|g" ganglia-web.spec.in > ganglia-web.spec

version.php:	version.php.in
	sed -e s/@GWEB_VERSION@/$(GWEB_VERSION)/ version.php.in > version.php

apache.conf:	apache.conf.in
	sed -e "s|@GDESTDIR@|$(GDESTDIR)|g" apache.conf.in > apache.conf

dist-dir:	default
	rsync	--exclude "rpmbuild" \
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

install:	dist-dir
	# Create dwoo sharedstattedir tree
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/compiled
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/dwoo/cache
	mkdir -p $(DESTDIR)/$(GWEB_STATEDIR)/filters
	# Install ganglia-webfrontend low level conf
	rsync -a $(DIST_DIR)/conf $(DESTDIR)/$(GWEB_STATEDIR)
	# Set apache daemon ownership to the sharedstattedir files.
	chown -R $(APACHE_USER):$(APACHE_GROUP) $(DESTDIR)/$(GWEB_STATEDIR)
	# Install php files
	mkdir -p $(DESTDIR)/$(GDESTDIR)
	rsync 	--exclude "conf" \
		--exclude conf_redirect.php \
		--exclude "*.in" \
		--exclude "*.spec" \
		-a $(DIST_DIR)/* $(DESTDIR)/$(GDESTDIR)
	# Intall file that redirect conf.php to /etc/ganglia-webfrontend
	# so it can be seen as a config file under debian packages.
	# Do the same for all distro to avoid specific case for debian.
	cp -f conf_redirect.php $(DESTDIR)/$(GDESTDIR)/conf.php
	# Install the generated conf_default.php file.
	cp -f conf_default.php $(DESTDIR)/$(GDESTDIR)/conf_default.php
	# Create the /etc/ganglia-webfrontend directory.
	mkdir -p $(DESTDIR)/$(GCONFDIR)
	cp -f apache.conf $(DESTDIR)/$(GCONFDIR)/
	# Install the editable copy of conf_default.php
	cp -f conf_default.php $(DESTDIR)/$(GCONFDIR)/conf.php

dist-gzip:	dist-dir $(DIST_GZIP_TARGETS)
	if [ -f $(DIST_TARBALL) ]; then \
	rm -rf $(DIST_TARBALL) ;\
	fi ;\
	cp -pf $(DIST_GZIP_TARGETS) $(DIST_DIR)
	tar -czf $(DIST_TARBALL) $(DIST_DIR)/*

rpm: dist-gzip ganglia-web.spec apache.conf
	rm -rf rpmbuild
	mkdir rpmbuild
	mkdir rpmbuild/SOURCES 
	mkdir rpmbuild/BUILD 
	mkdir rpmbuild/RPMS 
	mkdir rpmbuild/SRPMS
	ln -s $(DIST_TARBALL) rpmbuild/SOURCES
	rpmbuild --define '_topdir $(PWD)/rpmbuild' --with web_prefixdir=$(GDESTDIR) -bb ganglia-web.spec

uninstall:
	rm -rf $(DESTDIR)/$(GDESTDIR)  $(DESTDIR)/$(GWEB_STATEDIR)

