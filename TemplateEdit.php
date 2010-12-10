<?php 

Class TemplateEdit {

	// Connector for Code Igniter
	private $CI = NULL;

	private $patternList = NULL;
	private $values = array();
	private $messages= array();

	private $allOK = FALSE;

	private $uses_upload = FALSE;

	private $upload_config = array(
		'upload_path'	=> NULL,
		'allowed_types'	=> NULL,
		'max_size'		=> NULL,
		'max_width'		=> NULL,
		'max_height'	=> NULL,
		'overwrite'		=> NULL,
		'remove_spaces'	=> NULL,
	);

	private $_extraButtonsValidate = array();

	public function __construct($params = array())
	{
		if( ! count($params) > 0 ) {
			$context = implode('::', array(__FILE__,__LINE__,__FUNCTION__)) . '::';
			$msg = $context . "FATAL: Parameter list is empty";
			die($msg);
		}

		if( ! isset($params['patternFile'] ) ) {
			$context = implode('::', array(__FILE__,__LINE__,__FUNCTION__)) . '::';
			$msg = $context . "FATAL: No pattern file specified";
			die($msg);
		}

		$patternFile = $params['patternFile'];

		$this-> loadDefs($patternFile);
		if( $this->patternList === NULL ) {
			$context = implode('::', array(__FILE__,__LINE__,__FUNCTION__)) . '::';
			$msg = $context . "FATAL: patternList is not Initialized";
			die($msg);
		}
		
		$this->CI = get_instance();

		foreach($params as $p_name => $p_val) {
			switch($p_name) {

			case 'upload_config':
				break;

			default:
				break;
			}
		}
	}

	private function loadDefs($file)
	{
		// TODO: Introduce caching 

		$x = dirname(__FILE__) . '/../../..';

		require_once $x . '/lib/sfYaml/lib/sfYaml.php';
		require_once $x . '/lib/sfYaml/lib/sfYamlParser.php';
		require_once $x . '/lib/sfYaml/lib/sfYamlDumper.php';
		require_once $x . '/lib/sfYaml/lib/sfYamlInline.php';

		$loader = sfYaml::load($file);

		$this->patternList = $loader;
	}

	public function mkFieldsReadOnly($what = array())
	{
		if(! is_array($what) ) {
			if( ! is_scalar($what) ) {
				// For now ignore wring arguments
				return NULL;
			}
			$x = $what;
			$what = array();
			$what[] = $x;
		}

		if( count($what) === 0 ) {
			return NULL;
		}

		// Assert: $what is array and not empty

		// Remove all fields that are not in the Template
		$what2 = array();
		$dictionary = $this->patternList['FieldList'];
		foreach($dictionary['sequence'] as $field_name) {
			if( ! $dictionary['fields'][$field_name] ) {
				continue;
			}

			$template_def = $dictionary['fields'][$field_name];

			if( in_array($field_name,$what)) {
				$what2[] = $field_name;
			}
		}

		if( count($what2) === 0 ) {
			return NULL;
		}

	 	// We have work to do
		foreach($what2 as $field_name) {
			// print "READONLY: $field_name";
			$this->patternList['FieldList']['fields'][$field_name]['html_hints']['readonly'] = TRUE;
		}

		return NULL;
	}

	public function registerExtraValidateButtons($buttonNames = array() ) 
	{
		if(! is_array($buttonNames) ) {

			if( ! is_scalar($buttonNames) ) {
				// For now ignore wring arguments
				return NULL;
			}

			$x = $buttonNames;
			$buttonNames = array();
			$buttonNames[] = $x;
		}

		if( count($buttonNames) === 0 ) {
			return NULL;
		}

		foreach($buttonNames as $name) {

			if( ! is_string($name)) {
				continue;
			}

			$this -> _extraButtonsValidate[] = $name;
		}

		return NULL;
	}

	public function makeEditScreen($messages = array())
	{
		$x_a = array();

		$dictionary = $this->patternList['FieldList'];

		$me = $_SERVER['PHP_SELF'];

		////////////////////////////////////////////
		////////////////////////////////////////////
		////////////////////////////////////////////
		// render any uplevel messages 

		$uplevel_message_ok_s = '';
		$my_OK = NULL;
		if( count($this->values) !== 0 AND count($this->messages) === 0 AND count($messages) === 0 ) {
			// it looks like all data has been validated
			$my_OK = "Automatic validation OK";
			$uplevel_message_ok_s = <<<EOS
			<div class="message_ok">
				$my_OK
			</div>
EOS;
		}
	
		$uplevel_message_a = array();
		if( is_array($messages) AND count($messages) > 0 ) {
			$uplevel_message_a[] = <<<EOS
			<div class="message_section">
EOS;
			foreach( $messages as $message ) {
				$uplevel_message_a[] = <<<EOS
				<div class="error_message">
					$message
				</div>
EOS;
			}

			$uplevel_message_a[] = <<<EOS
			</div>
EOS;
		}

		$uplevel_message_s = implode("\n", $uplevel_message_a);

		////////////////////////////////////////////
		////////////////////////////////////////////
		////////////////////////////////////////////

		$x_a[] = <<<EOS


	<div class="form_group" >
		$uplevel_message_s
		$uplevel_message_ok_s
		<form action="$me" method="POST" enctype="multipart/form-data" >
			<fieldset>
				<legend> Fields </legend>
EOS;

		$z_name_base = "form_data";

		foreach($dictionary['sequence'] as $field_name) {
			if( ! $dictionary['fields'][$field_name] ) {
				continue;
			}

			$template_def = $dictionary['fields'][$field_name];
			$template_name = $field_name;

			$label = $template_name;
			if( isset($template_def['label']) ) {
				$label = $template_def['label'];
			}

			// ---------------------------------------
			// what value to show , if no value but we have a default show the default
			$name = $template_name;
			$value = '';
			if( isset($this->values[$name])) {
				$value = $this->values[$name];
			}

			$def_value = NULL;
			if( isset( $dictionary['fields'][$field_name]['value_constraints']['value_default'] ) ) {
				$def_value = $dictionary['fields'][$field_name]['value_constraints']['value_default'];
			}

			if( ! $value AND $def_value !== NULL ) {
				$value = $def_value;
			}

			// ---------------------------------------

			$id = "id_$name";

			$title = "No helptext defined for this element";
			if( isset($template_def['help_text']) ) {
				$title = $template_def['help_text'];
			}

			$html_type = $template_def['html_type'];
			$class = 'input_' . $html_type;

			$z_name = $z_name_base . '[' . $name . ']';

			$x_msg = '';
			if( isset($this->messages[$field_name]) ) {
				$x_msg = '<div class="error_message" >' . $this->messages[$field_name] . '</div>';
			}

			switch($html_type) {

			case 'text':
			case 'password':
				$size = 10;	// some minimal size of the field just in case there is none defined in the yml file

				if(isset( $template_def['html_hints'] ) ) {
					if( isset($template_def['html_hints']['size']) ) {
						$size = $template_def['html_hints']['size'] * 1;
					}
				}

				$maxlength = 0;
				if(isset( $template_def['html_hints'] ) ) {
					if( isset($template_def['html_hints']['maxlength']) ) {
						$maxlength = $template_def['html_hints']['maxlength'] * 1;
					}
				}

				$x_max = '';
				if( $maxlength > 0 ) {
					$x_max = 'maxlength="' . $maxlength . '"';
				}

				$class = "form_entry";
				if( isset($template_def['html_hints']) AND isset($template_def['html_hints']['class']) ) {
					$class = $template_def['html_hints']['class'];
				}

				$x_readonly = '';
				if( 
					isset($template_def['html_hints']['readonly'] ) AND 
					$template_def['html_hints']['readonly'] === TRUE
				) {
					$x_readonly = 'readonly="readonly"';
				}
				// Render the Field with possible message and possible value
				$x_a[] = <<<EOS
					<div class="form_entry">
						$x_msg
						<label class="label" for="$id">$label</label>
						<span class="pre_feedback">&nbsp</span>
						<input id="$id" class="$class" type="$html_type" name="$z_name" value="$value" title="$title" size="$size" $x_max $x_readonly />
					</div>
EOS;
				break;

			case 'hidden':
				$x_a[] = <<<EOS
					<input type="$html_type" name="$z_name" value="$value" />
EOS;
				break;

			case 'file':
				$size = 10;	// some minimal size of the field just in case there is none defined in the yml file

				if(isset( $template_def['html_hints'] ) ) {
					if( isset($template_def['html_hints']['size']) ) {
						$size = $template_def['html_hints']['size'] * 1;
					}
				}

				$maxlength = 0;
				if(isset( $template_def['html_hints'] ) ) {
					if( isset($template_def['html_hints']['maxlength']) ) {
						$maxlength = $template_def['html_hints']['maxlength'] * 1;
					}
				}

				$x_max = '';
				if( $maxlength > 0 ) {
					$x_max = 'maxlength="' . $maxlength . '"';
				}

				$z_name = $field_name;

				$x_a[] = <<<EOS
					<div class="form_entry">
						$x_msg
						<label class="label" for="$id">$label</label>
						<span class="pre_feedback"></span>
						<input id="$id" class="$class" type="file" name="$z_name" title="$title" size="$size" $x_max />
						<span class="file_upload_value"> $value </span>
					</div>
EOS;

				break;

			case 'textarea':

				$rows = 3;
				$cols = 25;

				if(isset( $template_def['html_hints'] ) ) {
					if( isset($template_def['html_hints']['rows']) ) {
						$rows = $template_def['html_hints']['rows'] * 1;
					}
					if( isset($template_def['html_hints']['columns']) ) {
						$cols = $template_def['html_hints']['columns'] * 1;
					}
				}

				if( $rows < 1 ) {
					$rows = 3;
				}
				if( $cols < 10 ) {
					$cols = 10;
				}

				$x_a[] = <<<EOS
					<div class="form_entry">
						$x_msg
						<label class="label" for="$id">$label</label>
						<span class="pre_feedback">&nbsp</span>
						<textarea id="$id" class="$class" name="$z_name" rows="$rows" cols="$cols" title="$title">$value</textarea>
					</div>
EOS;
				break;

			case 'select':
				// mboot; 13-09-2010; create code for multi select and use select_separator to process results
				$list_types = array('inline', 'function');

				$list_type = 'inline';
				if( isset( $template_def['value_constraints']['value_select_list_type']) ) {
					$list_type = $template_def['value_constraints']['value_select_list_type'];
					if( ! in_array($list_type,$list_types) ) {
						continue;
					}
				}

				$list_name = '';
				if( isset( $template_def['value_constraints']['value_select_list_name'] ) ) {
					$list_name = $template_def['value_constraints']['value_select_list_name'];
				}

				if( $list_name === '' ) {
					continue;
				}

				switch($list_type) {

				case 'inline':
					if( ! isset( $this->patternList['InlineLists'][$list_name] ) ) {
						continue;
					}

					$value_def = $this->patternList['InlineLists'][$list_name];
					break;

				case 'function':
					$value_def = $list_name();
					// must return both value list and default
					// array( 'values' => array(), 'default => value)
					continue;
					break;

				case 'ClassMethod':
					// see function
					// in this case both a class and a method must be specified
					// $tmpHandle = new $className();
					// $value_def = $tmpHandle -> $methodName();
					continue;

				default:
					continue;
					break;

				}

				/*
					possible keys that can be defined by $value_def:
						$value_default = $value_def['default'];				// a posible default value to preselect if there is no current selected value given
						$value_list = $value_def['values'];					// ths list of (key,values) that we can choose from (label,value) style
						$select_single = $value_def['select_single'];		// is this a single or a multi select list
						$select_list_size = $value_def['select_list_size']; // how many values will we show on screen; use 'all' to avoid scrolling
						$select_separator = $value_def['select_separator'];	// how will wwe combine the selected values so they can be passed around later
				*/
				$current_value = $value;

				$value_default = '';
				if( isset($value_def['default']) ) {
					$value_default = $value_def['default'];
				}

				$value_list = $value_def['values'];

				$select_single = $value_def['select_single'];
				$select_list_size = 1;

				if( isset($value_def['select_list_size'] )) {
					$select_list_size = $value_def['select_list_size'];
					if( $select_list_size === 'full' ) {
						$select_list_size = count($value_list);
					}
					$select_list_size = $select_list_size * 1;
					if( $select_list_size == 0 ) {
						$select_list_size = 1;
					}
				}

				$select_separator = NULL;
				if( isset($value_def['select_separator']) ) {
					$select_separator = $value_def['select_separator'];
				}

				if( is_array($current_value) ) {
					if( $select_single === TRUE ) {
						$value_default = $current_value[0]; // single selects only at the moment
					} else {
						$value_default = $current_value;
					}
				}

				$x_val_a = array();

				foreach($value_list as $v_val => $v_display) {
					$sel = '';

					if( $select_single === TRUE ) {
						if( $value_default AND $v_val == $value_default ) {
							$sel = 'selected="selected"';
						}
					} else {
						if( count($value_default) AND is_array($value_default) AND in_array($v_val,$value_default) ) {
							$sel = 'selected="selected"';
						}
					}

					$x_val_a[] = <<<EOS
						<option $sel value="$v_val">$v_display</option>
EOS;
				}

				$x_val_s = implode("\n", $x_val_a);
				$x_multiple = '';
				if( $select_single === FALSE ) {
					$x_multiple = 'multiple="multiple"';
				}

				$x_size = 'size="' . $select_list_size . '"';

				$x_a[] = <<<EOS
					<div class="form_entry">
						$x_msg
						<label class="label" for="$id">$label</label>
						<span class="pre_feedback">&nbsp</span>
						<select id="$id" class="$class" name="{$z_name}[]" $x_size title="$title" $x_multiple >
							$x_val_s
						</select>
					</div>
EOS;
				break;

			case 'checkbox':
				$x_check = '';
				if( is_array($value) ) {
					$x_check = 'checked="checked"';
				}

				$x_a[] = <<<EOS
					<div class="form_entry">
						$x_msg
						<label class="label" for="$id">$label</label>
						<span class="pre_feedback">&nbsp</span>
						<input type="checkbox" id="$id" class="$class" name="{$z_name}[]" title="$title" value="$value" $x_check />
					</div>
EOS;
				break;

			default:
				break;

			}
		}

		// http://home.jongsma.org/software/js/datepicker
		$where = base_url();

		$x_extra_buttons_s = '';
		if( count($this -> _extraButtonsValidate) > 0 ) {
			$x_extra_buttons_a = array();
			foreach($this -> _extraButtonsValidate as $b_name) {
				$x_extra_buttons_a[] = <<<EOS
				<input type="submit" value="$b_name" name="submit" />
EOS;

			}
			$x_extra_buttons_s = implode("\n",$x_extra_buttons_a);
		}

		$x_a[] = <<<EOS
			</fieldset>
			<fieldset>
				<legend> Actions </legend>
				<input type="submit" value="Validate Only" name="submit" />
				$x_extra_buttons_s
				<input type="reset" value="Reset" name="reset" />
			</fieldset>
		</form>
	</div>
EOS;

		$x_a[] = <<<EOS
	<script language="javascript">
		function createPickers()
		{
			$(document.body).select(
				'input.datepicker'
			).each(
				function(e) {
					new Control.DatePicker(
						e,
						{
							'icon': '$where/images/calendar.png',
							timePicker: true,
							timePickerAdjacent: true,
							use24hrs: true,
							locale: 'en_iso8601'
						}
					);
				}
			);
		}

		Event.observe(window, 'load', createPickers);
	</script>

EOS;

		$x_s = implode("\n", $x_a);
		return $x_s;
	}

	public function validate_one_field($field_name,&$taint_value)
	{
		// Value transforms may change the input data
		// so we communicate the changed data back to the caller
		// tests run on the transformed data

		if( ! isset($this->patternList['FieldList']['fields'][$field_name]) ) {
			return "I have no knowledge of the field: $field_name";
		}

		$x_defs = $this->patternList['FieldList']['fields'][$field_name];

		if( ! isset($x_defs['value_constraints'] ) ) {
			return 'OK';
		}

		$x_type = $x_defs['html_type'];

		$x_constraints = $x_defs['value_constraints'];

		// CHECK: Mandatory

		if( isset($x_constraints['mandatory']) ) {
			if( $x_constraints['mandatory'] === TRUE ) {
				if( $x_type === 'checkbox' ) {
					return 'OK';
				}

				if( ! $taint_value ) {
					return "Err: this field is mandatory";
				}
			} else {
				// for non mandatory fiels that have no value, stop here
	
				// mboot; 8-sept-2010
				if( strlen(trim($taint_value)) == 0 ) {
					return 'OK';
				}
			}
		} else {
			// for non mandatory fiels that have no value, stop here

			// mboot; 8-sept-2010
			if( strlen(trim($taint_value)) == 0 ) {
				return 'OK';
			}
		}

		// Value_type
		if( ! isset($x_constraints['value_type'] ) ) {
			return 'OK';
		}

		$x_v_type = $x_constraints['value_type'];

		switch($x_v_type) {

		case 'bool':
			$taint_value = trim($taint_value);
			break;

		case 'string':
			if( ! is_array($taint_value) ) {
				$taint_value = trim($taint_value);
			} else {
				// TODO
				// check if the value is in the select list you prepared and not some bogus data
				// if not in array(......) msg: that value is not in the select list
			}
			///////////////////////////////////////////////////
			// VALUE TRANSFORMS

			$known_transforms = array(
				'uppercase',
				'lowercase',
			);

			$v_trans = NULL;
			if( isset($x_constraints['value_transform']) ) {
				$v_trans = $x_constraints['value_transform'];
				if( ! is_array($v_trans) ) {
					$v_trans = NULL;
				}
			}

			if( $v_trans !== NULL ) {
				foreach($v_trans as $this_trans) {
					if( ! in_array($this_trans,$known_transforms)) {
						continue;
					}
					switch($this_trans) {
					case 'lowercase':
						$taint_value = strtolower($taint_value);
						break;
					case 'uppercase':
						$taint_value = strtoupper($taint_value);
						break;
					default:
						break;
					}
				}
			}

			///////////////////////////////////////////////////
			// LENGTH CHECKS

			$v_len = NULL;
			if( isset($x_constraints['value_length']) ) {
				$v_len = $x_constraints['value_length'];
				if( ! is_array($v_len) ) {
					$v_len = NULL;
				}
				if( count($v_len) !== 2) {
					$v_len = NULL;
				}
			}

			if( $v_len !== NULL ) {
				$v_len_min = $v_len[0];
				$v_len_max = $v_len[1];

				if( strlen($taint_value) < $v_len_min OR strlen($taint_value) > $v_len_max ) {
					return "Err: the current value is not within the length boundaries: [ $v_len_min, $v_len_max ]";
				}
			}

			///////////////////////////////////////////////////
			// PERL REGEX TESTS

			if( isset($x_constraints['value_match_preg']) ) {
				foreach($x_constraints['value_match_preg'] as $x_preg => $x_msg) {
					// a regexp must have at least 2 times / and something in between (hence minimal 3 chars)
					if( strlen($x_preg) < 3 OR ! preg_match('/^\/.*\/i?$/', $x_preg) ) {
						continue;
					}
					if( ! preg_match($x_preg,$taint_value) ) {
						if( $x_msg ) {
							return "$x_msg";
						} else {
							return "Err: the value ($taint_value) does not match the perl_regex $x_preg";
						}
					}
				}
			}

			/////////////////////////////////////////////////
			// All seems OK so FAR
			break;

		case 'numeric':
			$taint_value = trim($taint_value);

			// Force numeric value
			if( ! preg_match('/^\s*\d+\s*$/', $taint_value ) ) {
				return "Err: the value of this field must be numerical, that means that only numbers are allowed";
			}

			// Value Range
			$v_range = NULL;
			if( isset($x_constraints['value_range']) ) {
				$v_range = $x_constraints['value_range'];
				if( ! is_array($v_range) ) {
					$v_range = NULL;
				}
				if( count($v_range) !== 2) {
					$v_range = NULL;
				}
			}

			if( $v_range !== NULL ) {
				$v_range_min = $v_range[0];
				$v_range_max = $v_range[1];

				if( $taint_value < $v_range_min OR $taint_value > $v_range_max ) {
					return "Err: the current value is not within the range: [ $v_range_min, $v_range_max ]";
				}
			}

			// PERL REGEX TESTS
			if( isset($x_constraints['value_match_preg']) ) {
				foreach($x_constraints['value_match_preg'] as $x_preg => $x_msg) {
					// a regexp must have at least 2 times / and something in between (hence minimal 3 chars)
					if( strlen($x_preg) < 3 OR ! preg_match('/^\/.*\/i?$/', $x_preg) ) {
						continue;
					}
					if( ! preg_match($x_preg,$taint_value) ) {
						if( $x_msg ) {
							return "$x_msg";
						} else {
							return "Err: the value ($taint_value) does not match the perl_regex $x_preg";
						}
					}
				}
			}

			break;

		case 'monetary':
			$taint_value = trim($taint_value);

			///////////////////////////////////////////////////
			if( ! preg_match('/^\s*\d+\.\d{2}\s*$/', $taint_value ) ) {
				return "Err: the value of this field must be monetary, that means that it must look like xxx.yyy ";
			}

			// PERL REGEX TESTS
			if( isset($x_constraints['value_match_preg']) ) {
				foreach($x_constraints['value_match_preg'] as $x_preg => $x_msg) {
					// a regexp must have at least 2 times / and something in between (hence minimal 3 chars)
					if( strlen($x_preg) < 3 OR ! preg_match('/^\/.*\/i?$/', $x_preg) ) {
						continue;
					}
					if( ! preg_match($x_preg,$taint_value) ) {
						if( $x_msg ) {
							return "$x_msg";
						} else {
							return "Err: the value ($taint_value) does not match the perl_regex $x_preg";
						}
					}
				}
			}

			// Value Range
			$v_range = NULL;
			if( isset($x_constraints['value_range']) ) {
				$v_range = $x_constraints['value_range'];
				if( ! is_array($v_range) ) {
					$v_range = NULL;
				}
				if( count($v_range) !== 2) {
					$v_range = NULL;
				}
			}

			if( $v_range !== NULL ) {
				$v_range_min = $v_range[0];
				$v_range_max = $v_range[1];

				if( $taint_value < $v_range_min OR $taint_value > $v_range_max ) {
					return "Err: the current value is not within the range: [ $v_range_min, $v_range_max ]";
				}
			}

			break;

		case 'iso_date_time':
		case 'date_time':
			$taint_value = trim($taint_value);

			///////////////////////////////////////////////////
			// PERL REGEX TESTS

			// PERL REGEX TESTS
			if( isset($x_constraints['value_match_preg']) ) {
				foreach($x_constraints['value_match_preg'] as $x_preg => $x_msg) {
					// a regexp must have at least 2 times / and something in between (hence minimal 3 chars)
					if( strlen($x_preg) < 3 OR ! preg_match('/^\/.*\/i?$/', $x_preg) ) {
						continue;
					}
					if( ! preg_match($x_preg,$taint_value) ) {
						if( $x_msg ) {
							return "$x_msg";
						} else {
							return "Err: the value ($taint_value) does not match the perl_regex $x_preg";
						}
					}
				}
			}

			// TODO: test valid date and time
			break;

		case 'file_upload':
			break;

		default:
			return 'Err: I do not know that value_type: ' . $x_v_type;
			break;
		}

		return 'OK';
	}

	public function validate_all($zz = array())
	{
		$x_path = dirname(__FILE__) . '/../../../';

		$upload_config = array(
			'upload_path'	=> $x_path . '/uploads/',
			'allowed_types'	=> 'jpg',
			'max_size'		=> '500',	// in kbytes
			'max_width'		=> '1024',
			'max_height'	=> '768',
			'overwrite'		=> TRUE,
			'remove_spaces'	=> TRUE,
		);

		$this->CI->load->library('upload', $upload_config);

		$x = $this->CI->input->post('submit', TRUE);

		$submitValue = $x;

		// This can now be several values
		// Validate Only
		// Save Changes
		// Delete

		$this->allOK = FALSE;

		// TODO: Note: if the user did not press validate and there is no data , this results in TRUE, to be corrected
		$allOK = TRUE;

		if( 
			$x !== FALSE 
		AND (
				$x === 'Validate Only' 
			OR 	
				$x === 'Save Changes' 
			OR 
				$x === 'Delete' 
			)
		) {

			// process results from post
			foreach($this->patternList['FieldList']['sequence'] as $field_name) {

				// upload file has to be treated special 
				$x_defs = $this->patternList['FieldList']['fields'][$field_name];

				$x_type = NULL;
				if( isset($x_defs['value_constraints']) AND isset($x_defs['value_constraints']['value_type']) ) {
					$x_type = $x_defs['value_constraints']['value_type'];
				}

				if( $x_type === 'file_upload' ) {
					// NOTE: there are several problems with upload
					// As it does not behave as a normal field; in particular
					// we will never know the client side filename fully
					// It is advised to have separate upload forms and not to mix them with other validateion inputs

					$zz = $this->CI->upload->do_upload($field_name);
					if( !$zz ) {
						$this->messages[$field_name] = $this->CI->upload->display_errors('','');
						$allOK = FALSE;
					} else {
						$this_upload = $this->CI->upload->data();

						$msg = $this->validate_one_field($field_name,$this_upload['file_name']);
						if( $msg !== 'OK' ) {
							$this->messages[$field_name] = $msg;
							$allOK = FALSE;
						}

						$this->values[$field_name] = $this_upload['file_name'];
					}

					continue;
				} 

				$taint_value = NULL;
				if( isset( $_POST['form_data'][$field_name]) ) {
					$taint_value = $this->CI->input->xss_clean($_POST['form_data'][$field_name]);
				}


				$msg = $this->validate_one_field($field_name,$taint_value);
				// print "validate_one_field($field_name,$taint_value); $msg<br />";
				if( $msg !== 'OK' ) {
					$this->messages[$field_name] = $msg;
					$allOK = FALSE;
				}

				$this->values[$field_name] = $taint_value;
			} // end foreach individusl fiels


			// if we have inter related tests run them after all individual ones but only if there are no errors detected
			if( $allOK === TRUE AND isset( $this->patternList['FieldList']['PostChecks']) ) {
		

			}
		}
	
		if( count( $this->values ) === 0 AND count($zz) !== 0 ) {
			foreach($zz as $zz_name => $zz_val) {
				$this->values[$zz_name] = $zz_val;
			}	
		}

		$this->allOk = $allOK;

		return array($allOK,$submitValue);
	}

	public function get_values()
	{
		if( ! $this->allOk === TRUE ) {
			return NULL;
		}

		$values = array();
		foreach( $this->values as $name => $val ) {
			$values[$name] = $val;
		}

		return $values;
	}

	public function get_human_values()
	{
		$v = $this -> get_values();

		if ($v === NULL ) {
			return NULL;
		}

		$dictionary = $this->patternList['FieldList'];

		$values = array();
		foreach( $v as $v_name => $v_val ) {

			if( ! isset($dictionary['fields'][$v_name]) ) {
				continue;
			}

			$template_def = $dictionary['fields'][$v_name];
			$html_type = $template_def['html_type'];

			$value_type = 'string';
			if( isset($template_def['value_constraints']) AND isset($template_def['value_constraints']['value_type']) ) {
				$value_type = $template_def['value_constraints']['value_type'];
			}

			// html_types select and checkbox are special in the sense that they return arrays
			// if the select is single: we need a scalar value
			// if the value_type for the checkbox is a boolean: we need a scalar
			// In particular boolean checkboxes are not present in the resulting data if they are not selecte
			// that means the default value is FALSE and if a value is present it becomse TRUE

			switch($html_type) {

			case 'select':
				// assume select single for now
				// mboot; 13-09-2010; create code for multi select and use select_separator to process results
				$list_types = array('inline', 'function');

				// list type 
				$list_type = 'inline';
				if( isset( $template_def['value_constraints']['value_select_list_type']) ) {
					$list_type = $template_def['value_constraints']['value_select_list_type'];
					if( ! in_array($list_type,$list_types) ) {
						continue;
					}
				}

				// list name
				$list_name = '';
				if( isset( $template_def['value_constraints']['value_select_list_name'] ) ) {
					$list_name = $template_def['value_constraints']['value_select_list_name'];
				}
				if( $list_name === '' ) {
					continue;
				}

				// get values 
				switch($list_type) {
				case 'inline':
					if( ! isset( $this->patternList['InlineLists'][$list_name] ) ) {
						continue;
					}
					$value_def = $this->patternList['InlineLists'][$list_name];
					break;

				case 'function':
					// not implemented yet
					$value_def = $list_name();
					// must return both value list and default
					// array( 'values' => array(), 'default => value)
					continue;
					break;

				default:
					continue;
					break;
				}

				$select_single = $value_def['select_single'];
				if( $select_single === TRUE ) {
					$values[$v_name] = $v_val[0];
				} else {
					$select_separator = NULL;
					if( isset($value_def['select_separator']) ) {
						$select_separator = $value_def['select_separator'];
					}
					if( $select_separator === NULL ) {
						continue;
					}
					$values[$v_name] = implode($select_separator, $v_val);
				}
				break;

			case 'checkbox':
				// $values[$v_name] = FALSE;
				// NOTE: this is not even present in the $values if FALSE
				// value_type: bool value_bool_true: and value_bool_false 
				// default user string 0 and string 1
				
				$values[$v_name] = "0";
				if( $v_val ) {
					$values[$v_name] = "1";
				}

				if( $value_type === "bool" ) {
					if( isset($template_def['value_constraints']['value_bool_true']) and isset($template_def['value_constraints']['value_bool_false']) ) {
						$t = $template_def['value_constraints']['value_bool_true'];
						$f = $template_def['value_constraints']['value_bool_false'];
						$values[$v_name] = $f;
						if( $v_val ) {
							$values[$v_name] = $t;
						}
					}
				}
				break;

			default:
				$values[$v_name] = $v_val;
				break;
			}
		}

		return $values;
	}

	public function get_select_names()
	{
		$dictionary = $this->patternList['FieldList'];

		$select_names = array();

		// during import the dropdosns have to be treated as arrays
		foreach($dictionary['sequence'] as $field_name) {
			$template_def = $dictionary['fields'][$field_name];
			$html_type = $template_def['html_type'];
			if( ! $dictionary['fields'][$field_name] ) {
				continue;
			}

			switch($html_type) {

			case 'select':
				// mboot; 13-09-2010; create code for multi select and use select_separator to process results
				$list_types = array('inline', 'function');

				// list type 
				$list_type = 'inline';
				if( isset( $template_def['value_constraints']['value_select_list_type']) ) {
					$list_type = $template_def['value_constraints']['value_select_list_type'];
					if( ! in_array($list_type,$list_types) ) {
						continue;
					}
				}

				// list name
				$list_name = '';
				if( isset( $template_def['value_constraints']['value_select_list_name'] ) ) {
					$list_name = $template_def['value_constraints']['value_select_list_name'];
				}
				if( $list_name === '' ) {
					continue;
				}

				// get values 
				switch($list_type) {

				case 'inline':
					if( ! isset( $this->patternList['InlineLists'][$list_name] ) ) {
						continue;
					}

					$value_def = $this->patternList['InlineLists'][$list_name];
					break;

				case 'function':
					// not implemented yet
					$value_def = $list_name();
					// must return both value list and default
					// array( 'values' => array(), 'default => value)
					continue;
					break;

				default:
					continue;
					break;
				}

				$select_single = $value_def['select_single'];
				$select_separator = NULL;
				if( isset($value_def['select_separator']) ) {
					$select_separator = $value_def['select_separator'];
				}

				$select_names[$field_name] = array(
					'select_single' => $select_single,
					'select_separator' => $select_separator,
				);
				break;

			default:
				break;
			}
		}

		return $select_names;
	}
}

