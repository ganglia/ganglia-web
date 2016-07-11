<?php

include_once $conf['gweb_root'] . "/lib/json.php";

// Common error handling
function api_return_error ( $message ) {
	$digest = array( "status" => "error", "message" => $message );
	die ( json_encode($digest) );
}

function api_return_ok ( $message ) {
	$digest = array( "status" => "ok", "message" => $message );
	die ( json_encode($digest) );
}

// Handle PHP error
set_error_handler("ganglia_api_error_handler");
function ganglia_api_error_handler ($no, $str, $file, $line, $context) {
  $context; // PHPCS
  switch ($no) {
    case E_ERROR:
    case E_CORE_ERROR:
    case E_COMPILE_ERROR:
    case E_USER_ERROR:
      api_return_error( "$file [$line] : $str" );
      break;
  }
}

?>
