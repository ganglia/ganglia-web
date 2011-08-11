<style>
.img_view {
  float: left;
  margin: 0 0 10px 10px;
}
</style>
<style>
.flotgraph-enlarge {
  height: 500px;
  width:  800px;
}
</style>
<script language="javascript" type="text/javascript" src="js/jquery.flot.min.js"></script>
<script language="javascript" type="text/javascript" src="js/jquery.flot.crosshair.min.js"></script>
<script type="text/javascript" src="js/create-flot-graphs.js"></script>
<div id="placeholder_<?php print $_SERVER['QUERY_STRING']; ?>_legend" class="flotlegend"></div>
<div id="placeholder_<?php print $_SERVER['QUERY_STRING']; ?>" class="flotgraph-enlarge img_view"></div>
<p id="hoverdata"></p> 
