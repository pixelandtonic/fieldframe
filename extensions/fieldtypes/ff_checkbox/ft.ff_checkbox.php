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
		'name'             => 'FF Checkbox',
		'version'          => '1.0.0',
		'desc'             => 'Provides as single checkbox field type',
		'docs_url'         => 'https://github.com/brandonkelly/bk.fieldframe.ee_addon/wikis',
		'versions_xml_url' => 'http://brandon-kelly.com/downloads/versions.xml'
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
		return '<label style="display:inline-block; padding:1px; background:#768E9D;">'
		         . $DSP->input_checkbox($field_name, 'y', $field_data == 'y' ? 1 : 0)
		         . '</label>';
	}

}


/* End of file ft.ff_checkbox.php */
/* Location: ./system/fieldtypes/ff_checkbox/ft.ff_checkbox.php */