About
-----

This is a puppet recipe that will install Graphite <http://graphite.wikidot.org> and Ganglia on the same
machine. 

Running
-------

To run it install puppet from <http://www.puppetlabs.com> then run it against the graphite recipe ie.

puppet graphite.pp

This will invoke puppet in standalone mode. You can run it even if you otherwise uses puppet on this machine.

Manual steps
------------

Apache Config
-------------

Manual steps that need to be run after puppet finishes are copy the Graphite Virtual Host example config
to Apache configuration directory ie. on Ubuntu you could do

   cp /tmp/graphite-web-0.9.6/examples/example-graphite-vhost.conf /etc/apache2/sites-enabled/

or on Centos

   cp /tmp/graphite-web-0.9.6/examples/example-graphite-vhost.conf /etc/httpd/conf.d/

You also need to add following lines to it instructing Apache not to use mod_python
for Ganglia web UI e.g.

        Alias /ganglia /var/www/html/ganglia
        <Location "/ganglia/">
                SetHandler None
        </Location>

Restart Apache when you are done.


Django init
-----------

Graphite uses Django and you need to initialize it. Go to

   cd /opt/graphite/webapp/graphite 

then type

   Ubuntu: sudo -u www-data python manage.py syncdb
   CentOS: sudo -u apache python manage.py syncdb

Follow the directions. You will be asked to create Graphite admin user.


Ganglia Web UI
--------------

You will need to copy contents of the modified Ganglia web UI from here

http://github.com/vvuksan/ganglia-misc/tree/master/ganglia-web/

Copy it to e.g. /var/www/html/ganglia. To enable Graphite in conf.php you will need to change

  $use_graphite = "no";

to 

  $use_graphite = "yes";

