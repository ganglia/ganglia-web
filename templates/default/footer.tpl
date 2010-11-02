</div>
<div id="tabs-search">
Search by host or metric e.g. web or disk.

<!---- Uses LiveSearch from http://andreaslagerkvist.com/jquery/live-search/ ---->
<div id="metric-search">
<form method="post" action="/search/">

    <p>
        <label>
            Enter search terms<br />
            <input type="text" name="q" size=40 />
        </label> <input type="submit" value="Go" />
    </p>

</form>
</div>

</div> 


<div id="tabs-views">
  Views
</div>

<HR>
<CENTER>
<FONT SIZE="-1" class=footer>
Ganglia Web Frontend version {webfrontend-version}
<A HREF="http://ganglia.sourceforge.net/downloads.php?component=ganglia-webfrontend&amp;
version={webfrontend-version}">Check for Updates.</A><BR>

Ganglia Web Backend <i>({webbackend-component})</i> version {webbackend-version}
<A HREF="http://ganglia.sourceforge.net/downloads.php?component={webbackend-component}&amp;
version={webbackend-version}">Check for Updates.</A><BR>

Downloading and parsing ganglia's XML tree took {parsetime}.<BR>
Images created with <A HREF="http://www.rrdtool.org/">RRDtool</A> version {rrdtool-version}.<BR>
Pages generated using <A HREF="http://templatepower.codocad.com/">TemplatePower</A> version {templatepower-version}.<BR>
</FONT>
</CENTER>

</FORM>
</BODY>
</HTML>
