<?php
// $Id: connect_actions.php,v 1.9.2.6 2010/02/28 01:12:03 stevem Exp $

/*
 * action functions make themselves known and take effect when called as hooks from connect
 *
 * @params $op
 * 'requires' -> describe parent & child data requirements for config UI
 * 'menu' -> return array of cached menu items, used to register callbacks
 * 'validate'   -> evaluate child content (NB $child = (array) $form_data, not (obj) $child)
 * 'form_alter' -> allows actions to affect the child node form when before it's displayed
 * 'insert'  -> handle changes to $target nodes
 * 'update'  -> handle changes to $target nodes
 * 'delete'  -> handle changes to $target nodes
 * 'display' -> node is being displayed
 * 'status'  -> reports on status of child node interaction
 * 'redirect' -> sets a destination instead of the form again on successful submit
 * 'admin-validate' -> function settings are being validated
 *    ($parent->nid contains the parent id, and $parent->data holds the form values)
 *
 *  @return
 *  if $op='display', an array('data' => string)
 *  if $op='status', an array('message' => string, 'show_form' => boolean)
 *  if $op='validate', array('status' => boolean, 'message' => string)
 */

/**
 *
 * Implementation of hook_connect
 *
 * declares any action functions to be made available to connect parent nodes
 *
 * return array('function callback name' => array('title' => string, 'desc' => string)
 *
 * 
 **/
function connect_connect() {
	$actions = array(
		'connect_action_basic' => array(
			'title'			=> 'Basic settings',
		),
		
		'connect_action_redirect_submit' => array(
		  'title' 		=> 'Redirect on submit',
		  'desc'  		=> 'Allows you to specify a URL or node to present on successful form submission.',
		),

		'connect_action_display_progress' => array(
		  'title' 		=> 'Display progress bar',
		  'desc'  		=> 'Adds a CSS-based display showing how many of the target number have participated.',
		),

		'connect_action_voteonce' => array(
		  'title' 		=> 'One vote per person',
		  'desc'  		=> 'Prevent the same person from participating more than once.',
		),

		'connect_action_display_participants' => array(
      'title' 		=> 'Display participants',
      'desc'  		=> 'Select fields to display in a public listing of participants. (Double opt-in enabled.)',
		),

		'connect_action_content_replace' => array(
      'title' 		=> 'Content: rewrite',
      'desc'  		=> 'Allows participants to revise the content provided by the parent node.',
		),

		'connect_action_content_append' => array(
      'title' 		=> 'Content: append',
      'desc'  		=> 'Allows participants to add their own comments, etc. to the content provided by the parent node.',
		),

		'connect_action_send_email' => array(
      'title' 		=> 'Send email',
      'desc'  		=> 'Sends email to/cc/bcc addresses that you specify or that can be looked up on the basis of participant information.',
		),
		
		'connect_action_double_optin' => array(
      'title' 		=> 'Double opt-in',
      'desc'  		=> 'Sends an email to the participant confirming participation. <strong>Partly implemented. Right now only the "display participants" function respects it.</strong>',
		),
	);
	return $actions;
}

  
/**
 * Mandatory action, handles basic housekeeping
 */
function connect_action_basic(&$parent, &$child, $op='', $target='parent') {
  switch($op) {
	case 'requires' :
		$return = array();

		$default = connect_node_options($parent->nid, 'debug_hooks');
		$default = $default ? $default : 'no';
		$return['variables']['debug_hooks'] = array(
			'#type'  => 'radios',
			'#title' => t('Turn on debugging?'),
			'#description' => t('This option turns on a display of debugging messages that can be useful when troubleshooting connect problems. (Don\'t bother turning this on unless you are a developer.)'),
			'#options' => array('yes' => 'Yes', 'no' => 'No'),
			'#default_value' => $default,
			'#required' => TRUE,
		);
	 
		$types = connect_participant_types_options();
		$types[0] = '';
		asort($types);
		$return['variables']['participant_type'] = array(
			'#type'  => 'select',
			'#title' => 'Participant node type',
			'#options' => $types,
			'#default_value' => connect_node_options($parent->nid, 'participant_type'),
			'#required' => TRUE,
		);

		$return['variables']['participant_title'] = array(
			'#type'  => 'textfield',
			'#title' => 'Title for the participants',
			'#default_value' => connect_node_options($parent->nid, 'participant_title'),
			'#required' => TRUE,
		);

		$return['variables']['call_to_action'] = array(
			'#type'  => 'textfield',
			'#title' => 'Call to action',
			'#default_value' => connect_node_options($parent->nid, 'call_to_action'),
			'#required' => TRUE,
		);

		$return['variables']['thankyou'] = array(
			'#type'  => 'textarea',
			'#size' => 4,
			'#title' => 'Thank-you message',
			'#default_value' => connect_node_options($parent->nid, 'thankyou'),
			'#required' => TRUE,
		);
		return $return;
		break;
	
	case 'status' :
		// display 'thank you' message
		if (isset($_SESSION['connect_action_thanks_'.$parent->nid])) {
			unset($_SESSION['connect_action_thanks_'.$parent->nid]);
			$message = connect_node_options($parent->nid, 'thankyou');
			return array(
				'status' => $message,
				'show_form' => FALSE,
		 );
		}
		break;

	case 'insert' :
		if ($target != 'child')  break;
		
		// create parent-child mapping
		$sql = "INSERT INTO {connect_data} (nid,pid) VALUES (%d,%d);";
		db_query($sql, $child->nid, $parent->nid);
		
		// record fact of current user's participation
		$_SESSION['connect_action_basic_'.$parent->nid] = TRUE;
		$_SESSION['connect_action_thanks_'.$parent->nid] = TRUE;
		break;

	case 'delete' :
		if ($target != 'child')  break;
		
		// delete parent-child mapping
		$sql = "DELETE FROM {connect_data} WHERE nid=%d;";
		db_query($sql, $child->nid);
		break;
	}
}


/**
 * Set a custom redirect page post-connect-form-submission
 */
