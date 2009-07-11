<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * Checkbox Group Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_checkbox_group extends Fieldframe_Multi_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Checkbox Group',
		'version'  => FF_VERSION,
		'desc'     => 'Provides a checkbox group fieldtype',
		'docs_url' => 'http://brandon-kelly.com/fieldframe/docs/ff-checkbox-group',
		'no_lang'  => TRUE
	);

	var $settings_label = 'checkbox_options_label';

	/**
	 * Display Field
	 * 
	 * @param  string  $field_name      The field's name
	 * @param  mixed   $field_data      The field's current value
	 * @param  array   $field_settings  The field's settings
	 * @return string  The field's HTML
	 */
	function display_field($field_name, $field_data, $field_settings)
	{
		global $DSP;

		if ( ! $field_data) $field_data = array();
		$r = '';

		foreach($field_settings['options'] as $option_name => $option_label)
		{
			$checked = in_array($option_name, $field_data) ? 1 : 0;
			$r .= '<label style="display:block; float:left; margin:3px 15px 7px 0; white-space:nowrap;">'
			    . $DSP->input_checkbox($field_name.'[]', $option_name, $checked)
			    . NBS.$option_label
			    . '</label> ';
		}
		$r .= $DSP->input_hidden($field_name.'[]', 'temp')
		    . '<div style="clear:left"></div>';

		return $r;
	}

	/**
	 * Save Field
	 *
	 * @param  mixed   $field_data      The field's data
	 * @param  array   $field_settings  The field's settings
	 * @return string  Modified $field_data
	 */
	function save_field($field_data, $field_settings)
	{
		// remove the temp element
		@array_pop($field_data);
		return $field_data;
	}

	/**
	 * Display Cell
	 * 
	 * @param  string  $cell_name      The cell's name
	 * @param  mixed   $cell_data      The cell's current value
	 * @param  array   $cell_settings  The cell's settings
	 * @return string  The cell's HTML
	 */
	function display_cell($cell_name, $cell_data, $cell_settings)
	{
		return $this->display_field($cell_name, $cell_data, $cell_settings);
	}

	/**
	 * Save Cell
	 *
	 * @param  mixed   $cell_data      The cell's data
	 * @param  array   $cell_settings  The cell's settings
	 * @return string  Modified $cell_data
	 */
	function save_cell($cell_data, $cell_settings)
	{
		return $this->save_field($cell_data, $cell_settings);
	}

}


/* End of file ft.ff_checkbox_group.php */
/* Location: ./system/fieldtypes/ff_checkbox_group/ft.ff_checkbox_group.php */