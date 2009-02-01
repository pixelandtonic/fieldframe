<?php

class Checkbox_group
{
	var $info = array(
		'name'                    => 'Checkbox Group',
		'version'                 => '1.0.0',
		'desc'                    => 'Provides a group of checkboxes',
		'docs_url'                => 'http://brandon-kelly.com/',
		'author'                  => 'Brandon Kelly',
		'author_url'              => 'http://brandon-kelly.com/',
		'versions_xml_url'        => 'http://brandon-kelly.com/downloads/versions.xml'
	);
//
//	/**
//	 * Field Constructor
//	 */
//	function Checkbox_group($sitewide_settings=array())
//	{
//		$this->sitewide_settings = $sitewide_settings;
//	}
//
//	/**
//	 * Display Sitewide Settings
//	 * 
//	 * Called if $field_info['sitewide_settings_exist'] == 'y'
//	 * 
//	 * Defines the field’s sitewide settings block in Field’s
//	 * settings page within the Extensions Manager
//	 * 
//	 * @return array
//	 */
//	function display_sitewide_settings()
//	{
//		return array();
//	}
//
//	/**
//	 * Display Field Settings
//	 * 
//	 * Called form the Edit Custom Field form
//	 * 
//	 * @param  array  $field_settings  The currently saved field settings
//	 * @return array  Blocks of HTML
//	 */
//	function display_field_settings($field_settings)
//	{
//		global $DSP;
//		
//		$v = '';
//		foreach($field_settings['options'] as $option_value => $option_label) {
//			if ($v != '') $v .= NL;
//			$v .= "{$option_value} = {$option_label}";
//		}
//		$r = array(
//			'block2' => $DSP->input_textarea('checkbox_options', $v)
//		);
//		return $r;
//	}
//
//	/**
//	 * Save Field Settings
//	 * 
//	 * @return array  The field settings (not serialized)
//	 */
//	function save_field_settings()
//	{
//		$r = array(
//			'options' => array()
//		);
//		foreach(explode(NL, $_POST['checkbox_options']) as $option) {
//			$option = explode('=', $option);
//			$r['options'][trim($option[0])] = trim($option[1])
//		}
//		
//		$r = array(
//			'options' => )
//		);
//		return $r;
//	}
//
//	/**
//	 * Display Custom Field
//	 * 
//	 * @param string   $field_id        The field’s name
//	 * @param mixed    $field_data      The field’s current value
//	 * @param array    $field_settings  The field’s settings
//	 * 
//	 * @return string  The field’s HTML
//	 */
//	function display_custom_field($field_id, $field_data, $field_settings)
//	{
//		global $DSP;
//		
//		$r = '';
//		foreach($field_settings['options'] as $option_label => $option_value) {
//			$checked = in_array($option_value, $field_data) ? 1 : 0;
//			if ($r != '') $r .= BR;
//			$r .= '<label>'
//			    . $DSP->input_checkbox("{$field_id}[]", $option_value, $checked)
//			    . NBSP.$option_label
//			    . '</label>';
//		}
//	 	return $r;
//	}
//
//	/**
//	 * Save Field
//	 * 
//	 * @param string  $field_id  The field’s name
//	 * @return mixed  The field’s data
//	 */
//	function save_field_data($field_id)
//	{
//		$r = array();
//		foreach($_POST[$field_id] as $option_value) {
//			$r[] = $option_value;
//		}
//		return $r;
//	}
}

?>