function connect_action_redirect_submit(&$parent, &$child, $op='', $target='parent') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['variables'] = array(
        'redirect_submit_target' => array(
          '#type'  => 'textfield',
          '#size' => 20,
          '#title' => 'Target page',
          '#default_value' => connect_node_options($parent->nid, 'redirect_submit_target'),
          '#required' => TRUE,
        ),
      );
      return $return;
      break;

    case 'redirect' :
      $return = connect_node_options($parent->nid, 'redirect_submit_target');
      return $return;
      break;
  }
}


/**
 * provide a signing block in addition to or instead of the regular form
 *   - blocks are declared to Drupal in connect.module
 *   - see connect_blocks.php for block generation code
 */
/*
function connect_action_provide_block(&$parent, &$child, $op='', $target='parent') {
  switch($op) {
    case 'describe' :
      $return = array();
      $return['title'] = 'Provide a block';
      $return['desc']  = 'Makes the participation form available as a Drupal block.';
      return $return;
      break;

    case 'requires' :
      $return = array();
      $visibility = connect_node_options($parent->nid, 'provide_block_visibility');
      $visibility = empty($visibility) ? 0 : $visibility;
      
      $return['variables'] = array(
          'provide_block_text' => array(
            '#type'  => 'textfield',
            '#size' => 20,
            '#title' => 'Text for link to main node',
            '#description' => 'If blank, no link to the main node will appear in the block',
            '#default_value' => connect_node_options($parent->nid, 'provide_block_text'),
            '#required' => FALSE,
         ),
          'provide_block_noform' => array(
            '#type'  => 'checkbox',
            '#title' => 'Prevent display of main form',
            '#default_value' => connect_node_options($parent->nid, 'provide_block_noform'),
            '#required' => TRUE,
         ),
          'provide_block_visibility' => array(
            '#type'  => 'radios',
            '#title' => 'Block visibility',
            '#options' => array(
              'use regular block configuration',
              'only display on this page',
              'display everywhere except this page',
             ),
            '#default_value' => $visibility,
            '#required' => TRUE,
         ),
     );
      return $return;
      break;
      
    case 'status' :
      if (connect_node_options($parent->nid, 'provide_block_noform')) {
        return array(
          'show_form' => FALSE,
       );
      }
  }
}
*/


/**
 * Adds a CSS-based display to the parent node showing how many of the target number of participants have participated
 */
function connect_action_display_progress(&$parent, &$child, $op='', $target='parent') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['variables'] = array(
				'display_progress_goal' => array(
					'#type'  => 'textfield',
					'#size' => 20,
					'#title' => 'Target no. of participants',
					'#default_value' => connect_node_options($parent->nid, 'display_progress_goal'),
					'#required' => TRUE,
			 ),
     );
     return $return;
     break;

	case 'theme_register':
		$return = array(
			'connect_display_progress' => array(
			'arguments' => array(
					$goal => 0,
					$pct => 0,
					$pct_display => 0,
					$title => NULL,
				),
			),
		);
		return $return;
		break;
			
    case 'display' :
      if ($target != 'parent' || $parent == NULL) return;

      $done  = connect_participant_count($parent);
      $title = connect_node_options($parent->nid, 'participant_title');
      $goal  = connect_node_options($parent->nid, 'display_progress_goal');
      if ($goal > 0) {
        $pct  = round($done/$goal, 2) * 100;
        $pct_display = $pct > 100 ? 100 : $pct;
      } 
      else {
        $goal = 0;
        $pct  = 0;
        $pct_display = 0;
      }
      $return['data'] = theme('connect_display_progress', $goal, $pct, $pct_display, $title);
      return $return;
      break;
  }
}

function theme_connect_display_progress($goal, $pct, $pct_display, $title) {
  $progress_bar = <<<EOT
<div>
<div id="connect-progress-report">$goal $title</div>
<div id="connect-progress-border">
<div id="connect-progress-bar" style="width: $pct_display%;">
$pct%
</div>
</div>
</div>
EOT;
  return $progress_bar;
}


/**
 * Enforces a one person, one vote policy based on a unique identifier
 */
function connect_action_voteonce(&$parent, &$child, $op='', $target='child') {
  $message = connect_node_options($parent->nid, 'voteonce_message');
  $message = $message ? $message : t('You have already participated.');

  switch($op) {
    case 'requires' :
      $return = array();
      $return['child'] = array(
        'voteonce_identifier' => 'Unique identifier (i.e., full name, membership #, or email address)',
     );
      $return['variables'] = array(
        'voteonce_message' => array(
          '#type'  => 'textfield',
          '#title' => 'Message to display when someone attempts to participate more than once',
          '#default_value' => $message,
          '#required' => TRUE,
        ),
      );
      return $return;
      break;

    case 'validate' :
      // don't check for this when editing an existing child node
      if (isset($child->status) && $child->status == 0) return;

      $map         = connect_get_map($parent->nid);
      $test_value  = connect_value('voteonce_identifier', $parent, $child, 'child');
      $fieldname   = $map['voteonce_identifier'];
      $field_keys  = connect_get_field_keys($fieldname);
      $test_db     = _connect_get_cck_db_info($fieldname);
      $test_table  = $test_db['table'];
      $test_column = $test_db['columns'][$field_keys[0]]['column'];

      $sql   = "SELECT count(*) FROM {".$test_table."} t, {connect_data} p WHERE t.nid=p.nid AND t.$test_column = '%s' AND p.pid = %d";
      $count = db_result(db_query($sql, array($test_value, $parent->nid)));
      $already_voted = ($count != 0);

      if ($already_voted) form_set_error('', t($message));
      break;

    case 'status' :
      // if this user has already participated
      if (isset($_SESSION['connect_action_basic_'.$parent->nid])) {
        return array(
          'status' => $message,
          'show_form' => FALSE,
       );
      }
      break;
  }
}


/**
 *  provide a list of participants
 */
