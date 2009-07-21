<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * FF Multi-select Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_multiselect extends Fieldframe_Multi_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Multi-select',
		'version'  => '1.2',
		'docs_url' => 'http://brandon-kelly.com/fieldframe/docs/ff-multi-select',
		'no_lang'  => TRUE
	);

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
		$SD = new Fieldframe_SettingsDisplay();
		return $SD->multiselect($field_name.'[]', $field_data, $field_settings['options']);
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
		$SD = new Fieldframe_SettingsDisplay();
		return $SD->multiselect($cell_name.'[]', $cell_data, $cell_settings['options'], array('width' => '145px'));
	}

}


/* End of file ft.ff_multiselect.php */
/* Location: ./system/fieldtypes/ff_multiselect/ft.ff_multiselect.php */