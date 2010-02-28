<?php
// $Id: connect_admin.php,v 1.3.2.2 2010/01/27 20:07:14 stevem Exp $

/**** FORM: select campaign functions ****/

function connect_node_functions_form() {
  drupal_add_css(drupal_get_path('module', 'connect') .'/connect.css');
  
  $child   = array();
  $options = array();
  $p_nid   = arg(1);
  $parent  = node_load($p_nid);

  // determine possible and enabled actions
  $requirements_OK = TRUE;
  $actions     = connect_get_actions($parent->nid);
	$action_list = module_invoke_all('connect');
  unset($action_list['connect_action_basic']);
  foreach ($action_list as $function=>$action) {
    $status = '';    
    if (in_array($function, $actions)) {
      if (TRUE === _connect_hook_check_requirements($parent, $child, $function, 'parent')) {
        $status = ' ' . theme_image(drupal_get_path('module', 'connect') . '/images/accept.png', '(ACTIVE)', 'This function is active.');
      }
      else {
        $requirements_OK = FALSE;
        $status = ' ' . theme_image(drupal_get_path('module', 'connect') . '/images/exclamation.png', '(INACTIVE)', 'This function is not active. Please check the settings tab.');
      }
    }
    $options[$function] = $action['title'] . $status . '<p class="connect-comment">' . $action['desc'] . '</p>';
  }
  if (!$requirements_OK) {
    drupal_set_message('One or more of your selected functions requires additional settings to be configured.<br />Please check the settings tab for details.' , 'error');
  }
  
  $form   = array();
  $form['parent_id'] = array(
    '#type' => 'value',
    '#value' =>   $parent->nid,
  );
  $form['connect_actions'] = array(
    '#type' => 'fieldset',
    '#title' => 'Choose functions to apply to this campaign',
  );
  $form['connect_actions']['actions'] = array(
    '#type'    => 'checkboxes',
    '#title'   => '',
    '#options' => $options,
    '#default_value' => $actions,
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
    );
  return $form;
}


function connect_node_functions_form_submit($form, &$form_state) {
  // save action options
  $options = array();
  foreach( $form_state['values']['actions'] as $key=>$value ) {
    if ("$key" == "$value") {
      $options[] = $value;
    }
  }

  // restore mandatory action
  $options[] = 'connect_action_basic';
  connect_node_options( $form_state['values']['parent_id'], 'connect_actions', $options );

  drupal_set_message('Your selected functions have been updated.');
}


/**** FORM: settings ****/