function connect_action_display_participants(&$parent, &$child, $op='', $target='child') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['variables'] = array(
        'display_participants_pager' => array(
          '#type'  => 'textfield',
          '#title' => 'How many participants would you like to display at a time?',
          '#default_value' => connect_node_options($parent->nid, 'display_participants_pager') ? connect_node_options($parent->nid, 'display_participants_pager') : 25,
          '#required' => TRUE,
        ),
      );
      $return['child'] = array(
        'display_participants_displayme' => 'Display my name in public lists',
      );

      // does this action use double opt-in?
      $double_opt_in = connect_value('double_optin_token', $parent, $child, 'child');
      if ($double_opt_in) {
        $return['child']['double_optin_token'] = 'Double opt-in confirmation field';
      }

      // select child fields to display
      $idx = 0;
      $child_type  = connect_node_options($parent->nid, 'participant_type');
      $cck_options = _connect_get_child_fields($child_type);
      $map  = connect_get_map($parent->nid);
      if (!empty($cck_options[$map['display_participants_displayme']])) {
        unset($cck_options[$map['display_participants_displayme']]);
      
        if (!empty($cck_options)) {
          $return['variables']['display_participants_fields_intro'] = array(
            '#value' => 'Select the items to display, in order.',
          );
          foreach ($cck_options as $name=>$title) {
            if (empty($name)) continue;
            
            $return['variables']['display_participants_fields_' . $idx] = array(
                '#type'  => 'select',
                '#title' => '',
                '#options' => $cck_options,
                '#default_value' => connect_node_options($parent->nid, 'display_participants_fields_' . $idx),
                '#required' => FALSE,
            );
            // need at least one!
            if ($idx == 0) {
              $return['variables']['display_participants_fields_' . $idx]['#required'] = TRUE;
              $return['variables']['display_participants_fields_' . $idx]['#title']    = 'participant info';
            }
            $idx++;
          }
        }
      }
      return $return;

		case 'theme_register':
			$return = array(
				'connect_action_display_participants' => array(
					'arguments' => array('parent_id' => NULL),
				),
				'connect_action_display_participants_return' => array(
					'arguments' => array('parent_title' => NULL, 'arent_nid' => NULL),
				),
			);
			return $return;
			break;

    case 'menu' :
      $return = array(
        'connect/participants' => array(
					'title' => 'Display participants',
					'type' => MENU_CALLBACK,
					'page callback' => '_connect_action_display_participants',
					'access arguments' => array('access content'),
					),
      );
      return $return;
      break;
      
    case 'display' :
      if ($target != 'parent' || $parent == NULL) return;
      
      $return['data'] = theme('connect_action_display_participants', $parent->nid);
      return $return;
      break;
  }
}

/* themeable function */
function theme_connect_action_display_participants($parent_id) {
  $link =  l(t('Display '). connect_node_options($parent_id, 'participant_title'), 'connect/participants/'.  $parent_id);
  return "<div id='connect-display-participants'>$link</div>";
}

/* themeable function */
function theme_connect_action_display_participants_return($parent_title = NULL, $parent_nid = NULL) {
  $link =  l(t('Return to ') . check_plain($parent_title), 'node/'. $parent_nid);
  return "<div id='connect-returnto-link'>&raquo; ". $link .'</div>';
}


/**
 *  allows participant-generated content to replace the parent content
 */
function connect_action_content_replace(&$parent, &$child, $op='', $target='child') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['parent'] = array(
        'data_replace_parent' => 'Content replace: the content that can be rewritten.',
     );
      $return['child']  = array(
        'data_replace_child' => 'Content replace: the participant\'s version.',
     );
      return $return;

	case 'form_alter':
		$map   = connect_get_map($parent->nid);
    $field = $map['data_replace_child'];
    $key   = connect_get_field_keys($field);
    
    // filter text if required
		$cck_info = _content_type_info();
		$cck_vars = $cck_info['content types'][connect_node_options($parent->nid, 'participant_type')]['fields'];
    $text = connect_value('data_replace_parent', $parent, $child, 'parent');
		if ($cck_vars[$field]['text_processing'] == 0) $text = strip_tags($text);
		$child[$field][0]['#default_value']['value'] = $text;
    break;

   case 'insert' :
     if ($target == 'child') {
       $addition = connect_value('data_replace_child', $parent, $child, 'child');
       connect_value('data_replace_parent', $parent, $child, 'parent', $addition);
     }
     break;
  }
}


/**
 *  allows participant-generated content to be added to the parent content
 */
function connect_action_content_append(&$parent, &$child, $op = '', $target = 'child') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['parent'] = array(
        'data_append_parent' => 'Content append: the content that can be added on to.',
     );
      $return['child']  = array(
        'data_append_child' => 'Content append: the participant\'s addition.',
     );
      return $return;

    case 'insert' :
      if ($target == 'child') {
        $original = connect_value('data_append_parent', $parent, $child, 'parent');
        $addition = connect_value('data_append_child', $parent, $child, 'child');
        connect_value('data_append_parent', $parent, $child, 'parent', "$original\n$addition");
      }
      break;
  }
}



/* Allow form to be embedded in another site */
/*
function connect_action_embed(&$parent, &$child, $op = '', $target = 'child') {
  switch ($op) {
    case 'describe' :
      $return = array();
      $return['title'] = 'Make Embeddable';
      $return['desc']  = 'Makes petition form embeddable in external site';
      return $return;
      break;
    //case 'menu' :
      //$items = array();
      //$items[] = array(
        //'path' => 'connect/embed',
        //'title' => 'Connect Embed',
        //'callback' => '_connect_embed',
        //'type' => MENU_CALLBACK,
        //'access' => TRUE,
      //);
      //return $items;
      //break;
    //case 'form_alter' : //loop through and reduce the length of textfields
     //foreach ()
     //$child[$field][0][$key[0]]['#default_value'] = connect_value('data_replace_parent', $parent, $child, 'parent');
     //return;
  }  
}

function _connect_embed($nid = NULL) {
  if (!is_numeric(arg(2)) || !arg(2)) {
    print '<p>Please specify a parent ID</p>';
    return;
  }
  $parent_node = node_load($nid);
  if (!isset($parent_node)) {
    print '<p>Invalid ID</p>';
    return;
  }
  if (!connect_is_parent_node($parent_node)){
    print '<p>Referenced node is not a parent type</p>';
    return;
  }
  $parent_node =& _connect_parent_node($parent_node);
    
  // require a CAPTCHA on the form?
  //if (!_connect_captcha_test('connect_form_block')) {
    //return $return;
  //}
    
    
  $form = drupal_get_form('connect_form_block');
  //$link = empty($text) ? '' : '<p>&raquo;&nbsp;'. l($text, "node/$nid") .'</p>';
  print '<h2>' . $parent_node->title . '</h2>' . $parent_node->body . $form;
}
*/


