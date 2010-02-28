<?php


/*
function connect_action_civicrm_insert(&$parent, &$participant) {
  // duh.
  if (!module_exists('civicrm')) {
     return false;
  }

  civicrm_initialize(true);
  $required   = array();  // holds params to test for existing contact
  $crm_params = array();  // holds all params to create/update contact
  $translate  = variable_get('connect_civicrm_map_'.connect_nid(), array());
  $crm_test   = variable_get('connect_civicrm_test_'.connect_nid(), array());

  // TODO: include data types in the map to insert non-text info

  // grab data from CCK node
  foreach ($participant as $field=>$value) {
    $field_keys = connect_get_field_keys($field);
    foreach ($field_keys as $key) {
      if (!empty($value[0][$key]) && isset($translate[$field][$key])) {
        $crm_field = $translate[$field][$key];
        $crm_params[$crm_field] = $value[0][$key];

        // keep fields to test for existence
        if ( in_array( $crm_field, $crm_test) ) {
          $required[$crm_field] = $value[0][$key];
        }
      }
    }
  }

  // update or create contact
  if ( !empty($crm_params) && !empty($required) ) {
    $get_contact = crm_get_contact($required);
    if ( get_class($get_contact) == 'CRM_Contact_BAO_Contact' ) {
      $contact = crm_update_contact($get_contact, $crm_params);
    } else {
      $contact = crm_create_contact($crm_params, 'Individual');
    }

    // group membership
    if (get_class($contact) == 'CRM_Contact_BAO_Contact') {

      // save contact ID in cck record
      $participant->field_civicrm_id[0]['value'] = $contact->id;
      node_save($participant);

      // determine group name
      $group = false;
      if (isset($parent->og_groups)) {  // if there's an OG, use that name
        $og = node_load($parent->og_groups[0]);
        $group = $og->title;
      } else {   // otherwise use name of parent node
        $group = $parent->title;
      }

      // find or create group
      $groups = crm_get_groups(array('title' => $group));
      if ( isset($groups[0]) ) {
        $group_obj = $groups[0];
      } else {
        $group_obj = crm_create_group( array('title' =>  $group, 'is_active' => '1') );
      }

      // add contact to group
      $to_add[] = $contact;
      $result = crm_add_group_contacts($group_obj, $to_add, $status = 'Added', $method = 'Admin');
      if ( $result !== null ) {
        watchdog('debug', 'connect: user not added to group: '.$participant->nid);
      }
    } else {
      watchdog('debug', 'connect: CiviCRM record not created: '.$participant->nid);
    }
  } else {
    watchdog('debug', 'connect: CiviCRM params empty: '.$participant->nid);
  }
}

function connect_action_civicrm_update(&$participant) {
  if (module_exists('civicrm') && isset($participant->field_civicrm_id[0]['value'])) {
    civicrm_initialize(true);

    $crm_params = array();  // holds all params to create/update contact
    $translate  = variable_get('connect_civicrm_map_'.$participant->field_parent_id[0]['value'], array());

    // TODO: include data types in the map to insert non-text info

    // grab data from CCK node
    foreach ($participant as $field=>$value) {
      $field_keys = connect_get_field_keys($field);
      foreach ($field_keys as $key) {
        if (!empty($value[0][$key]) && isset($translate[$field][$key])) {
          $crm_field = $translate[$field][$key];
          $crm_params[$crm_field] = $value[0][$key];
        }
      }
    }

    // update contact
    $crm_id = $participant->field_civicrm_id[0]['value'];
    if ( !empty($crm_params) ) {
      $get_contact = crm_get_contact(array('contact_id' => $crm_id));
      if (is_a($get_contact, 'CRM_Contact_BAO_Contact')) {
        $contact = crm_update_contact($get_contact, $crm_params);
        return true;
      }
    }
  }
  // errors fall through
  watchdog('error','Connect: civicrm update failed: ' . $participant->nid);
}
*/

