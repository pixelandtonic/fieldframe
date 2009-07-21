<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * FF Select Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_select extends Fieldframe_Multi_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Select',
		'version'  => '1.0',
		'desc'     => 'A better drop-down list',
		'docs_url' => 'http://brandon-kelly.com/fieldframe/docs/ff-select',
		'no_lang'  => TRUE
	);

	var $settings_label = 'select_options_label';

	/**
	 * Display Site Settings
	 */
	function display_site_settings()
	{
		global $DB;

		$fields_q = $DB->query('SELECT COUNT(*) FROM exp_extensions WHERE class = "Sarge"');
		if ($fields_q->row)
		{
			$SD = new Fieldframe_SettingsDisplay();
			return $SD->block()
			     . $SD->row(array(
			                  $SD->label('convert_sarge_label'),
			                  $SD->select('convert_sarge', 'n', array('n' => 'no', 'y' => 'yes'))
			                ))
			     . $SD->block_c();
		}

		return FALSE;
	}

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

		$SD = new Fieldframe_SettingsDisplay();
		return $SD->select($field_name, $field_data, $field_settings['options']);
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

}


/* End of file ft.ff_select.php */
/* Location: ./system/fieldtypes/ff_select/ft.ff_select.php */