/**
 *  send email
 */
function connect_action_send_email(&$parent, &$child, $op = '', $target = 'child') {
  $mail_op_list = array('to', 'cc', 'bcc');
  
  switch($op) {
    case 'requires' :
      require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
      $return = array();

			$default = connect_node_options($parent->nid, 'is_live');
			$default = $default ? $default : 'no';		
			$return['variables']['is_live'] = array(
				'#type'  => 'radios',
				'#title' => t('Is this campaign ready to go live?'),
				'#description' => t('\'No\' means that no emails, faxes, etc. will not be sent; you will instead see a display of the message(s) that would have been sent.'),
				'#options' => array('yes' => 'Yes', 'no' => 'No'),
				'#default_value' => $default,
				'#required' => TRUE,
			);
       
      // stringent validation?
      $return['variables']['email_stringent'] = array(
          '#type'  => 'radios',
          '#title' => 'Use stringent email address validation?',
          '#description' => 'This requires that email addresses point to an existing, fully-qualified domain name.',
          '#options' => array('yes' => 'Yes', 'no' => 'No'),
          '#default_value' => connect_node_options($parent->nid, 'email_stringent'),
          '#required' => TRUE,
       );

      // CC the participant?
      $return['variables']['email_cc_participant'] = array(
          '#type'  => 'radios',
          '#title' => 'CC the participant?',
          '#description' => 'Should emails generated by the campaign also be CCed to the participant?',
          '#options' => array('yes' => 'Yes', 'no' => 'No'),
          '#default_value' => connect_node_options($parent->nid, 'email_cc_participant'),
          '#required' => TRUE,
       );

      // re-sending batch size
      $batch = connect_node_options($parent->nid, 'email_batch');
      $batch = $batch ? $batch : 0;
      $return['variables']['email_batch'] = array(
          '#type'  => 'textfield',
          '#title' => 'Batch size for re-sending failed emails',
          '#description' => 'How many failed emails should be re-tried in one session? Set to zero to process an unlimited number of emails.',
          '#default_value' => $batch,
       );

      // send HTML mail?
      if (module_exists('mimemail')) { 
        if (!variable_get('mimemail_alter', 0)) {
          $return['variables']['email_send_html'] = array(
            '#type'  => 'radios',
            '#title' => 'Send HTML email using the mimemail module?',
            '#options' => array('yes' => 'Yes', 'no' => 'No'),
            '#default_value' => connect_node_options($parent->nid, 'email_send_html'),
            '#required' => TRUE,
          );
        }
      } else {
        $return['variables']['email_send_html'] = array(
          '#value' => '<p><strong>Send HTML Email</strong><br />' . t('If you want to be able to send HTML emails, install the mimemail module.') . '</p>',
        );
      }

      // TO/CC salutation
      $return['variables']['email_salutation'] = array(
          '#type'  => 'radios',
          '#title' => 'Prefix a list of the recipients\' names?',
          '#description' => 'Connect can automatically add a list of the TO and CC target names above the message.',
          '#options' => array('yes' => 'Yes', 'no' => 'No'),
          '#default_value' => connect_node_options($parent->nid, 'email_salutation'),
          '#required' => TRUE,
       );
       
      // defined target
      $return['variables']['email_defined_targets'] = array (
          '#type'  => 'textarea',
          '#title' => 'Email targets',
          '#description' => 'Enter your targets one to a line, using the format "to,email@example.com,person name". You can specify "to" or "cc" or "bcc" in the first field. There must be at least one "to" address (between the defined targets and lookup target). The name element is used as the salutation at the top of the email.',
          '#default_value' => connect_node_options($parent->nid, 'email_defined_targets'),
          '#required' => FALSE,
      );

      // target lookup
      $lookup_types   = connect_get_lookup_types(TRUE);
      $lookup_actions = array('' => '');
      foreach ($mail_op_list as $action) {
        $lookup_actions[$action] = $action;
      }
      
      $return['variables']['email_lookup'] = array(
        '#type'  => 'fieldset',
        '#title' => 'Target lookup',
      );
      $return['variables']['email_lookup']['email_lookup_intro'] = array(
        '#value' => '<p>You may define one target address that will be determined on the basis of information provided by the participants (such as an elected representative corresponding to a postal code).</p>',
      );
      $return['variables']['email_lookup']['email_lookup_action'] = array(
          '#type'  => 'select',
          '#title' => 'Email action',
          '#options' => $lookup_actions,
          '#default_value' => connect_node_options($parent->nid, 'email_lookup_action'),
          '#required' => FALSE
      );
      $return['variables']['email_lookup']['email_lookup_type'] = array(
          '#type'  => 'select',
          '#title' => 'Type of lookup',
          '#options' => $lookup_types,
          '#default_value' => connect_node_options($parent->nid, 'email_lookup_type'),
          '#required' => FALSE,
      );

      // participant signature
      $child_type   = connect_node_options($parent->nid, 'participant_type');
      $child_fields = _connect_get_child_fields($child_type);
      $return['variables']['email_signature'] = array(
        '#type'  => 'fieldset',
        '#title' => 'Signature',
      );
      $return['variables']['email_signature']['email_signature_intro'] = array(
        '#value' => '<p>Select the participant fields that will be appended to the message as a signature</p>',
      );
      for ($row = 1; $row <= 3; $row++) {
        $return['variables']['email_signature']["email_signature_{$row}"] = array(
          '#type'  => 'fieldset',
          '#title' => "Row $row",
        );
        for ($col = 1; $col <= 2; $col++) {
          $return['variables']['email_signature']["email_signature_{$row}"]["email_signature_{$row}_{$col}"] = array(
            '#type'  => 'select',
            '#title' => '',
            '#options' => $child_fields,
            '#default_value' => connect_node_options($parent->nid, "email_signature_{$row}_{$col}"),
            '#required' => FALSE,
          );
        }
      }
      
      $return['parent'] = array(
        'email_subject' => 'Send email: the subject line of the email',
        'email_body'    => 'Send email: the body of the email',
      );
      $return['child']  = array(
        'email_from'   => 'Participant\'s email address',
        'email_defined_result' => 'Send email: record the e-mail success/failure message',
        //'email_cc_me' => 'Send email: should the email be CCed to the participant?',
      );

      // add requirements from lookup type, if set
      $lookup = connect_target_lookup($parent, $child, 'requires', 'email');
      if (is_array($lookup)) {
        foreach(array('variables','parent','child') as $key) {
          if (isset($lookup[$key])) {
            foreach($lookup[$key] as $newkey=>$add) {
              if ($key == 'variables') {
                $return[$key]['email_lookup'][$newkey] = $add;
              }
              else {
                $return[$key][$newkey] = $add;
              }
            }
          }
        }
      }
      
      return $return;
      break;

    /*
    case 'menu' :
      $return = array(
        array(
          'path' => 'connect/email_resend',
          'title' => 'Re-send email',
          'callback' => '_connect_action_send_email_resend_failed',
          'type' => MENU_LOCAL_TASK,
					'access arguments' => array('administer content'),
        ),
      );
      return $return;
      break;
      */
      
    case 'menu' :
      break;

    case 'admin-validate' :
			// are we imposing extra tests for email address validity?
      $stringent = (connect_node_options($parent->nid, 'email_stringent') == 'yes');
 
      // check targets
      $targets   	= _connect_parse_email_targets($parent->data['campaign_variables']['variables_connect_action_send_email']['email_defined_targets']);
      if (!empty($targets)) {
				$processed 	= array();
				$count 			= 0;
				foreach ($targets as $target) {
					// type of email activity
					if (!in_array($target['type'], $mail_op_list)) {
						form_set_error('email_defined_targets_'. $count++, 'Email targets: "'. htmlentities($target['type']) . '" must be one of ' . implode(', ', $mail_op_list));
					}
					
					// address correctness
					if (($stringent && !_connect_valid_email_strict($target['email'])) || !valid_email_address($target['email'])) {
						form_set_error('email_defined_targets_'. $count++, 'Email targets: "'. htmlentities($target['email']) .'" is not a valid email address');
					}
					elseif (in_array($target['email'], $processed)) {
						form_set_error('email_defined_targets_'. $count++, 'Email targets: "'. htmlentities($target['email']) .'" should not appear more than once in the list');
					}
					else {
						$processed[] =$target['email'];
					}

					// name
					
				}
			}
			else {
				$lookup_action = $parent->data['campaign_variables']['variables_connect_action_send_email']['email_lookup_action'];
				$lookup_type  = $parent->data['campaign_variables']['variables_connect_action_send_email']['email_lookup_type'];
				if (empty($lookup_action) || empty($lookup_type)) form_set_error('email_defined_targets', 'You do not have any targets defined for your email function. Please set up a defined or a lookup target.');
			}
			
      break;
      
    case 'validate' :
      if ($target != 'child') {
        return;
      }
      $stringent = (connect_node_options($parent->nid, 'email_stringent') == 'yes');
      $email = connect_value('email_from', $parent, $child, 'child');
      if (($stringent && !_connect_valid_email_strict($email)) || (!$stringent && !valid_email_address($email))) {
        form_set_error('', 'Please enter a valid email address');
      }
      // call validation from lookup type
      $lookup_type = connect_node_options($parent->nid, 'email_lookup_type');
      if ($lookup_type) {
        connect_target_lookup($parent, $child, 'validate', 'email');
      }
      break;

    case 'insert' : 
      if ($target != 'child') {
        return;
      }
      $active = (connect_node_options($parent->nid, 'is_live') == 'yes');
      $html   = (connect_node_options($parent->nid, 'email_send_html') == 'yes');
      $addresses  = array();
      $headers    = array();
      $salutation = array();

      // entities for adding elements to html or plaintext emails
      $br    = $html ? "<br />\r\n\r\n" : "\r\n\r\n";
      $p_on  = $html ? '<p>' : '';
      $p_off = $html ? "</p>\r\n\r\n" : "\r\n\r\n";

      // direct targets
      $targets   = _connect_parse_email_targets(connect_node_options($parent->nid, 'email_defined_targets'));
      foreach ($targets as $target) {
        if (!empty($target['email']) && in_array($target['type'], $mail_op_list)) {
          eval('$addresses[' . $target['type'] . '][] = $target[\'email\'];');
          $salutation[$target['type']][] = $target['name'];
        }
      }

      // lookup target
      require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
      $target = connect_target_lookup($parent, $child, 'lookup', 'email');      
      $target['type'] = connect_node_options($parent->nid, 'email_lookup_action');
      if (!empty($target['email']) && in_array($target['type'], $mail_op_list)) {
        eval('$addresses[' . $target['type'] . '][] = $target[\'email\'];');
        $salutation[$target['type']][] = $target['name'];
      }

      // set salutation string
      $salute = '';
      if (connect_node_options($parent->nid, 'email_salutation') == 'yes') {
        $salute = $p_on . 'TO: ' . implode(', ', $salutation['to']) . $p_off;
        if (!empty($salutation['cc'])) {
          $salute .= $p_on . 'CC: ' . implode(', ', $salutation['cc']) . $p_off;
        }
      }

      // set signature
      $signature = '';
      for ($row = 1; $row <= 3; $row++) {
        $line = '';
        for ($col = 1; $col <= 2; $col++) {
          $field = connect_node_options($parent->nid, "email_signature_{$row}_{$col}");
          if ($field) {
            $path = _connect_get_field_path($child, $field);
            eval('$temp = $child'. $path .';');
            if ($temp) {
              $line .= check_plain($temp) . ' ';
            }
          }
        }
        if ($line) {
          $signature .= "$line$br";
        }
      }
      $signature = $signature ? "\r\n\r\n$p_on$signature$p_off" : '';

      // do we cc the participant?
      if (_connect_positive_value(connect_node_options($parent->nid, 'email_cc_participant'))) {
        $addresses['cc'][] = connect_value('email_from', $parent, $child, 'child');
      }

      // basic message elements
      $message->to      = implode(', ', $addresses['to']);
      $message->from    = connect_value('email_from', $parent, $child, 'child');
      $message->subject = connect_value('email_subject', $parent, $child, 'parent');
      $message->body    = $salute . connect_value('email_body', $parent, $child, 'parent') . $signature;

      // add CC, BCC headers
      if (!empty($addresses['cc'])) {
        $headers['CC'] = implode(', ', $addresses['cc']);
      }
      if (!empty($addresses['bcc'])) {
        $headers['BCC'] = implode(', ', $addresses['bcc']);
      }
      $message->headers = $headers;

      // send it
      $result = _connect_send_email($message, $html, $active);

      // save result
      $_SESSION['connect_'.$parent->nid.'_email_sent'] = $result ? implode(', ', $salutation['to']) : FALSE;
      $result = $result ? "Success" : "Failed";
      connect_value('email_defined_result', $parent, $child, 'child', $result);
      node_save($child);
      break;

    case 'status' :
      $message  = '';
      if (isset($_SESSION['connect_'.$parent->nid.'_email_sent'])) {
        $message = $_SESSION['connect_'.$parent->nid.'_email_sent'] ? 'Your email message was sent to: ' .$_SESSION['connect_'.$parent->nid.'_email_sent'] : 'Sorry, but there was a problem sending the message. The problem will be investigated, and your message will be sent when the problem is resolved.';
        unset($_SESSION['connect_'.$parent->nid.'_email_sent']);
      }
      return array(
        'status' => $message
     );
      break;
  }
}