function connect_node_settings_form() {
  drupal_add_css(drupal_get_path('module', 'connect') .'/connect.css');
  
  $form    = array();
  $child   = NULL;
  $p_nid   = arg(1);
  $parent  = node_load($p_nid);
  $actions = connect_get_actions($parent->nid);
	//var_dump($actions);
	
  // test for requirements  
  $requirements_message = '';
  foreach ($actions as $function) {
		$this_OK = _connect_hook_check_requirements($parent, $child, $function, 'parent');
		if ($this_OK !== TRUE) $requirements_message .= $this_OK;
  }
  if (!empty($requirements_message)) {
    $requirements_message = 'The following items need to to be configured for your actions to work properly:<br />'. $requirements_message;
    drupal_set_message($requirements_message);
  }

  // store the parent nid
  $form['parent_id'] = array(
    '#type' => 'value',
    '#value' =>   $parent->nid,
  );
  
  // required variables
  $map      = connect_get_map($parent->nid);
  $required = connect_get_required_vars($parent, $child);
	//var_dump($map);
	//var_dump($required);
    
    
  if (!empty($required['variables'])) {
    $form['campaign_variables'] = array('#tree' => TRUE);
    foreach ( $required['variables'] as $action=>$vars ) {
      $description = $action_list[$action];
      $form['campaign_variables']["variables_$action"] = array(
        '#type'    => 'fieldset',
        '#title'   => $description['title'],
      );
      foreach( $vars as $key=>$formitem ) {
        $form['campaign_variables']["variables_$action"][$key] = $formitem;
      }
      if ($action == 'connect_action_basic') {
        $form['campaign_variables']["variables_$action"]['#weight'] = -1;
      }
    }
  }
  
  // parent node fields
  //var_dump($required['parent']);
  if ( !empty($required['parent']) ) {
    $options = connect_get_node_fields($parent->type);
    $form['variables_parent'] = array(
      '#type'    => 'fieldset',
      '#title'   => 'Required fields in parent/campaign node',
      '#tree'    => TRUE,
    );
    $form['variables_parent']['message'] = array(
      '#value'   => '<em>'. t('The selected functions store or use information from the campaign/parent node. Please identify which fields in the parent node correspond to the function settings below.') .'</em>',
    );
    foreach ( $required['parent'] as $key=>$desc ) {
      $form['variables_parent'][$key] = array(
        '#type'    => 'select',
        '#title'   => $desc,
        '#options' => $options,
        '#default_value' => isset($map[$key]) ? $map[$key]: '',
        '#required' => TRUE,
      );
    }
  }

  // child node fields
  if (connect_node_options($parent->nid, 'participant_type')) {
    if (!empty($required['child'])) {
			//var_dump($required['child']);
      $form['variables_child'] = array(
        '#type'    => 'fieldset',
        '#title'   => 'Required fields in child/participant node',
        '#tree'    => TRUE,
      );
      $form['variables_child']['message'] = array(
        '#value'   => '<em>'. t('The selected functions store or use information from the participant/child node. Please identify which fields in the child node correspond to the function settings below.') .'</em>',
      );
      $child_type = connect_node_options( $parent->nid, 'participant_type' );
      $options    = connect_get_node_fields($child_type);
      foreach ( $required['child'] as $key=>$desc ) {
        $form['variables_child'][$key] = array(
          '#type'    => 'select',
          '#title'   => $desc,
          '#options' => $options,
          '#default_value' => isset($map[$key]) ? $map[$key]: '',
          '#required' => TRUE,
        );
      }
    }
  }
  else {
    $form['variables_child']['message'] = array(
      '#value'   => t('Please select a participant type and return here to set up your variables.'),
    );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
    );
  
  //var_dump($form);
  return $form;
}


function connect_node_settings_form_validate($form, &$form_state) {
  $child = NULL;
  $parent->nid  = $form_state['values']['parent_id'];   // required by connect_call_hooks
  $parent->data = $form_state['values'];
  connect_call_hooks($parent, $child, 'admin-validate', 'parent');
}

// handle nested arrays
function _connect_set_values_from_form($item, $parent_id) {
  foreach ($item as $key=>$value) {
    if (is_array($value)) {
        _connect_set_values_from_form($value, $parent_id);
    }
    else {
      connect_node_options($parent_id, $key, $value);
    }
  }
}

function connect_node_settings_form_submit($form, &$form_state) {
  // save overall settings
  foreach ($form_state['values']['campaign_variables'] as $function=>$vars) {
    _connect_set_values_from_form($vars, $form_state['values']['parent_id']);
  }
  
  // save variable->field mapping
  $options = array();
  foreach ( array('variables_parent','variables_child') as $formitem) {
    if (isset($form_state['values'][$formitem])) {
      foreach ($form_state['values'][$formitem] as $key=>$value) {
        if ( !empty($value) ) {
          $options[$key] = $value;
        }
      }
    }
  }
  connect_node_options( $form_state['values']['parent_id'], 'connect_map', $options );

  $null = drupal_get_messages(NULL); // clear bogus _connect_hook_check_requirements errors
  drupal_set_message('The campaign configuration has been updated.');
}


/**** FORM: connect module administration ****/

