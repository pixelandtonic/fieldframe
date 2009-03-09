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
		'name'             => 'FF Checkbox Group',
		'version'          => '1.0.0',
		'desc'             => 'Provides as checkbox group field type',
		'docs_url'         => 'https://github.com/brandonkelly/bk.fieldframe.ee_addon/wikis',
		'versions_xml_url' => 'http://brandon-kelly.com/downloads/versions.xml'
	);

	/**
	 * Display Site Settings
	 */
	function display_site_settings()
	{
		$SD = new Fieldframe_SettingsDisplay();

		$r = $SD->block()
		   . $SD->row(array(
		                $SD->label('Default Option Template', 'Available tags: <code>{option}</code>, <code>{option_name}</code>, <code>{count}</code>, and <code>{switch="odd|even"}</code>'),
		                $SD->textarea('option_tmpl', (isset($this->site_settings['option_tmpl']) ? $this->site_settings['option_tmpl'] : ''), array('rows' => '2'))
		              ))
		   . $SD->block_c();

		return $r;
	}

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

		$cell2 = $DSP->qdiv('defaultBold', 'Checkbox Options')
		       . $DSP->qdiv('default', 'Put each item on a single line')
		       . $DSP->input_textarea('options', $options, '6', 'textarea', '99%');

		return array('cell2' => $cell2);
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
		$options = preg_split('/[\r\n]+/', $settings['options']);
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
				    . $DSP->input_checkbox("{$field_name}[]", $option_name, $checked)
				    . $option_label
				    . '</label>';
			}
		}
		return $r;
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

		if (isset($field_settings['options']))
		{
			// combine default param values with the passed params
			$params = array_merge(array(
				'sort'      => '',
				'backspace' => '0'
			), $params);

			// option template
			if ( ! $tagdata AND isset($this->site_settings['option_tmpl'])) $tagdata = $this->site_settings['option_tmpl'];

			$field_data = $field_data ? unserialize($field_data) : array();

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