/*
function _connect_action_send_email_resend_failed_page(){
  drupal_set_title('Re-send failed e-mails');

  $output  = "<p>This utility allows you to re-send messages for any failed e-mail deliveries.</p>";
  $output .= "";
  
  return $output;
}
*/

/**
 * utility to re-send mail from participants with failed results
 **/
function _connect_action_send_email_resend_failed() {
  $p_nid  = arg(1);
  $parent = node_load($p_nid);
  if (!$parent || !connect_is_parent_node($parent)) return;

  $batch = (int) connect_node_options($parent->nid, 'email_batch');
  $done  = 0;
  
  $report_header = array('participant email', 'result');
  $report_data   = array();
  
  $sql    = "SELECT nid FROM {connect_data} WHERE pid = %d";
  $result = db_query($sql, $p_nid);
  while ($row = db_fetch_object($result)) {
    $child = node_load($row->nid);
    if (connect_value('email_defined_result', $parent, $child, 'child') == "Failed") {
      //set lock to prevent connect_nodeapi from firing;  
      $_SESSION['connect_child_lock_insert'] = TRUE;
      connect_call_hooks($parent, $child, 'insert');
      unset($_SESSION['connect_child_lock_insert']);
      $report_data[] = array(
        l(connect_value('email_from', $parent, $child, 'child'), 'node/' . $child->nid),
        connect_value('email_defined_result', $parent, $child, 'child')
      );
      $done ++;
      if ($batch && $done >= $batch) break;
    }
  }

  if (empty($report_data)) {
    $output .= "<p>There were no failed emails to re-send.</p>\n";
  }
  else {
    $output  = "<p>$done emails were re-sent</p>\n";
    $output .= theme_table($report_header, $report_data);
  }
  return $output;
}


