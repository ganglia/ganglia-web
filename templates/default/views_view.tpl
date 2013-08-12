<style type="text/css">
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>

<script type="text/javascript">
  function refreshView() {
    $("#view_graphs img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
	  var d = new Date();
	  $(this).attr("src", 
                       jQuery.param.querystring(src, "&_=" + d.getTime()));
	}    
    });
  }

  function viewId(view_name) {
    return "v_" + view_name.replace(/[^a-zA-Z0-9_]/g, "_");
  }

  function createView() {
    $("#create-new-view-confirmation-layer").html('<img src="img/spinner.gif">');
    $.get('views_view.php', 
          $("#create_view_form").serialize() , 
          function(data) {
      $("#create-new-view-layer").toggle();
      $("#create-new-view-confirmation-layer").html(data.output);
      if ("tree_node" in data) {
 	$('#views_menu').jstree('create',
				'#root',
				'last',
				data.tree_node,
				null,
				true);
     }
   }, "json");
   return false;
  }

  function selectView(view_name) {
    $.cookie('ganglia-selected-view-' + window.name, view_name);
    $("#vn").val(view_name);
    {if !$display_views_using_tree}
    $.get("views_view.php?vn=" + view_name + "&views_menu",
          function(data) {
            $("#views_menu").html(data);
          }); 
    {/if}
    var qs = jQuery.deparam.querystring();
    $.get("view_content.php?vn=" + view_name + 
  	  "&r=" + qs.r + 
	  "&cs=" + $("#datepicker-cs").val() + 
	  "&ce=" + $("#datepicker-ce").val(),
	  function(data) {
	    $("#views-content").html(data);
	    initShowEvent();
	  });
    $("#page_title").text('"' + view_name.replace(/--/g, " / ") + '"');
    refreshHeader();
  }

  function newViewDialogCloseCallback() {
  {if !$display_views_using_tree}
    $.get('views_view.php?views_menu=1',
	  function(data) {
	    $("#views_menu").html(data);
          });
  {/if}
  }

  $(function() {
    $( "#popup-dialog" ).dialog({ autoOpen: false, width:850 });
    $("#create_view_button")
      .button()
      .click(function() {
	$( "#create-new-view-dialog" ).dialog( "open" );
    });
    $("#delete_view_button")
      .button()
      .click(function() {
        if ($("#vn").val() != "") {
	  if (confirm("Are you sure you want to delete the view: " + $("#vn").val() + " ?")) {
	    $.get('views_view.php?vn=' + 
                  encodeURIComponent($("#vn").val()) +
                  '&delete_view&views_menu',
                  function(data) {
                    {if $display_views_using_tree}
                      $('#views_menu').jstree("remove", null);
                    {else}
                      $("#views_menu").html(data);
		      $("#view_graphs").html("");  
                      $.cookie('ganglia-selected-view-' + window.name, "");
		      $("#vn").val("");
                    {/if}
                  });
          }
        } else
	  alert("Please select the view to delete");
    });
    {if $display_views_using_tree}
    $('#views_menu').jstree({
      "json_data" : {
         "data" : {$existing_views}
      },
      'core': { animation: 0 },
      'plugins': ['themes', 'json_data', 'ui', 'cookies', 'crrm'],
      themes: { 
        theme: 'default', dots: true, icons: false}})
    .bind("select_node.jstree", 
          function (event, data) {
            selectView(data.rslt.obj.attr("view_name"));
            return false;
          })
    .bind("before.jstree", 
          function (e, data) {
            if (data.func === "select_node" &&
                !data.inst.is_leaf(data.args[0])) {
	      data.inst.toggle_node(data.args[0]);
	      e.stopImmediatePropagation();
	      return false;
            }
          });
    {/if}
  });
</script>

<div id="popup-dialog" title="Inspect Graph">
  <div id="popup-dialog-content">
  </div>
</div>

<table id="views_table">
<tr><td valign="top">
<div id="views_menu" {if $ad_hoc_view} style="visibility: hidden; display: none;" {/if}>
  {if !$display_views_using_tree}
    {$existing_views}
  {/if}
</div>
{if !$display_views_using_tree}
<script type="text/javascript">
  $(function() { $("#views_menu").buttonsetv(); });
</script>
{/if}
</td>
<td valign="top">
<div>
  {include('view_content.tpl')}
<div style="clear: left"></div>
</div>
</td>
</tr>
</table>
{if $ad_hoc_view}
<input type="hidden" id="ad-hoc-view" name="ad-hoc-view" value="{$ad_hoc_view_json}">
{/if}