function connect_admin_form() {
  // grab all node types, format for use in form
  $sql = "SELECT type FROM {node_type};";
  $result = db_query($sql);
  while ($row = db_fetch_object($result)) {
    $type_options[$row->type] = $row->type;
  }

  // remove some standard node types
  $remove = array('blog', 'forum', 'page', 'story');
  $type_options = array_diff($type_options, $remove);

  $form = array();
  if (!empty($type_options)) {
     // participant node types
     $form['connect_participant_nodes'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Which node types hold participant/child information?'),
      '#options' => $type_options,
      '#required' => TRUE,
      '#default_value' => variable_get('connect_participant_nodes', array()),
      );

     // parent node types
     $form['connect_parent_nodes'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Which node types can be used as campaign/parent nodes?'),
      '#options' => $type_options,
      '#required' => TRUE,
      '#default_value' => variable_get('connect_parent_nodes', array()),
      );

      // captcha
      $form['connect_captcha_required'] = array(
        '#type' => 'radios',
        '#title' => t('Should connect require a CAPTCHA?'),
        '#options' => array( 'yes' => 'Yes', 'no' => 'No' ),
        '#default_value' => variable_get('connect_captcha_required', 'yes'),
      );

      // cache settings
      $form['connect_cache'] = array(
        '#value' => 'Define the timeout value for your cached data using the format "999 X", where "999" is an integer and "X" is one of mhd, for minutes, hours, days. Leave blank, or set the number to zero (0) to set an unlimited cache lifetime.',
      );
      require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
      $cache_names = _connect_get_cache_names();
      foreach ($cache_names as $key=>$title) {
        $form['connect_cache']["connect_cache_$key"] = array(
          '#type' => 'textfield',
          '#title' => $title,
          '#size' => 10,
          '#default_value' => variable_get("connect_cache_$key", ''),
        );
      }
      
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
      );
  }
  else {
    $form['message'] = array(
      '#value' => t('No node types have been defined yet. Please set up some content types and return here to set up Connect.'),
    );
  }
  return $form;
}

function connect_admin_form_validate($form, &$form_state) {
  // parent node type cannot be a child node type as well
  $test = array_intersect_assoc($form_state['values']['connect_participant_nodes'], $form_state['values']['connect_parent_nodes']);
  foreach ($test as $key=>$val) {
    if (empty($val)) {
      unset($test[$key]);
    }
  }
  if (!empty($test)) {
    form_set_error('', 'A node type cannot be both a parent and a child node.');
  }

  // validate cache timeouts
  require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
  $cache_names = _connect_get_cache_names();
  $regex = '/^[0-9]{1,}\ [mhd]$/';
  foreach ($cache_names as $key=>$title) {
    $field_value = $form_state['values']["connect_cache_$key"];
    if (!empty($field_value) && !preg_match($regex,$field_value)) {
      form_set_error("connect_cache_$key", 'Please specify cache lifetime in the "999 X" format.');
    }
  }
}

function connect_admin_form_submit($form, &$form_state) {
  // save participant node info
  $data = array();
  foreach ($form_state['values']['connect_participant_nodes'] as $key=>$value) {
    if ($key === $value) {
      $data[] = $value;
    }
  }
  if (! empty($data)) {
    variable_set('connect_participant_nodes', $data);
  }

  // save parent node info
  $data = array();
  foreach ($form_state['values']['connect_parent_nodes'] as $key=>$value) {
    if ($key === $value) {
      $data[] = $value;
    }
  }
  if (! empty($data)) {
    variable_set('connect_parent_nodes', $data);
  }
  // captcha
  $captcha = $form_state['values']['connect_captcha_required'] == 'no' ? 'no' : 'yes';
  variable_set('connect_captcha_required', $captcha);

  // cache timeouts
  require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
  $cache_names = _connect_get_cache_names();
  foreach ($cache_names as $key=>$title) {
    $interval = empty($form_state['values']["connect_cache_$key"]) ? 0 : $form_state['values']["connect_cache_$key"];
    variable_set("connect_cache_$key", $interval);
  }
  drupal_set_message(t('The connect settings have been updated.'));
}
