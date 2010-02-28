<?php

/*
 *  implementation of hook_connect_lookup
 *
 *  NB: lookup functions are intended to return contact information to 
 *  be passed back to, say, an email or fax function. They should return
 *  an array with keys 'name', 'fax', and 'email'.
 *
 * @return array (function name => function description)
 */
function connect_connect_lookup() {
	$lookups = array(
		'connect_lookup_mpp_ontario' => 'Ontario MPP (postal code)',
		'connect_lookup_mp' => 'Canadian MP (postal code)',
  );
  return $lookups;
}


/*
 *  Determine available target lookup types, return in select list format
 */
function connect_get_lookup_types($select = FALSE) {
  static $return = array();
  $empty = array();
  if (empty($return) ) {
    $return = module_invoke_all('connect_lookup');
    $return[0] = '';
    asort($return);
  }
  return $return;
}


/*
 * interface function for returning lookup description or data
 */
function connect_target_lookup(&$parent, &$child, $op = 'lookup', $type = '') {
  $return = false;
  if ($parent && $type) {
    $lookup = connect_node_options( $parent->nid, $type.'_lookup_type' );
    if ($lookup) {
      eval('$return = '.$lookup.'($op, $parent, $child);');
    }
  }
  return $return;
}


/*
 * connect_lookup_NAME( $op = 'lookup', $parent, $child )
 *
 * @ param $op
 * 'lookup' -> perform the lookup
 * 'requires' -> get info to pass through via connect_action_hook function
 * 'cache' -> returns an array of descriptors for the cache table
 *
 * @ return
 * if $op=='lookup': object( object->email object->name object->fax, etc )
 * if $op=='requires' : array as per hook_connect 'requires' op
 * of $op=='cache' : array describing the connet_cache items this lookup can/may use
 */

 
/*
 *  function to look up contact info for Ontario MPP -- basic lookup from Postal Code
 *
 *  uses the OpenConcept Consulting web service at http://makethechange.ca/
 *  which requires a client key
 *
 */
function connect_lookup_mpp_ontario($op = 'lookup', &$parent, &$child) {
  switch ($op) {    
    case 'cache' :
      return array(
        'EDID2MPP' => 'Federal riding to ontario MPP',
        'pc2riding_provincial_ontario_ca' => 'Postal code to Ontario riding'
      );
      break;
      
    case 'requires':
      $return['variables']  = array(
        //uses the same key as mp lookup
        'makethechange_key' => array(
          '#type'  => 'textfield',
          '#title' => 'Makethechange web service key',
          '#default_value' => connect_node_options($parent->nid, 'makethechange_key'),
          '#required' => TRUE,
        ),
        
        //unique cache for mpp
        'mpp_ontario_lookup_cache' => array(
          '#type'  => 'radios',
          '#title' => 'Cache the MPP lookup data?',
          '#options' => array( 'no' => 'No', 'yes' => 'Yes'),
          '#default_value' => connect_node_options($parent->nid, 'mpp_ontario_lookup_cache'),
          '#required' => TRUE,
        ),
      );
      
      $return['child']  = array(
        //same postal code as mp lookup
        'postal_code' => 'Postal code',
        
        //unique edid for ontario mpp
        'lookup_mp_data_edid_ontario' => 'Ontario Riding',
      );
      return $return;

    //validate the postal code (same code as mp lookup)  
    case 'validate' :
      $code = connect_value( 'postal_code', $parent, $child, 'child' );
      if ( !connect_is_postalcode($code) ) {
        form_set_error('', 'Please enter a valid postal code.');
      }
      break;

    //this is the meat of the function looks up edid then looks up the email of the ontari mpp based on edid  
    case 'lookup':
      $return     = array();
      $use_cache  = (connect_node_options($parent->nid, 'mpp_ontario_lookup_cache') == 'yes');
      $mtc_key    = connect_node_options($parent->nid, 'makethechange_key');
      $postalcode = connect_value('postal_code', $parent, $child, 'child');
      
      //find the edid based on postal code (for ontario ridings)
      $edid       = _connect_get_riding($mtc_key, $postalcode, 'provincial', 'ontario', $use_cache);
      
      if ($edid) {
        // save the riding in the child node
        connect_value('lookup_mp_data_edid_ontario', $parent, $child, 'child', $edid[0]['id']);
        
        /* Danger! in rare cases there may be > 1 matches: this
         * just grabs the first one; if this is a problem,
         * use the CCK MPautocomplete version to allow users to select a riding
         * or write a two-stage version of the participation form
         */
      
        //look up mp data
        $mpp = _connect_get_ontario_MPP_by_EDID($mtc_key, $edid[0]['id'], $use_cache); 
        if (!empty($mpp)){
          $return['name']  = $mpp->full_name;
          $return['email'] = $mpp->email;
          $return['fax']   = $mpp->fax;
          //***there is no fax for mpp?*** 
        }
        else {
          watchdog('connect', "connect_lookup_mpp_ontario: MPP not found for " . $edid[0][id]);
        }
      }
      else {
        watchdog('connect', "connect_lookup_mpp_ontario: EDID not found for $postalcode");
      }
      
      return $return;
      break;
     
  }
} 
 
