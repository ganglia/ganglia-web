<?php
/* reformat_conf.php
 * Copyright 2011, Alex Dean <alexATcrackpotDOTorg>
 * 
 * Use this script to convert a Ganglia PHP file to use a $conf array instead of top-level variables.
 * Example: $template_name => $conf['template_name']
 *
 * Resulting file will be checked for syntax errors, and to ensure that
 * all required configuration values are defined.
 *
 * Usage: 'php reformat_conf.php -i input-conf.php -o output-conf.php'
 */

$required_conf_vars = array(
  'template_name',
  'gmetad_root',
  'rrds',
  'dwoo_compiled_dir',
  'rrdcached_socket',
  'graphdir',
  'graphreport_stats',
  'ganglia_ip',
  'ganglia_port',
  'max_graphs',
  'hostcols',
  'metriccols',
  'show_meta_snapshot',
  'default_refresh',
  'cpu_user_color',
  'cpu_nice_color',
  'cpu_system_color',
  'cpu_wio_color',
  'cpu_idle_color',
  'mem_used_color',
  'mem_shared_color',
  'mem_cached_color',
  'mem_buffered_color',
  'mem_free_color',
  'mem_swapped_color',
  'load_one_color',
  'proc_run_color',
  'cpu_num_color',
  'num_nodes_color',
  'show_cores',
  'jobstart_color',
  'load_colors',
  'load_scale',
  'default_metric_color',
  'default_metric',
  'strip_domainname',
  'time_ranges',
  'default_time_range',
  'graphite_url_base',
  'graphite_rrd_dir',
  'graph_sizes_keys',
  'graph_sizes',
  'default_graph_size',
  'case_sensitive_hostnames'
);
$optional_conf_vars = array(
  'optional_graphs',
  'filter_dir'
);

function reformat_conf_vars( $string, $conf_vars, $depth=0 ) {
  $output = "";
  $tokens = token_get_all($string);
  $in_dbl_quotes = false;
  $line_number = 1;
  
  try {
    foreach($tokens as $value) {
      if(count($value)==3) {
        $token = $value[1];
        $token_name = token_name($value[0]);
        $line_number = $value[2];
      } else {
        if( $value=='"' ) {
          $in_dbl_quotes = !$in_dbl_quotes;
        }
        $token = $value;
        $token_name = false;
      }
    
      $var_name = substr($token,1);
      if( $token_name=='T_VARIABLE' && in_array( $var_name, $conf_vars) ) {
        if( $in_dbl_quotes ) {
          $output .= '${conf[\''.$var_name.'\']}';
        } else {
          $output .= '$conf[\''.$var_name.'\']';
        }
      // some config values may be commented out.  so we parse comments also.
      } else if( $token_name == 'T_COMMENT' ) {
        if( substr($token,0,1)=='#' ) { 
          $initial='#';
        } else if( substr($token,0,2)=='//' ) {
          $initial='//';
        } else if( substr($token,0,2)=='/*' ) {
          $initial='/*';
        }
        $subject = substr($token,strlen($initial));
        // tokenizer won't parse a string w/o php's open/close tags.
        $subject = "<?php ".$subject." ?>";
        $subject = reformat_conf_vars( $subject, $conf_vars, $depth+1 );
        $subject = str_replace( array('<?php ',' ?>'), '', $subject);
        $subject = $initial . $subject;
        $output .= $subject;
      } else if( in_array( $token_name, array('T_STRING_VARNAME','T_CURLY_OPEN','T_DOLLAR_OPEN_CURLY_BRACES') ) ) {
        // Not worth the effort to parse complex variable syntax.  Just bail and tell user to write something simpler.
        throw new Exception( "Config file uses '$token'.\nComplex variable syntax cannot be converted automatically.\nPlease reformat your config file and try again.\n", E_USER_ERROR );
      } else {
        $output .= $token;
      }
    }
  } catch( Exception $e ) {
    if( $depth > 0 ) {
      throw $e;
    } else {
      // Only trigger an error once we're at the top of the stack, so the line number can be reported correctly.
      trigger_error( "Near line $line_number of source file.\n".$e->getMessage(), E_USER_ERROR );
    }
  }
  return $output;
}

function usage() {
  return "This script will output a version of your conf.php using the \$conf array.\n" .
         "Example usage: 'php ${argv[0]} -i conf.php -o conf.php-converted'\n" .
         " -i : Input file\n" .
         " -o : Output file\n" .
         " -f : Force.  Overwrite output file if it already exists.\n\n";
}

$options = getopt( "i:o:f" );
if( !isSet( $options['i'] ) ) {
  echo usage();
  echo "Missing -i (input file) option.\n";
  exit;
}
if( !file_exists( $options['i'] ) ) {
  echo usage();
  echo "Input file '${options['i']}' does not exist.\n";
  exit;
}
if( !isSet( $options['o'] ) ) {
  echo usage();
  echo "Missing -o (output file) option.\n";
  exit;
}
if( file_exists( $options['o'] ) ) {
  if( array_key_exists( 'f', $options ) ) {
    echo "Overwriting existing '${options['o']}' due to usage of -f.\n";
  } else {
    echo usage();
    echo "Output file '${options['o']}' already exists.\n";
    echo "Please remove it, or use -f (force).\n";
    exit;
  }
}

$output = reformat_conf_vars( file_get_contents( $options['i'] ), array_merge( $required_conf_vars, $optional_conf_vars ) );

$result = file_put_contents( $options['o'], $output );
if( !$result ) {
  echo "Failed to write new config file to '${options['o']}'.\n";
  echo "Permissions problem?\n";
} else {
  echo "Wrote converted configuration to '${options['o']}'.\n";
}

echo "Running syntax check on '${options['o']}'\n";
system( "php -l ${options['o']}", $return );
if( $return > 0 ) {
  exit(1);
}

// suppress warnings: we don't care if version.php is missing, etc.
// we're only interested in $conf
@require $options['o'];
$missing = array_diff( $required_conf_vars, array_keys( $conf ) );
if( count($missing) ) {
  echo "Generated config file is missing these required config values: ".implode( $missing, ',' );
  exit(1);
} else {
  echo "All required config values are defined in '${options['o']}'.\n";
}
echo "Finished.\n";
?>
