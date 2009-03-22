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
class Ff_checkbox_group extends Fieldframe_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'        => 'FF Checkbox Group',
		'version'     => FF_VERSION,
		'desc'        => 'Provides a checkbox group fieldtype',
		'docs_url'    => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-checkbox-group'
	);

	/**
	 * Default Site Settings
	 * @var array
	 */
	var $default_site_settings = array(
		'option_tmpl' => '<li>{option}</li>'
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

	/**
	 * Default Tag Params
	 * @var array
	 */
	var $default_tag_params = array(
		'sort'      => '',
		'backspace' => '0'
	);

	/**
	 * Display Site Settings
	 */
	function display_site_settings()
	{
		global $LANG;

		// Initialize a new instance of SettingsDisplay
		$SD = new Fieldframe_SettingsDisplay();

		// Open the settings block
		$r = $SD->block('FF Checkbox Group');

		// Add the Default Option Template setting
		$r .= $SD->row(array(
		                 $SD->label('checkbox_option_tmpl_label', 'checkbox_option_tmpl_subtext'),
		                 $SD->textarea('option_tmpl', $this->site_settings['option_tmpl'], array('rows' => '2'))
		                   . '<p>'.$LANG->line('checkbox_option_tmpl_tags').'</p>'
		               ));

		// Close the settings block
		$r .= $SD->block_c();

		// Return the settings block
		return $r;
	}

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
	 * @return array  Settings HTML (cell1, cell2, rows)
	 */
	function display_field_settings($field_settings)
	{
		global $DSP, $LANG;

		$options = $this->_options_setting($field_settings['options']);

		$cell2 = $DSP->qdiv('defaultBold', $LANG->line('checkbox_options_label'))
		       . $DSP->qdiv('default', $LANG->line('checkbox_options_subtext'))
		       . $DSP->input_textarea('options', $options, '6', 'textarea', '99%')
		       . $DSP->qdiv('default', $LANG->line('checkbox_option_examples'));

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

		$options = $this->_options_setting($cell_settings['options']);

		$r = '<label class="itemWrapper">'
		   . $DSP->qdiv('defaultBold', $LANG->line('checkbox_options_label'))
		   . $DSP->input_textarea('options', $options, '3', 'textarea', '140px')
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
		$r = array('options' => array());
		$options = preg_split('/[\r\n]+/', $field_settings['options']);
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
		global $DSP;
		if ( ! $field_data) $field_data = array();
		$r = '';
		foreach($field_settings['options'] as $option_name => $option_label)
		{
			$checked = in_array($option_name, $field_data) ? 1 : 0;
			$r .= '<label style="margin-right:15px; white-space:nowrap;">'
			    . $DSP->input_checkbox("{$field_name}[]", $option_name, $checked)
			    . $option_label
			    . '</label>';
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
		return $this->display_field($cell_name, $cell_data, $cell_settings);
	}

	/**
	 * Display Tag
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  relationship references
	 */
	function display_tag($params, $tagdata, $field_data, $field_settings)
	{
		global $TMPL;

		$r = '';

		if ($field_settings['options'])
		{
			// option template
			if ( ! $tagdata) $tagdata = $this->site_settings['option_tmpl'];

			if ( ! $field_data) $field_data = array();

			// optional sorting
			if ($sort = strtolower($params['sort']))
			{
				if ($sort == 'asc')
				{
					sort($field_data);
				}
				else if ($sort == 'desc')
				{
					rsort($field_data);
				}
			}

			// replace switch tags with {SWITCH[abcdefgh]SWITCH} markers
			$this->switches = array();
			$tagdata = preg_replace_callback('/'.LD.'switch\s*=\s*[\'\"]([^\'\"]+)[\'\"]'.RD.'/sU', array(&$this, '_get_switch_options'), $tagdata);

			$count = 0;
			foreach($field_data as $option_name)
			{
				if (isset($field_settings['options'][$option_name]))
				{
					// copy $tagdata
					$option_tagdata = $tagdata;

					// simple var swaps
					$option_tagdata = $TMPL->swap_var_single('option', $field_settings['options'][$option_name], $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('option_name', $option_name, $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('count', $count+1, $option_tagdata);

					// switch tags
					foreach($this->switches as $i => $switch)
					{
						$option = $count % count($switch['options']);
						$option_tagdata = str_replace($switch['marker'], $switch['options'][$option], $option_tagdata);
					}

					$r .= $option_tagdata;

					$count++;
				}
			}
		}

		if ($params['backspace'])
		{
			$r = substr($r, 0, -$params['backspace']);
		}

		return $r;
	}

	/**
	 * Get Switch Options
	 *
	 * @param  array   $matches  array of match chunks
	 * @return string  marker to be inserted back into tagdata
	 * @access private
	 */
	function _get_switch_options($match)
	{
		global $FNS;
		$marker = LD.'SWITCH['.$FNS->random('alpha', 8).']SWITCH'.RD;
		$this->switches[] = array('marker' => $marker, 'options' => explode('|', $match[1]));
		return $marker;
	}

}


/* End of file ft.ff_checkbox_group.php */
/* Location: ./system/fieldtypes/ff_checkbox_group/ft.ff_checkbox_group.php */