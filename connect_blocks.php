<?php
// $Id: connect_blocks.php,v 1.2 2009/04/03 19:09:52 stevem Exp $

define('CONNECT_BLOCK_PARENT_ONLY', 1);
define('CONNECT_BLOCK_NOT_PARENT', 2);

function connect_block_list() {
  $return = array();
  
  // find all the connect parent node types
  $parent_nodetypes = variable_get('connect_parent_nodes', array());
  if (empty($parent_nodetypes)) {
    return $return;
  }
  foreach ($parent_nodetypes as $type) {
    $types .= "'$type',";
  }
  $types = substr($types, 0, strlen($types)-1);

  // determine which parent nodes expose the child form as a block
  $sql = "SELECT nid FROM {node} WHERE type IN ($types);";
  $result = db_query($sql);
  while ($row = db_fetch_object($result)) {
    $parent_node    = node_load($row->nid);
    $parent_actions = connect_get_actions($parent_node->nid);
      if (in_array('connect_action_provide_block', $parent_actions)) {
      $return[$parent_node->nid] = array('info' => 'Connect: '. $parent_node->title);
    }
  }
  
  return $return;
}

// TODO -- prevent block from displaying wrong form
// when main page is another action without a block
function connect_block_view($nid = 0) {
  $return = array();
  $parent_node = node_load($nid);
  if ($parent_node) {
    $parent_node =& _connect_parent_node($parent_node);
    
    // only display if the block option is turned on
    //$parent_actions = connect_get_actions($nid);
    if (!in_array('connect_action_provide_block', connect_get_actions($nid))) {
      return;
    }
    
    // require a CAPTCHA on the form?
    if (!_connect_captcha_test('connect_form_block')) {
      return;
    }
      
    // visibility control
    $visibility = connect_node_options($nid, 'provide_block_visibility');
    if ($visibility > 0) {
      $parent_path = "node/$nid";
      $current_path = arg(0) .'/'. arg(1);
      if (($visibility == CONNECT_BLOCK_NOT_PARENT && $parent_path == $current_path) ||
          ($visibility == CONNECT_BLOCK_PARENT_ONLY && $parent_path != $current_path))
      {
        return;
      }
    }
    
    $form = drupal_get_form('connect_form_block', 'block', $nid);
    $text = connect_node_options($nid, 'provide_block_text');
    $link = empty($text) ? '' : '<p>&raquo;&nbsp;'. l($text, "node/$nid") .'</p>';
    $return['subject'] = $parent_node->title;
    $return['content'] = $form . $link;
  }
  return $return;
}

/*
 *  these just call the regular form functions, but with form_id = 'connect_form_block'
 */
/*
function connect_form_block() {
  $form = connect_form();
  return $form;
}

function connect_form_block_validate($form_id, &$form_values) {
  return connect_form_validate($form_id, $form_values);
}

function connect_form_block_submit($form_id, &$form_values) {
  return connect_form_submit($form_id, $form_values);
}
*/
