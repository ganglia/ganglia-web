<script type="text/javascript">
  var viewCommonYaxis = false;
  var yAxisUpperLimit = null;
  var yAxisLowerLimit = null;

  function getLimit(data, min_or_max) {
    var start = data.indexOf("value_" + min_or_max + " = ");
    if (start != -1) {
      var end = data.indexOf("\n", start);
      if (end != -1)
	return (new Number(data.substring(start + 12, end))).valueOf();
    }
    return null;
  }

  function computeYAxisLimits() {
    var oldUl = yAxisUpperLimit;
    yAxisUpperLimit = null;

    var oldLl = yAxisLowerLimit;
    yAxisLowerLimit = null;

    $("#view_graphs img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
          var qs = jQuery.deparam(jQuery.param.querystring(src));
	  delete qs.x; // Remove upper limit
	  delete qs.n; // Remove lower limit
          qs.verbose = '';
          qs["_"] = (new Date()).getTime();
   	  src = jQuery.param.querystring(src, qs, 2);

          jQuery.ajax({
            url: src,
            success: function(data) {
              var ul = getLimit(data, "max");
              if (ul != null) {
                if (yAxisUpperLimit == null) {
                  yAxisUpperLimit = ul;
                } else {
                  if (ul > yAxisUpperLimit)
                    yAxisUpperLimit = ul;
                }
              }

	      var ll = getLimit(data, "min");
              if (ll != null) {
                if (yAxisLowerLimit == null) {
                  yAxisLowerLimit = ul;
                } else {
                  if (ll < yAxisLowerLimit)
                    yAxisLowerLimit = ll;
                }
              }
            },
            async: false
          });
	}
    });

    var upperLimitChanged = true;
    if ((oldUl != null) && (yAxisUpperLimit != null)) {
      if (yAxisUpperLimit <= oldUl) {
        yAxisUpperLimit = oldUl;
        upperLimitChanged = false;
      }
    }

    var lowerLimitChanged = true;
    if ((oldLl != null) && (yAxisLowerLimit != null)) {
      if (yAxisLowerLimit >= oldLl) {
        yAxisLowerLimit = oldLl;
        lowerLimitChanged = false;
      }
    }

    return lowerLimitChanged || upperLimitChanged;
  }

  function refreshView() {
    if (viewCommonYaxis) {
      var limitsChanged = computeYAxisLimits();
    }

    $("#view_graphs img").each(function (index) {
	var src = $(this).attr("src");
	if (src.indexOf("graph.php") == 0) {
	  var d = new Date();
          if (viewCommonYaxis &&
              yAxisUpperLimit != null &&
              yAxisLowerLimit != null &&
              limitsChanged)
	    $(this).attr("src",
                         jQuery.param.querystring(
                           src,
                           "&x=" + encodeURIComponent(yAxisUpperLimit) +
                           "&n=" + encodeURIComponent(yAxisLowerLimit) +
                           "&_=" + d.getTime()));
          else
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
 	var tree = $('#views_menu').jstree(true);
        tree.create_node('#',
			 data.tree_node,
			 'last',
		         null,
			 true);
     }
   }, "json");
   return false;
  }

 function selectView(view_name) {
   $("#vn").val(view_name);
   var qs = jQuery.deparam.querystring();
   $.get("view_content.php?vn=" + view_name +
  	 "&r=" + qs.r +
	 "&cs=" + $("#datepicker-cs").val() +
	 "&ce=" + $("#datepicker-ce").val(),
	 function(data) {
	   $("#views-content").html(data);
	   initShowEvent();
	   initCustomTimeRangeDragSelect($("#views-content"));
  	   if (viewCommonYaxis)
	     refreshView();
	 });
   $("#page_title").text('"' + view_name.replace(/--/g, " / ") + '"');
   refreshHeader();
  }

 function initViewsMenu() {
   {if !$display_views_using_tree}
   $("#views_menu").controlgroup({ direction: "vertical" });
   $("#views_menu").find("input:radio").each(function() {
     $(this).checkboxradio({ icon: false }).checkboxradio('refresh');
   });
   // Highlight the currently selected view
   var view_name = $("#vn").val();
   if (view_name != "") {
     $('#' + viewId(view_name)).prop('checked', true).checkboxradio('refresh');
   }
   {/if}
 }

  function newViewDialogCloseCallback() {
    {if !$display_views_using_tree}
    $.get('views_view.php?views_menu=1',
	  function(data) {
	    $("#views_menu").html(data);
            initViewsMenu();
          });
    {/if}
  }

  $(function() {
    $("#create_view_button")
      .button()
      .click(function() {
	$("#create-new-view-dialog").dialog("open");
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
 	              var tree = $('#views_menu').jstree(true);
                      var sel = tree.get_selected();
                      if (sel.length)
                        tree.delete_node(sel);
                      else
                        alert("Please select the view to delete");
                    {else}
                      $("#views_menu").html(data);
                      initViewsMenu();
		      $("#view_graphs").html("");
		      $("#vn").val("");
                    {/if}
                  });
          }
        } else
	  alert("Please select the view to delete");
    });
    {if $display_views_using_tree}
    $('#views_menu').jstree({
      'core' : {
         'data' : {$existing_views},
         'multiple' : false,
         'animation' : 0,
	 'check_callback' : true,
         'themes' : { 'icons' : true, 'dots' : true, 'stripes' : false }
      },
      'state' : {
        'filter' : function(n) { delete n.core.selected; return n; },
        'key' : 'view-tree-' + window.name
      },
      'plugins' : ['state', 'sort', 'unique']
    })
    .on("select_node.jstree",
          function (event, data) {
	    if (data.instance.is_leaf(data.node)) {
              selectView(data.node.original.view_name);
            } else {
	      data.instance.toggle_node(data.node);
	      e.stopImmediatePropagation();
            }
            return false;
          })
    .on("before.jstree",
          function (e, data) {
            if (data.func === "select_node" &&
                !data.inst.is_leaf(data.args[0])) {
	      data.inst.toggle_node(data.args[0]);
	      e.stopImmediatePropagation();
	      return false;
            }
          });
    // Check for a selected view
    var tree = $('#views_menu').jstree(true);
    tree.select_node(viewId("{$view_name}"), true, false);
    {else}
    initViewsMenu();
    {/if}
  });
</script>

<table id="views_table">
<tr><td valign="top" {if $display_views_using_tree} style="padding:5px;" {/if}>
<div id="views_menu" {if $display_views_using_tree} style="background-color:white" {/if} {if $ad_hoc_view} style="visibility: hidden; display: none;" {/if}>
  {if !$display_views_using_tree}
    {$existing_views}
  {/if}
</div>
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