/**
 * Sends fax (using the myfax.com service) to a specific name and fax no. defined in the parent node.
 */
function connect_action_myfax_defined(&$parent, &$child, $op='', $target='child') {
  switch($op) {
    case 'requires' :
      $return = array();
			$default = connect_node_options($parent->nid, 'is_live');
			$default = $default ? $default : 'no';	
      $return['variables']  = array(
				'is_live' => array(
					'#type'  => 'radios',
					'#title' => t('Is this campaign ready to go live?'),
					'#description' => t('\'No\' means that no emails, faxes, etc. will not be sent; you will instead see a display of the message(s) that would have been sent.'),
					'#options' => array('yes' => 'Yes', 'no' => 'No'),
					'#default_value' => $default,
					'#required' => TRUE,
				),
        'fax_to_number' => array (
          '#type'  => 'textfield',
          '#size' => 20,
          '#title' => 'Target fax no.',
          '#default_value' => connect_node_options($parent->nid, 'fax_to_number'),
          '#required' => TRUE,
       ),
        'fax_to_name' => array (
          '#type'  => 'textfield',
          '#size' => 20,
          '#title' => 'Target name',
          '#default_value' => connect_node_options($parent->nid, 'fax_to_name'),
          '#required' => TRUE,
       ),
        'myfax_email' => array(
          '#type'  => 'textfield',
          '#title' => 'MyFax account \'from\' email address',
          '#default_value' => connect_node_options($parent->nid, 'myfax_email'),
          '#required' => TRUE,
       ),
        'myfax_password' => array(
          '#type'  => 'textfield',
          '#title' => 'MyFax password',
          '#default_value' => connect_node_options($parent->nid, 'myfax_password'),
       ),
     );
      $return['parent'] = array(
        'fax_subject' => 'Fax subject',
        'fax_body'    => 'Fax body',
     );
      $return['child']  = array(
        'fax_direct_result' => 'MyFax (direct) success/failure message',
        'fax_from'   => 'Fax \'from\' name',
     );
      return $return;
      break;

    case 'validate' :
      if ($target != 'child') {
        return;
      }
      $name = connect_value('fax_from', $parent, $child, 'child');
      if (empty($name)) {
        form_set_error('', 'Please enter your name.');
      }
      break;

    case 'insert' :
      if ($target != 'child') {
        return;
      }

      $fax = array();
      $fax['from_mail'] = connect_node_options($parent->nid, 'myfax_email');
      $fax['to_fax']    = connect_node_options($parent->nid, 'fax_to_number');
      $fax['to_name']   = connect_node_options($parent->nid, 'fax_to_name');
      $fax['password']  = connect_node_options($parent->nid, 'myfax_password');
      $fax['body']      = connect_value('fax_body', $parent, $child, 'parent');
      $fax['subject']   = connect_value('fax_subject', $parent, $child, 'parent');
      $fax['from_name'] = connect_value('fax_from', $parent, $child, 'child');

      $message = connect_myfax_prepare($fax);
      $active  = (connect_node_options($parent->nid, 'is_live') == 'yes');
      $result  = _connect_send_email($message, FALSE, $active);

      // save result
      $_SESSION['connect_'.$parent->nid.'_direct_fax_sent'] = $result;
      $result = $result ? "Success" : "Failed";
      connect_value('fax_direct_result', $parent, $child, 'child', $result);
      node_save($child);
      break;

    case 'status' :
      $message  = '';
      if (isset($_SESSION['connect_'.$parent->nid.'_direct_fax_sent'])) {
        $message = $_SESSION['connect_'.$parent->nid.'_direct_fax_sent'] ? 'Your fax message was sent!' : 'Sorry, but there was a problem sending the message. The problem will be investigated, and your message will be sent when the problem is resolved.';
        unset($_SESSION['connect_'.$parent->nid.'_direct_fax_sent']);
      }
      return array(
        'status' => $message
     );
      break;
      break;
  }
}