/*
function connect_civicrm_map_form() {
  //add a form element that can map cck fields -> civicrm fields
  $form['connect_civicrm_mapping']['intro'] = array(
    '#type' => 'markup',
    '#value' => '<p>' . t('Select the CiviCRM fields that correspond to the participant data.') . '</p>',
    );

  //only get the fields for this node's participant type!
  $nid            = arg(1);
  $node           = node_load($nid);
  $cck_info       = _content_type_info();
  $cck_fields     = $cck_info['content types'][$node->field_participant_type[0]['value']]['fields'];
  $civicrm_fields = connect_civicrm_properties_options();
  $cck2crm_map    = variable_get('connect_civicrm_map_'.$nid, array());

  // map cck -> civicrm
  foreach ($cck_fields as $cck_field) {
    $cck_name = $cck_field['field_name'];
    $cck_keys = connect_get_field_keys($cck_name);
    foreach ($cck_keys as $key) {
      $default = isset($cck2crm_map[$cck_name][$key]) ? $cck2crm_map[$cck_name][$key] : '';
      $form['connect_civicrm_mapping']['connect_civicrm_map_'.$cck_name.'+'.$key] = array (
        '#type' => 'select',
        '#title' => t($cck_field['widget']['label']. ' ('.$key.')' ),
        '#options' => $civicrm_fields,
        '#default_value' => $default,
        '#prefix' => '<div class="container-inline">',
        '#suffix' => '</div>',
      );
      // keep extant mapping for options in the next form item
      if ($default) {
        $test_options[$default] = $civicrm_fields[$default];
      }
    }

    // define civicrm fields to test for existing contacts
    if (!empty($test_options)){
      $form['connect_civicrm_mapping']['intro2'] = array(
        '#type' => 'markup',
        '#value' => '<div><p style="padding-top:1em">' . t('You must select at least one field that will be used by CiviCRM to determine if a contact already exists. Good choices include email address or first and last name.') . '</p></div>',
        '#weight' => '20',
      );

      $form['connect_civicrm_mapping']['connect_civicrm_test'] = array (
        '#type' => 'checkboxes',
        //'#required' => TRUE,
        '#title' => t('Choose the CiviCRM elements used to uniquely identify contacts.'),
        '#options' => $test_options,
        '#default_value' => variable_get('connect_civicrm_test_'.$nid, array()),
        '#weight' => '21',
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
      '#weight' => '22',
    );
  }
  return $form;
}

// TODO
function connect_civicrm_map_form_validate($form_id, $form_values) {

  // validate against cck and civicrm fields
  $civicrm_fields = connect_civicrm_properties_options();
  $cck_data       = content_fields(NULL, $node->participant_type[0]['value']);
  foreach ($cck_data as $cck) {
    $cck_fields[] = $cck['field_name'];
  }

  $form_keys = array_keys($form_values);
  foreach ($form_keys as $form_key) {
    if ( strpos( $form_key, 'connect_civicrm_' ) !== false ) {
      if ( ! empty($form_values[$form_key]) ) {
        $cck_field = str_replace( 'connect_civicrm_', '', $form_key );
        if ( !in_array( $cck_field, $cck_fields) || !in_array($form_values[$form_key], $civicrm_fields) ) {
          // error
        }
      }
    }
  }
  return true;
}

function connect_civicrm_map_form_submit($form_id, $form_values) {
  $nid = arg(1);

  // save data mapping
  $data = array();
  $form_keys = array_keys($form_values);
  foreach ($form_keys as $form_key) {
    if ( strpos( $form_key, 'connect_civicrm_map_' ) !== false ) {
      if ( ! empty($form_values[$form_key]) ) {
        // determine name, key
        $cck_field     = str_replace( 'connect_civicrm_map_', '', $form_key );
        $cck_keyname   = strrchr( $cck_field, '+');
        $cck_fieldname = str_replace( $cck_keyname, '', $cck_field );
        $cck_keyname   = str_replace( '+', '', $cck_keyname );
        $data[$cck_fieldname][$cck_keyname] = $form_values[$form_key];
      }
    }
  }
  variable_set('connect_civicrm_map_'.$nid, $data);

  // save civicrm contact test info
  if (isset($form_values['connect_civicrm_test'])) {
    $data = array();
    $civicrm_fields = connect_civicrm_properties_options();
    $civicrm_names  = array_flip($civicrm_fields);
    foreach ($form_values['connect_civicrm_test'] as $key=>$value) {
      if ($key === $value) {
        $data[] = $value;
      }
    }
    variable_set('connect_civicrm_test_'.$nid, $data);
  }

  drupal_set_message(t('The CiviCRM data mapping has been updated.'));
}
*/


/**
 * allows designated users to use the parent node form as a data entry mechanism
 */
/*
function connect_action_data_entry($op='', &$parent=NULL, &$child=NULL, $target='child') {
 return;
          drupal_set_message(t('Participant entered. Enter another?'));
          connect_session_status_set( $node->nid, '');
          // fall through to display form
}
*/
