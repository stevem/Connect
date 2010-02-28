<?php

/**
 * Implementation of hook_content_default_fields().
 */
function connect_node_types_content_default_fields() {
  module_load_include('inc', 'connect_node_types', 'connect_node_types.defaults');
  $args = func_get_args();
  return call_user_func_array('_connect_node_types_content_default_fields', $args);
}

/**
 * Implementation of hook_node_info().
 */
function connect_node_types_node_info() {
  module_load_include('inc', 'connect_node_types', 'connect_node_types.features.node');
  $args = func_get_args();
  return call_user_func_array('_connect_node_types_node_info', $args);
}