/*
 *  function to look up contact info for Canadian MP -- basic lookup from Postal Code
 *
 *  uses the OpenConcept Consulting web service at http://makethechange.ca/
 *  which requires a client key
 *
 */
function connect_lookup_mp($op = 'lookup', &$parent, &$child) {
  switch ($op) {
    case 'cache' :
      return array(
        'EDID2MP' => 'Federal riding to MP',
        'pc2riding_federal_federal_ca' => 'Postal code to Federal riding'
      );
      break;

    case 'requires':
      $return['variables']  = array(
        'makethechange_key' => array(
          '#type'  => 'textfield',
          '#title' => 'Makethechange web service key',
          '#default_value' => connect_node_options($parent->nid, 'makethechange_key'),
          '#required' => TRUE,
        ),
        'mp_lookup_cache' => array(
          '#type'  => 'radios',
          '#title' => 'Cache the lookup data?',
          '#options' => array( 'no' => 'No', 'yes' => 'Yes'),
          '#default_value' => connect_node_options($parent->nid, 'mp_lookup_cache'),
          '#required' => TRUE,
        ),
      );
      
      $return['child']  = array(
        'postal_code' => 'Postal code',
        'lookup_mp_data_edid' => 'Riding',
      );
      return $return;

    case 'validate' :
      $code = connect_value( 'postal_code', $parent, $child, 'child' );
      if ( !connect_is_postalcode($code) ) {
        form_set_error('', 'Please enter a valid postal code.');
      }
      break;

    case 'lookup':
      $return     = array();
      $use_cache  = (connect_node_options($parent->nid, 'mp_lookup_cache') == 'yes');
      $mtc_key    = connect_node_options($parent->nid, 'makethechange_key');
      $postalcode = connect_value('postal_code', $parent, $child, 'child');
      $edid       = _connect_get_riding($mtc_key, $postalcode, 'federal', 'federal', $use_cache);
      if ($edid) {
        // save the riding in the child node
        connect_value('lookup_mp_data_edid', $parent, $child, 'child', $edid[0]['id']);
        
        /* Danger! in rare cases there may be > 1 matches: this
         * just grabs the first one; if this is a problem,
         * use the CCK MPautocomplete version to allow users to select a riding
         * or write a two-stage version of the participation form
         */
        $mp = _connect_get_MP_by_EDID($mtc_key, $edid[0]['id'], $use_cache);      
        if (!empty($mp)){
          $return['name']  = $mp->mp_name;
          $return['email'] = $mp->Email;
          $return['fax']   = $mp->HillFax;
        }
        else {
          watchdog('connect', "connect_lookup_mp: MP not found for $edid[0][id]");
        }
      }
      else {
        watchdog('connect', "connect_lookup_mp: EDID not found for $postalcode");
      }
      
      return $return;
      break;
  }
}

