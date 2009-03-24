<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * Radio Group Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_radio_group extends Fieldframe_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'             => 'FF Radio Group',
		'version'          => FF_VERSION,
		'desc'             => 'Provides a radio group fieldtype',
		'docs_url'         => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-radio-group'
	);

	/**
	 * Default Field Settings
	 * @var array
	 */
	var $default_field_settings = array(
		'options' => array(
			'Option 1' => 'Option 1',
			'Option 2' => 'Option 2',
			'Option 3' => 'Option 3'
		)
	);

	/**
	 * Default Cell Settings
	 * @var array
	 */
	var $default_cell_settings = array(
		'options' => array(
			'Opt 1' => 'Opt 1',
			'Opt 2' => 'Opt 2'
		)
	);

	function _options_setting($options_setting=array())
	{
		$options = '';
		foreach($options_setting as $name => $label)
		{
			if ($options) $options .= "\n";
			$options .= $name . ($name != $label ? ' : '.$label : '');
		}
		return $options;
	}

	/**
	 * Display Field Settings
	 * 
	 * @param  array  $field_settings  The field's settings
	 * @return array  Settings HTML    (cell1, cell2, rows)
	 */
	function display_field_settings($field_settings)
	{
		global $DSP, $LANG;

		$cell2 = $DSP->qdiv('defaultBold', $LANG->line('radio_options_label'))
		       . $DSP->qdiv('default', $LANG->line('radio_options_subtext'))
		       . $DSP->input_textarea('options', $this->options_setting($field_settings['options']), '6', 'textarea', '99%')
		       . $DSP->qdiv('default', $LANG->line('radio_option_examples'));

		return array('cell2' => $cell2);
	}

	/**
	 * Display Field Settings
	 * 
	 * @param  array  $cell_settings  The cell's settings
	 * @return string  Settings HTML
	 */
	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->qdiv('defaultBold', $LANG->line('radio_options_label'))
		   . $DSP->input_textarea('options', $this->options_setting($cell_settings['options']), '3', 'textarea', '140px')
		   . '</label>';

		return $r;
	}

	/**
	 * Save Field Settings
	 *
	 * Turn the options textarea value into an array of option names and labels
	 * 
	 * @param  array  $field_settings  The user-submitted settings, pulled from $_POST
	 * @return array  Modified $field_settings
	 */
	function save_field_settings($field_settings)
	{
		$field_settings['options'] = $this->save_options_setting($field_settings['options']);
		return $field_settings;
	}

	/**
	 * Save Cell Settings
	 *
	 * Turn the options textarea value into an array of option names and labels
	 * 
	 * @param  array  $cell_settings  The user-submitted settings, pulled from $_POST
	 * @return array  Modified $cell_settings
	 */
	function save_cell_settings($cell_settings)
	{
		return $this->save_field_settings($cell_settings);
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
		$SD = new Fieldframe_SettingsDisplay();
		return $SD->radio_group($field_name, $field_data, $field_settings['options']);
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


/* End of file ft.ff_radio_group.php */
/* Location: ./system/fieldtypes/ff_radio_group/ft.ff_radio_group.php */