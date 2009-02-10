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
class Checkbox_group {

	var $info = array(
		'name'             => 'Checkbox Group',
		'version'          => '1.0.0',
		'desc'             => 'Provides as checkbox group field type',
		'docs_url'         => 'http://brandon-kelly.com/',
		'versions_xml_url' => 'http://brandon-kelly.com/downloads/versions.xml'
	);

	/**
	 * Display Field Settings
	 * 
	 * @param  array  $settings  The field's settings
	 * @return array  Settings HTML (col1, col2, rows)
	 */
	function display_field_settings($settings)
	{
		global $DSP;
		$options = '';
		if (isset($settings['options']))
		{
			foreach($settings['options'] as $name => $label)
			{
				if ($options) $options .= "\n";
				$options .= $name.' : '.$label;
			}
		}
		return array(
		              'cell1' => 'Test Cell One',
		              'cell2' => $DSP->qdiv('defaultBold', 'Checkbox Options')
		                       . $DSP->qdiv('default', 'Put each item on a single line')
		                       . $DSP->input_textarea('options', $options, '6', 'textarea', '99%'),
		              'rows'  => array(
		                                array($DSP->qdiv('defaultBold', 'Test Row 1'), '<label>Yes'.NBS.$DSP->input_radio('row1', 'y', 1).'</label>'.NBS.NBS.NBS.'<label>No'.NBS.$DSP->input_radio('row1', 'n').'</label>'),
		                                array($DSP->qdiv('defaultBold', 'Test Row 2'), '<label>Yes'.NBS.$DSP->input_radio('row2', 'y', 1).'</label>'.NBS.NBS.NBS.'<label>No'.NBS.$DSP->input_radio('row2', 'n').'</label>')
		                               )
		             );
	}

	/**
	 * Save Field Settings
	 *
	 * Turn the options textarea value into an array of option names and labels
	 * 
	 * @param  array  $settings  The user-submitted settings, pulled from $_POST
	 * @return array  Modified $settings
	 */
	function save_field_settings($settings)
	{
		$r = array('options' => array());
		$options = preg_split('/[\r\n]/', $settings['options']);
		foreach($options as $option)
		{
			$option = explode(':', $option);
			$option_name = trim($option[0]);
			$option_value = isset($option[1]) ? trim($option[1]) : $option_name;
			$r['options'][$option_name] = $option_value;
		}
		return $r;
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
		$field_data = $field_data ? unserialize($field_data) : array();
		$r = '';
		if (isset($field_settings['options']))
		{
			foreach($field_settings['options'] as $option_name => $option_label)
			{
				$checked = in_array($option_name, $field_data) ? 1 : 0;
				$r .= '<label style="margin-right:15px; white-space:nowrap;">'
				    . $DSP->input_checkbox("{$field_name}[{$option_name}]", 'y', $checked)
				    . $option_label
				    . '</label>';
			}
		}
		return $r;
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
		// We're not doing anything special for matrix cells,
		// so we just route this call to display_field()
		return $this->display_field($cell_name, $cell_data, $cell_settings);
	}

}


/* End of file ft.checkbox_group.php */
/* Location: ./system/fieldtypes/checkbox_group/ft.checkbox_group.php */