//Ontario MPP lookup based on edid
//This function returns an mpp object containing contact information for the MPP 
//it uses http://makethechange.ca to make the lookup
function _connect_get_ontario_MPP_by_EDID($service_key = NULL, $edid = NULL, $use_cache = FALSE) {
  if (is_numeric($edid)) {
    
    // cached value?
    if ($use_cache) {
      $cached = _connect_cached_value('EDID2MPP', $edid);
      if (!empty($cached)) {
        drupal_set_message( '_connect_get_ontario_MPP_by_EDID cache hit' );
        return $cached;
      }
    }

    // do lookup if no cached value found
    if ($service_key) {
      _connect_json_support();  // make sure we have JSON support
      
      $mp_url  = 'http://makethechange.ca/provincial/on_riding.php?type=json&key='.urlencode($service_key).'&edid='.$edid;
      $fh = fopen($mp_url, "r") or watchdog('debug','MPP fopen failed: ' . $mp_url );
      $mp_info = fread($fh, 8192) or watchdog('debug','MPP fread failed: ' . $mp_url );
      fclose($fh);

      if (!empty($mp_info)) {
        if (!strpos($mp_info, 'Error:')) {
          $return  = json_decode(trim($mp_info));
          if ($use_cache) {
            $cached = _connect_cached_value('EDID2MPP', $edid, $return);
            drupal_set_message( '_connect_get_ontario_MPP_by_EDID cache set' );
          }
          return $return;
        }
        else {
          watchdog('connect', "_connect_get_ontario_MPP_by_EDID $mp_info");
        }
      }
      else {
        watchdog('connect', "_connect_get_ontario_MPP_by_EDID lookup returned empty MP data for $edid");
      }
    }
  }
  
  // parameter and lookup errors fall through here
  watchdog('connect', "_connect_get_ontario_MPP_by_EDID missing EDID or service key");
  return NULL;
}


// MP lookup using EDID
function _connect_get_MP_by_EDID($service_key = NULL, $edid = NULL, $use_cache = FALSE) {
  if (is_numeric($edid)) {
    // cached value?
    if ($use_cache) {
      $cached = _connect_cached_value('EDID2MP', $edid);
      if (!empty($cached)) {
        drupal_set_message( '_connect_get_MP_by_EDID cache hit' );
        return $cached;
      }
    }

    // do lookup
    if ($service_key) {
      _connect_json_support();  // make sure we have JSON support
      $mp_url  = 'http://makethechange.ca/federal/riding.php?type=json&key='.urlencode($service_key).'&edid='.$edid;
      $fh = fopen($mp_url, "r") or watchdog('debug','fopen failed: ' . $mp_url );
      $mp_info = fread($fh, 8192) or watchdog('debug','fread failed: ' . $mp_url );
      fclose($fh);
      if (!empty($mp_info)) {
        if (!strpos($mp_info, 'Error:')) {
          $return  = json_decode(trim($mp_info));
          if (empty($return->mp_name)) {
            $return->mp_name = $return->FirstName .' '. $return->LastName;
          }
          if ($use_cache) {
            $cached = _connect_cached_value('EDID2MP', $edid, $return);
            drupal_set_message( '_connect_get_MP_by_EDID cache set' );
          }
          return $return;
        }
        else {
          watchdog('connect', "get_MP_by_EDID $mp_info");
        }
      }
      else {
        watchdog('connect', "get_MP_by_EDID lookup returned empty MP data for $edid");
      }
    }
  }
  
  // parameter and lookup errors fall through here
  watchdog('connect', "get_MP_by_EDID missing EDID or service key");
  return NULL;
}


/*
 *  generic riding lookup
 *
 *  @parameters
 *    postalcode (string)
 *    scope (string)  : 'federal'|'provincial'
 *    detail (string) : 'federal', 'ontario', 'ottawa', etc.
 *
 *  @return
 *    (array) of riding info = array( 'id'=>'', 'en'=>'', 'fr'=>'' )
 *
 *   TODO - fix the web service API.
 *
 */