/**
 * Sends fax (using the myfax.com service) to a target determined by the participant\'s information.
 */
function connect_action_myfax_lookup(&$parent, &$child, $op='', $target='child') {
  switch($op) {
    case 'requires' :
      require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
      $return = array();
      
      
			$default = connect_node_options($parent->nid, 'is_live');
			$default = $default ? $default : 'no';	
      $options = connect_get_lookup_types(TRUE);
      $base['variables']  = array(
				'is_live' => array(
					'#type'  => 'radios',
					'#title' => t('Is this campaign ready to go live?'),
					'#description' => t('\'No\' means that no emails, faxes, etc. will not be sent; you will instead see a display of the message(s) that would have been sent.'),
					'#options' => array('yes' => 'Yes', 'no' => 'No'),
					'#default_value' => $default,
					'#required' => TRUE,
				),
        'myfax_lookup_type' => array(
          '#type'  => 'select',
          '#title' => 'MyFax target lookup',
          '#options' => $options,
          '#default_value' => connect_node_options($parent->nid, 'myfax_lookup_type'),
          '#required' => TRUE,
       ),
        'myfax_email' => array(
          '#type'  => 'textfield',
          '#title' => 'MyFax account \'from\' email address',
          '#default_value' => connect_node_options($parent->nid, 'myfax_email'),
          '#required' => TRUE,
       ),
        'myfax_password' => array(
          '#type'  => 'textfield',
          '#title' => 'MyFax password',
          '#default_value' => connect_node_options($parent->nid, 'myfax_password'),
       ),
     );
      $base['parent'] = array(
        'fax_subject' => 'Fax subject',
        'fax_body'    => 'Fax body',
     );
      $base['child']  = array(
        'fax_lookup_result' => 'MyFax (lookup) success/failure message',
        'fax_from'   => 'Fax \'from\' name',
     );

      // add requirements from lookup type
      $lookup = connect_target_lookup($parent, $child, 'requires', 'myfax');
      if (is_array($lookup)) {
        foreach(array('variables','parent','child') as $key) {
          if (isset($lookup[$key])) {
            $return[$key] = $base[$key]+$lookup[$key];
          } else {
            $return[$key] = $base[$key];
          }
        }
      } else {
        $return = $base;
        drupal_set_message('You have not selected a lookup type. Please do so and then adjust any new settings that are required.');
      }
      return $return;
      break;

    case 'validate' :
      if ($target != 'child') {
        return;
      }
      $name = connect_value('fax_from', $parent, $child, 'child');
      if (empty($name)) {
        form_set_error('', 'Please enter your name.');
      }
      // call validation from lookup type
      connect_target_lookup($parent, $child, 'validate', 'myfax');
      break;

    case 'insert' :
      if ($target != 'child') {
        return;
      }
      require_once(drupal_get_path('module','connect') . '/connect_lookup.php');
      $target  = connect_target_lookup($parent, $child, 'lookup', 'myfax');
      if ($target && isset($target['fax']) && strlen($target['fax']) >= 10) {

        $fax = array();
        $fax['from_name'] = connect_value('fax_from', $parent, $child, 'child');
        $fax['from_mail'] = connect_node_options($parent->nid, 'myfax_email');
        $fax['to_fax']    = $target['fax'];
        $fax['to_name']   = $target['name'];
        $fax['body']      = connect_value('fax_body', $parent, $child, 'parent');
        $fax['password']  = connect_node_options($parent->nid, 'myfax_password');
        $fax['subject']   = connect_value('fax_subject', $parent, $child, 'parent');

        $message = connect_myfax_prepare($fax);
        $active  = (connect_node_options($parent->nid, 'is_live') == 'yes');
        $result  = _connect_send_email($message, FALSE, $active);

      } else {
        $result = FALSE;
      }

      // save result
      $_SESSION['connect_'.$parent->nid.'_myfax_lookup_sent'] = $result ? $target['name'] : FALSE;
      $result = $result ? "Success" : "Failed";
      connect_value('fax_lookup_result', $parent, $child, 'child', $result);
      node_save($child);
      break;

    case 'status' :
      $message  = '';
      if (isset($_SESSION['connect_'.$parent->nid.'_myfax_lookup_sent'])) {
        $message = $_SESSION['connect_'.$parent->nid.'_myfax_lookup_sent'] ? 'Your fax message was sent to ' .$_SESSION['connect_'.$parent->nid.'_myfax_lookup_sent'] : 'Sorry, but there was a problem sending the message. The problem will be investigated, and your message will be sent when the problem is resolved.';
        unset($_SESSION['connect_'.$parent->nid.'_myfax_lookup_sent']);
      }
      return array(
        'status' => $message
     );
      break;
      break;
  }
}

/*
 *  Send an email to participant and record confirmation, if email is confirmed
 *
 * TODO: create a token automatically to ID this participation (use form id?)
 * 
 * TODO: add 'require opt-in' option globally to action
 *  - if turned on, every action would have to store its final step in the connect_queue table
 *  - on confirmation of the token, all the connect_queue items with token = $token will be fired
 *
 *
 * connect_queue
 * qid
 * token
 * function
 * arguments
 * 
 */
