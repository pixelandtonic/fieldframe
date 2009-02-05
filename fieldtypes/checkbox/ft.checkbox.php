<?php

class Checkbox
{
	var $info = array(
		'name'                    => 'Checkbox',
		'version'                 => '1.0.0',
		'desc'                    => 'Provides as single checkbox',
		'docs_url'                => 'http://brandon-kelly.com/',
		'versions_xml_url'        => 'http://brandon-kelly.com/downloads/versions.xml'
	);

	/**
	 * Display Field
	 * 
	 * @param  string  $field_name      The field's name
	 * @param  mixed   $field_data      The field's current value
	 * @param  array   $field_settings  The field's settings
	 * @return string  The field's HTML
	 */
	function display_field($field_name, $field_data)
	{
		global $DSP;
		return '<label style="display:inline-block; padding:1px; background:#768E9D;">'
		         . $DSP->input_checkbox($field_name, 'y', $field_data == 'y' ? 1 : 0)
		         . '</label>';
	}

	/**
	 * Display Cell
	 * 
	 * @param  string  $cell_name      The cell's name
	 * @param  mixed   $cell_data      The cell's current value
	 * @param  array   $cell_settings  The cell's settings
	 * @return string  The cell's HTML
	 */
	function display_matrix_cell($cell_name, $cell_data)
	{
		// We're not doing anything special for matrix cells,
		// so we just route this call to display_custom_field()
		return $this->display_field($cell_name, $cell_data);
	}
}

/* End of file ft.checkbox.php */
/* Location: ./system/fieldtypes/ft.checkbox.php */