function _connect_get_riding($service_key = NULL, $postalcode = NULL, $scope='federal', $detail = 'federal', $use_cache = FALSE) { 
  // verify and normalize postalcode format
  if (!connect_is_postalcode($postalcode)) return;
  $postalcode = strtoupper(preg_replace('/\s/', '', $postalcode));
  $return = FALSE;
  $cache  = 'pc2riding_' . $scope . '_' . $detail . '_ca';
  
  // cached value?
  if ($use_cache) {
    $cached = _connect_cached_value($cache, $postalcode);
    if(!empty($cached)) {
      drupal_set_message( '_connect_get_riding cache hit' );
      return $cached;
    }
  }

  // look up data, if not found in cache
  // determine lookup URI based on type of lookup
  $scope_function = array (
    'federal'    => array ( 'federal' => 'pc2csv/index' ),
    'provincial' => array ( 'ontario' => 'provincial/on_riding' ),
    'municipal'  => array (),
  );

  if ( $service_key && isset($scope_function[$scope][$detail]) ) {
    $riding_url  = 'http://makethechange.ca/' .$scope_function[$scope][$detail]. '.php?key='.urlencode($service_key).'&pc='.urlencode($postalcode).'&type=riding';
    $fh = fopen($riding_url, "r") or watchdog('debug','fopen failed: ' . $riding_url );
    $riding_data = fread($fh, 200) or watchdog('debug','fread failed: ' . $riding_url );
    fclose($fh);
  }

  // if there are results, walk through them and create an array of arrays
  if (!empty($riding_data)) {
    $row_token = strtok($riding_data, "\n");
    while ($row_token != false) {
      $return[] = connect_riding_data($row_token);
      $row_token = strtok("\n");
    }

    // cache, if desired, and not error message
    if ($use_cache && strpos($riding_data,'Error :') === FALSE) {
      $cached = _connect_cached_value($cache, $postalcode, $return);
      drupal_set_message( '_connect_get_riding cache set' );
    }
  }

  return $return;
}


// turns riding data returned from web service into an indexed array
function connect_riding_data($data = NULL) {
  $return     = null;
  $array_keys = array('id','en','fr');
  $array_temp = explode(',',$data);
  for ($i = 0; $i < count($array_temp); $i++) {
    $return[$array_keys[$i]] = trim( $array_temp[$i] );
  }
  return $return;
}



/**** Utility functions ****/


/*
 *  Wrappers for JSON support if this version/install of PHP
 *  doesn't support it natively
 * 
 *  Thanks to http://abeautifulsite.net/notebook/71
 *  JSON library from http://mike.teczno.com/json.html
 */
function _connect_json_support() {
  if (!function_exists('json_encode')) {
    require_once('JSON.php');  
    function json_encode($data) {
      $json = new Services_JSON();
      return($json->encode($data));
    }
  }

  if (!function_exists('json_decode')) {
    require_once('JSON.php');  
    function json_decode($data) {
      $json = new Services_JSON();
      return($json->decode($data));
    }
  }
}

// check the connect cache for a saved value for a lookup
// if no third param = getter; third value = setter
function _connect_cached_value($type = NULL, $source = NULL, $value = NULL) {  
  $return = FALSE;
  if ($type && $source) {
    // normalize the source string:  only numbers and uppercase letters
    $source = strtoupper($source);
    $source = preg_replace('/[^0-9A-Z]/', '', $source);
    
    if (!$value) {
      $sql = "SELECT target from {connect_cache} WHERE type='%s' AND source = '%s';";
      $result = db_query($sql, $type, $source);
      if ($row = db_fetch_object($result)) {
        $return = unserialize($row->target);
      }
    }
    else {
      // delete
      $sql = "DELETE from {connect_cache} WHERE type='%s' AND source = '%s';";
      $result = db_query($sql, $type, $source);
      //insert
      $sql = "INSERT INTO {connect_cache} (type, source, target, created) VALUES('%s', '%s', '%s', %d);";
      $result = db_query($sql, $type, $source, serialize($value), time());
      $return = $result;
    }
  }
  return $return;
}

// return names for cached items
function _connect_get_cache_names() {
  $cache_names = array();
  $lookup_types = connect_get_lookup_types(TRUE);
  unset($lookup_types[0]);
  $empty = array();
  foreach ($lookup_types as $key=>$title) {
    if (function_exists($key)) {
      eval('$temp = ' . $key . '(\'cache\', $empty, $empty);');
      $cache_names = array_merge($cache_names, $temp);
    }
  }
  return array_unique($cache_names);
}
