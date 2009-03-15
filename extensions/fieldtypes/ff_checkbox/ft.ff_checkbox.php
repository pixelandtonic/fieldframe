<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * Checkbox Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_checkbox extends Fieldframe_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Checkbox',
		'version'  => FF_VERSION,
		'desc'     => 'Provides a single checkbox fieldtype',
		'docs_url' => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-checkbox'
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
		global $DSP;
		return $DSP->input_checkbox($field_name, 'y', $field_data == 'y' ? 1 : 0);
	}

	function display_cell($cell_name, $cell_data, $cell_settings)
	{
		return $this->display_field($cell_name, $cell_data, $cell_settings);
	}

}


/* End of file ft.ff_checkbox.php */
/* Location: ./system/fieldtypes/ff_checkbox/ft.ff_checkbox.php */