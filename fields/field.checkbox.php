<?php

class Checkbox
{
	var $info = array(
		'name'                    => 'Checkbox',
		'version'                 => '1.0.0',
		'desc'                    => 'Provides as single checkbox',
		'docs_url'                => 'http://brandon-kelly.com/',
		'author'                  => 'Brandon Kelly',
		'author_url'              => 'http://brandon-kelly.com/',
		'versions_xml_url'        => 'http://brandon-kelly.com/downloads/versions.xml'
	);

//	/**
//	 * Field Constructor
//	 */
//	function Checkbox($sitewide_settings=array())
//	{
//		$this->sitewide_settings = $sitewide_settings;
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
//		$checked = $field_data == 'y' ? 1 : 0;
//	 	return $DSP->input_checkbox($field_id, 'y', $checked);
//	}
//
//	/**
//	 * Display Matrix Cell
//	 * 
//	 * @param string   $cell_name      The cell’s name
//	 * @param mixed    $cell_data      The cell’s current value
//	 * @param array    $cell_settings  The cell’s settings
//	 * 
//	 * @return string  The cell’s HTML
//	 */
//	function display_matrix_cell($cell_name, $cell_data, $cell_settings)
//	{
//		// We’re not doing anything special for Matrix Cells,
//		// so we just route this call to display_custom_field()
//		return $this->display_custom_field($cell_name, $cell_data, $cell_settings);
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
//		// Do a quick sanitization, and then return
//		return $_POST[$field_id] == 'y' ? 'y' : '';
//	}
}

?>