<!-- Begin footer.tpl -->
</form> <!-- ganglia-form -->
</div> <!-- tabs-main -->

<div id="tabs-search">
  Search term matches any number of metrics and hosts. For example type web or disk; wait a split second, and a drop down menu will show up with choices.
  <!-- Uses LiveSearch from http://andreaslagerkvist.com/jquery/live-search/ -->
  <div id="metric-search">
    <form method="post" action="/search/" onsubmit="return false;">
      <p>
	<label>
	    <small>Search as you type</small><br />
	    <input type="text" name="q" id="search-field-q" size="60" placeholder="Search as you type" on />
	</label>
      </p>
    </form>
  </div>
</div>

<div id="create-new-view-dialog" title="Create new view">
  <div id="create-new-view-layer">
    <form id="create_view_form">
      <input type="hidden" name="create_view" value="1">
      <fieldset>
	 <label for="new_view_name">View Name</label>
	 <input type="text" name="vn" id="new_view_name" class="text ui-widget-content ui-corner-all" />
         <center><button style="margin:10px;" onclick="createView(); return false;">Create</button></center>
      </fieldset>
    </form>
  </div>
  <div id="create-new-view-confirmation-layer"></div>
</div>

<script type="text/javascript">
$(function(){
  var create_new_view_dialog = $("#create-new-view-dialog");
  if (create_new_view_dialog[0])
    create_new_view_dialog.dialog({
      autoOpen: false,
      height: "auto",
      width: "auto",
      modal: true,
      position: { my: "top",
                  at: "top+200",
                  of: window },
      close: function() {
        $("#create-new-view-layer").toggle();
        $("#create-new-view-confirmation-layer").html("");
	newViewDialogCloseCallback();
      }
    });
});
</script>

<div id="metric-actions-dialog" title="Metric Actions">
  <div id="metric-actions-dialog-content">
	Available Metric actions.
  </div>
</div>

<script type="text/javascript">
$(function(){
  initMetricActionsDialog();
});
</script>

<div id="tabs-mobile"></div>

<div id="tabs-autorotation">
Invoke automatic rotation system. Automatic rotation rotates all of the graphs/metrics specified in a view waiting
30 seconds in between each. This will run as long as you have this page open.
<p>
Please select the view you want to rotate.</p>
  <div id="tabs-autorotation-chooser">
Loading view, please wait...<img src="img/spinner.gif" />
  </div>
</div>

<div id="tabs-livedashboard">
Live dashboard provides you with an overview of all view metrics in a compact format. Data updates every 15 seconds.
Only those elements that contain a metric or graph report are supported. Aggregate graphs will not be included.
<p>
You can get more graphs per page by using your browser zoom functionality.
<p>
Please select the view you want to view</p>
  <div id="tabs-livedashboard-chooser">
Loading view, please wait...<img src="img/spinner.gif" />
  </div>
</div>


<div align="center" class="footer" style="font-size:small;clear:both;" {if $hide_footer} style="visibility:hidden;display:none;" {/if}>
<hr />
Ganglia Web Frontend version {$webfrontend_version}
<a href="http://ganglia.sourceforge.net/downloads.php?component=ganglia-webfrontend&amp;version={$webfrontend_version}">Check for Updates.</a><br />

Ganglia Web Backend <i>({$webbackend_component})</i> version {$webbackend_version}
<a href="http://ganglia.sourceforge.net/downloads.php?component={$webbackend_component}&amp;version={$webbackend_version}">Check for Updates.</a><br />

Downloading and parsing ganglia's XML tree took {$parsetime}.<br />
Images created with <a href="http://www.rrdtool.org/">RRDtool</a> version {$rrdtool_version}.<br />
{$dwoo.ad} {$dwoo.version}.<br />
</div>
</div> <!-- div-tabs -->
<div id="popup-dialog" title="Inspect Graph">
  <div id="popup-dialog-navigation"></div>
  <div id="popup-dialog-content">
  </div>
</div>

<script type="text/javascript">
$(function(){
  $("#popup-dialog").dialog({ autoOpen: false,
                              width:800,
                              height:500,
                              position: { my: "top",
                                          at: "top+200",
                                          of: window } } );
});
</script>
</body>
<!-- End footer.tpl -->
</html>