function connect_action_double_optin(&$parent, &$child, $op='', $target='child') {
  switch($op) {
    case 'requires' :
      $return = array();
      $return['variables']  = array(
        'double_optin_email_subject' => array (
          '#type'  => 'textfield',
          '#size' => 40,
          '#title' => 'Opt-in email subject line',
          '#default_value' => connect_node_options($parent->nid, 'double_optin_email_subject'),
          '#required' => TRUE,
        ),
        'double_optin_email_text' => array (
          '#type'  => 'textarea',
          '#title' => 'Opt-in email text',
          '#default_value' => connect_node_options($parent->nid, 'double_optin_email_text'),
          '#required' => TRUE,
        ),
        'double_optin_message' => array (
          '#type'  => 'textfield',
          '#size' => 40,
          '#title' => 'Opt-in confirmation message',
          '#default_value' => connect_node_options($parent->nid, 'double_optin_message'),
          '#required' => TRUE,
        ),
      );
      $return['child']  = array(
        'email_from'         => 'Participant\'s email',
        'double_optin_token' => 'Double opt-in confirmation field',
      );
      return $return;
      break;

    case 'menu' :
      $return = array(
        'connect/opt-in' => array(
          'title' => 'Double opt-in',
          'page callback' => '_connect_action_process_opt_in',
          'type' => MENU_CALLBACK,
					'access arguments' => array('access content'),
        ),
      );
      return $return;
      break;

    case 'insert' :
      if ($target != 'child') {
        return;
      }
      // create token, store in $child
      $token = md5(rand() . md5(rand() . time()));
      connect_value('double_optin_token', $parent, $child, 'child', $token);
      node_save($child);

      // create confirmation URL, append to email text
      global $base_url;
      $URL = "\n\n". $base_url .'/connect/opt-in/'. $parent->nid .'/'. $child->nid .'/'. $token ."\n\n";

      // compose email
      $message->to      = connect_value('email_from', $parent, $child, 'child');
      $message->from    = variable_get('site_mail', ini_get('sendmail_from'));
      $message->subject = connect_node_options($parent->nid, 'double_optin_email_subject');
      $message->body    = connect_node_options($parent->nid, 'double_optin_email_text') . $URL;

      // send mail
      $active  = (connect_node_options($parent->nid, 'is_live') == 'yes');
      $result  = _connect_send_email($message, FALSE, $active);
      break;
  }
}


/*
 *  callback to process double opt-in
 */
function _connect_action_process_opt_in($pid, $cid, $token) {
  if (!is_numeric($pid) || !is_numeric($cid)) {
    drupal_goto('node');
  }
  $parent = node_load($pid);
  $child  = node_load($cid);

  if ($parent && $child && connect_get_parent($child) == $pid) {
    $test = connect_value('double_optin_token', $parent, $child, 'child');
    if ($test == $token) {
      connect_value('double_optin_token', $parent, $child, 'child', 'yes');
      node_save($child);      
      drupal_set_message(connect_node_options($parent->nid, 'double_optin_message'));
    }    
  }
  drupal_goto("node/$pid");
}



/**** Utils ****/

/**
 *  turn fax information into an email message for the myfax service
 */
function connect_myfax_prepare($fax) {
  $faxNumber = preg_replace('/[^0-9]/','',$fax['to_fax']);
  if (strlen($faxNumber) < 10) {
    return FALSE;
  } elseif (strlen($faxNumber)==10) {
     // add default country code
    $faxNumber = '1'.$faxNumber;
  }

  $body = '';
  if (!empty($fax['password'])) {
    $body .= "$fax[password]\r\n \r\n";
  }
  $body .= "To: $fax[to_name] ($fax[to_fax])\r\n";
  $body .= "From: $fax[from_name]\r\n";
  $body .= "Subject: $fax[subject]\r\n\r\n";
  $body .= "$fax[body]\r\n\r\n$fax[from_name]";

  $message->to      = "$faxNumber@myfax.com";
  $message->from    = "$fax[from_name] <$fax[from_mail]>";
  $message->subject = $fax['to_name'];
  $message->body    = $body;

  return $message;
}

/*
 *   send or log email message
 */
function _connect_send_email(&$message, $send_html = FALSE, $send_mail = FALSE) {
  // sanity checks
  $send_html = ($send_html && module_exists('mimemail'));
  if (!isset($message->headers)) $message->headers = NULL;
  
  // filter content
  if ($send_html) {
    $message->body = filter_xss($message->body);
  }
  else {
    $message->body = strip_tags($message->body);
  }

	// are we just testing?
  if (!$send_mail) {
    watchdog('connect_send_email', '<pre>' . print_r($message, TRUE) . '</pre>');
    return TRUE;
  }
	
  // send it
	if ($message->subject && $message->body && $message->to && $message->from) {
		if ($send_html) {
			return mimemail($message->from, $message->to, $message->subject, $message->body, FALSE, $message->headers);
		}
		else {
			// turn the message object into param for drupal_mail_send
			$message_array = (array)$message;
			$message_array['id'] = 'connect_send_email';
			return drupal_mail_send($message_array);
		}
	}
	else {
		return FALSE;
	}
}


/**
 *  validation wrapper that allows 'name <address>' format email addresses
 */
function _connect_valid_email($address) {
  $regex = '/(.*)\s?\<(.*)\>/';
  $expanded_format = preg_match($regex, $address, $matches);
  if ($expanded_format) $address = $matches[2];
  return valid_email_address($address);
}


/**
 *  validation wrapper that tests for existing FQDN
 */
function _connect_valid_email_strict($address) {
  $FQDN = '/^(?:[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9])+(?:\.(?:[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9])+)*(?:\.[a-zA-Z]{2,})$/';
  list($mailbox,$domain) = split('@', $address);
  if(!preg_match($FQDN, $domain) || !checkdnsrr($domain .'.', 'MX')) {
    return FALSE;
  }
  return valid_email_address($address); 
}



/*
 *  turns structured textarea data into an array of address arrays
 */
function _connect_parse_email_targets($textarea) {
  $return  = array();
  $targets = split("\n", $textarea);
  foreach ($targets as $target) {
    if (!empty($target)) {
      list($type, $email, $name) = split(',', $target);
      $return[] = array(
        'type'  => strtolower(trim($type)),
        'email' => strtolower(trim($email)),
        'name'  => trim($name),
     );
    }
  }
  return $return;
}


// for debugging -- clears child nodeapi lock before dying
function _connect_die($op) {
  unset($_SESSION["connect_child_lock_$op"]);
  die();
}
