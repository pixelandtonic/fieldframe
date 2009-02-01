<?php

if ( ! defined('EXT'))
{
	exit('Invalid file request');
}

/**
 * FieldFrame Class
 *
 * This extension provides a framework for ExpressionEngine Field Type development.
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Fieldframe
{
	var $class = 'Fieldframe';
	var $name = 'FieldFrame';
	var $version = '0.0.2';
	var $description = 'Field Type framework';
	var $settings_exist = 'y';
	var $docs_url = 'http://exp.fieldframe.com';

	/**
	 * FieldFrame Constructor
	 *
	 * @param array  $settings
	 */
	function Fieldframe($settings=array())
	{
		global $SESS;

		// get the site-specific settings
		$this->settings = $this->_get_site_settings($settings);

		// create a reference to the cache
		if ( ! isset($SESS->cache[$this->class]))
		{
			$SESS->cache[$this->class] = array();
		}
		$this->cache = &$SESS->cache[$this->class];

		// define constants
		if ( ! defined('FIELDS_PATH') AND $this->settings['fields_path'])
		{
			define('FIELDS_PATH', $this->settings['fields_path']);
		}
		if ( ! defined('FIELDS_URL') AND $this->settings['fields_url'])
		{
			define('FIELDS_URL', $this->settings['fields_url']);
		}

		$this->errors = array();

	}

	/**
	 * Get All Settings
	 *
	 * @return array  All extension settings
	 * @access private
	 */
	function _get_all_settings()
	{
		global $DB;
		$query = $DB->query("SELECT settings
		                     FROM exp_extensions
		                     WHERE class = '".$this->class."'
		                       AND settings != ''
		                     LIMIT 1");
		return $query->num_rows
		 ? unserialize($query->row['settings'])
		 : array();
	}

	/**
	 * Get Site Settings
	 *
	 * @param  array  $settings   All saved settings data
	 * @return array  Default settings merged with any site-specific settings in $settings
	 * @access private
	 */
	function _get_site_settings($settings=array())
	{
		global $PREFS;
		$defaults = array(
			'fields_url' => '',
			'fields_path' => '',
			'check_for_updates' => 'y',
			'fields' => array()
		);
		$site_id = $PREFS->ini('site_id');
		return isset($settings[$site_id])
		 ? array_merge($defaults, $settings[$site_id])
		 : $defaults;
	}

	/**
	 * Match Field Filename
	 *
	 * @param  string  $file  The filename in question
	 * @return mixed   The filename (string) without its 'field.' prefix and '.php'
	 *                 suffix if it's a Field file, or FALSE (bool)
	 * @access private
	 */
	function _match_field_filename($file)
	{
		return (substr($file, 0, 6) == 'field.' AND substr($file, -strlen(EXT) == EXT))
		 ? substr($file, 6, -strlen(EXT))
		 : FALSE;
	}

	/**
	 * Fetch Field Files
	 *
	 * @access private
	 */
	function _get_field_files()
	{
		if ( ! isset($this->cache['field_files']))
		{
			$this->cache['field_files'] = array();

			if ( ! defined('FIELDS_PATH'))
			{
				$this->errors[] = 'no_fields_path';
			}
			elseif ( ! $fp = @opendir(FIELDS_PATH))
			{
				$this->errors[] = 'bad_fields_path';
			}
			else
			{
				// iterate through the field folder contents
				while (($file = readdir($fp)) !== FALSE)
				{
					// skip hidden/navigational files
					if (substr($file, 0, 1) == '.') continue;

					// is this a subdirectory?
					if ($fp_sd = @opendir(FIELDS_PATH.$file))
					{
						while (($sd_file = readdir($fp_sd)) !== FALSE)
						{
							if (substr($sd_file, 0, 1) != '.' AND ($class_name = $this->_match_field_filename($sd_file)))
							{
								$this->cache['field_files'][$class_name] = $file.'/'.$sd_file;
							}
						}
						closedir($fp_sd);
					}
					elseif ($class_name = $this->_match_field_filename($file))
					{
						$this->cache['field_files'][$class_name] = $file;
					}
				}
				closedir($fp);

				if ( ! $this->cache['field_files'])
				{
					$this->errors[] = 'no_fields';
				}
			}
		}

		return $this->cache['field_files'];
	}

	/**
	 * Initialize Fields
	 *
	 * @param  bool  $include_disabled  Include non-enabled fields
	 * @access private
	 */
	function _get_fields($include_disabled=FALSE)
	{
		if ( ! isset($this->cache['fields']))
		{
			$this->cache['fields'] = array();
			$files = $this->_get_field_files();

			if (count($files))
			{
				$req_info = array('name', 'version', 'desc', 'docs_url', 'author', 'author_url', 'versions_xml_url');

				foreach($files as $class_name => $file)
				{
					$class_name = ucfirst($class_name);

					// import the file
					if ( ! class_exists($class_name))
					{
						@include(FIELDS_PATH.$file);

						// skip if the class doesn't exist
						if ( ! class_exists($class_name))
						{
							continue;
						}
					}

					if ( ! $include_disabled)
					{
						// skip if not enabled
						if ( ! (isset($this->settings['fields'][$class_name]) AND $this->settings['fields'][$class_name]['enabled'] == 'y'))
						{
							continue;
						}
					}

					$OBJ = new $class_name();

					// make sure it has all the required info
					if ( ! (isset($OBJ->settings) AND is_array($OBJ->settings)))
					{
						$OBJ->settings = array();
					}
					if ( ! (isset($OBJ->info) AND is_array($OBJ->info)))
					{
						$OBJ->info = array();
					}
					foreach($req_info as $item)
					{
						if ( ! isset($OBJ->info[$item]))
						{
							$OBJ->info[$item] = '';
						}
					}
					if ( ! $OBJ->info['name'])
					{
						$OBJ->info['name'] = ucwords(str_replace('_', ' ', $class_name));
					}

					// make sure it's accounted for in the settings
					if ( ! isset($this->settings['fields'][$class_name]))
					{
						$this->settings['fields'][$class_name] = array('enabled'=>'n');
					}

					// add it to fields
					$this->cache['fields'][$class_name] = $OBJ;
				}
			}
		}

		return $this->cache['fields'];
	}

	/**
	 * Settings Form
	 *
	 * Construct the custom settings form.
	 *
	 * @param  array   $current   Current extension settings (not site-specific)
	 * @see    http://expressionengine.com/docs/development/extensions.html#settings
	 */
	function settings_form($current)
	{
		// EE doesn't send the settings when initializing extensions on
		// settings forms, so we have to re-call FieldFrame() here
		$this->FieldFrame($current);

		global $DB, $DSP, $LANG, $IN;

		// Breadcrumbs
		$DSP->crumbline = TRUE;
		$DSP->title = $LANG->line('extension_settings');
		$DSP->crumb = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities'))
		            . $DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')))
		            . $DSP->crumb_item($this->name);
	    $DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

		// open form
		$DSP->body .= "<h1>{$this->name} <small>{$this->version}</small></h1>"
		            . $DSP->form_open(
		                  array(
		                    'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
		                    'name'   => 'settings_subtext',
		                    'id'     => 'settings_subtext'
		                  ),
		                  array(
		                    'name' => strtolower($this->class)
		                  ));

		// initialize FFSettingsDisplay
		$SD = new FFSettingsDisplay();

		// import lang files
		$LANG->fetch_language_file('publish_ad');

		// Fields folder
		$DSP->body .= $SD->block('fields_folder_title')
		            . $SD->row(array(
		                           $SD->label('fields_url_label', 'fields_url_subtext'),
		                           $SD->text('fields_url', $this->settings['fields_url'])
		                         ))
		            . $SD->row(array(
		                           $SD->label('fields_path_label', 'fields_path_subtext'),
		                           $SD->text('fields_path', $this->settings['fields_path'])
		                         ))
		            . $SD->block_c();

		// Check for Updates
		$lgau_query = $DB->query("SELECT class
		                          FROM exp_extensions
		                          WHERE class = 'Lg_addon_updater_ext'
		                            AND enabled = 'y'
		                          LIMIT 1");
		$lgau_enabled = $lgau_query->num_rows ? TRUE : FALSE;
		$DSP->body .= $SD->block('check_for_updates_title')
		            . $SD->info_row('check_for_updates_info')
		            . $SD->row(array(
		                           $SD->label('check_for_updates_label', 'check_for_updates_subtext'),
		                           $SD->radio_group('check_for_updates', (($this->settings['check_for_updates'] != 'n') ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no'))
		                         ))
		            . $SD->block_c();

		// Field settings
		$DSP->body .= $SD->block('field_manager', 4);

		// initialize fields
		$fields = $this->_get_fields(TRUE);

		if ($this->errors)
		{
			foreach($this->errors as $error)
			{
				$DSP->body .= $SD->info_row($error);
			}
		}
		else
		{
			// add the headers
			$DSP->body .= $SD->heading_row(array(
			                                   $LANG->line('field'),
			                                   $LANG->line('field_enabled'),
			                                   $LANG->line('settings'),
			                                   $LANG->line('documentation')
			                                 ));

			foreach($fields as $class_name=>$OBJ)
			{
				$info = &$OBJ->info;
				$enabled = ($this->settings['fields'][$class_name]['enabled'] == 'y');
				$DSP->body .= $SD->row(array(
				                         $SD->label($info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $info['version']), $info['desc']),
				                         $SD->radio_group('fields['.$class_name.'][enabled]', ($enabled ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no')),
				                         (count($OBJ->settings) ? '<a href="#">'.$LANG->line('settings').'</a>' : '--'),
				                         ($info['docs_url'] ? '<a href="'.stripslashes($info['docs_url']).'">'.$LANG->line('documentation').'</a>' : '--')
				                       ));
			}
		}

		$DSP->body .= $SD->block_c();

		// Close form
		$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit())
		            . $DSP->form_c();
	}

	/**
	 * Add Slash to URL/Path
	 *
	 * @param  string  $path  The user-submitted path
	 * @return string  $path with a slash at the end
	 * @access private
	 */
	function _add_slash($path)
	{
		if (substr($path, -1) != '/')
		{
			$path .= '/';
		}
		return $path;
	}

	/**
	 * Save Settings
	 *
	 */
	function save_settings()
	{
		global $DB, $PREFS;

		// get the default settings
		$this->settings = $this->_get_site_settings();

		$this->settings['fields_url'] = ($_POST['fields_url'] ? $this->_add_slash($_POST['fields_url']) : '');
		$this->settings['fields_path'] = ($_POST['fields_path'] ? $this->_add_slash($_POST['fields_path']) : '');
		$this->settings['check_for_updates'] = (($_POST['check_for_updates'] != 'n') ? 'y' : 'n');

		if (isset($_POST['fields']))
		{
			foreach($_POST['fields'] as $class_name => $field_post)
			{
				$this->settings['fields'][$class_name] = array(
					'enabled' => ($field_post['enabled'] == 'y' ? 'y' : 'n')
				);
			}
		}

		// save all settings
		$settings = $this->_get_all_settings();
		$settings[$PREFS->ini('site_id')] = $this->settings;
		$DB->query("UPDATE exp_extensions
		            SET settings = '".addslashes(serialize($settings))."'
		            WHERE class = '".$this->class."'");
	}

	/**
	 * Activate Extension
	 *
	 * Resets all FieldFrame exp_extensions rows
	 *
	 */
	function activate_extension()
	{
		global $DB;

		// Get settings
		$settings = array();

		// Delete old hooks
		$DB->query("DELETE FROM exp_extensions
		            WHERE class = '".$this->class."'");

		// Add new extensions
		$hook_tmpl = array(
			'class'    => $this->class,
			'settings' => addslashes(serialize($settings)),
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		);

		$hooks = array(
			// Publish Admin
			'publish_admin_edit_field_type_pulldown',
			'publish_admin_edit_field_type_cellone',
			'publish_admin_edit_field_type_celltwo',
			'publish_admin_edit_field_extra_row',
			'publish_admin_edit_field_format',
			'publish_admin_edit_field_js',

			// LG Addon Updater
			'lg_addon_update_register_source',
			'lg_addon_update_register_addon',
		);

		foreach($hooks as $hook)
		{
			$ext = array_merge($hook_tmpl, is_string($hook)
			                                ? array('hook'=>$hook, 'method'=>$hook)
			                                : $hook);
			$DB->query($DB->insert_string('exp_extensions', $ext));
		}
	}

	/**
	 * Update Extension
	 *
	 * @param string   $current  Previous installed version of the extension
	 */
	function update_extension($current='')
	{
		if ( ! $current OR $current == $this->version)
		{
			// why did you call me again?
			return FALSE;
		}

		if ($current < '0.0.2')
		{
			// hooks have changed, so go through
			// the whole activate_extension() process
			$this->activate_extension();
		}
		else
		{
			// just update the version nums
			global $DB;
			$DB->query("UPDATE exp_extensions
			            SET version = '".$DB->escape_str($this->version)."'
			            WHERE class = '".$this->class."'");
		}
	}

	/**
	 * Disable Extension
	 *
	 */
	function disable_extension()
	{
		global $DB;
		$DB->query("UPDATE exp_extensions
		            SET enabled = 'n'
		            WHERE class = '".$this->class."'");
	}

	/**
	 * Get Last Call
	 *
	 * @param  mixed  $param   Parameter sent by extension hook
	 * @return mixed  Return value of last extension call if any, or $param
	 */
	function _get_last_call($param='')
	{
		global $EXT;
		return ($EXT->last_call !== FALSE) ? $EXT->last_call : $param;
	}

	/**
	 * Edit Field Type Menu
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $typemenu  The contents of the type menu
	 * @return string  The modified $typemenu
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_pulldown/
	 */
	function publish_admin_edit_field_type_pulldown($data, $typemenu)
	{
		$r = $this->_get_last_call($typemenu);

		global $DSP;

		$fields = $this->_get_fields();
		foreach($fields as $class_name=>$OBJ)
		{
			$r .= $DSP->input_select_option($class_name, $OBJ->info['name']);
		}

		return $r;
	}

	/**
	 * Edit Field Type - Cell One
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $cell  The contents of the cell
	 * @return string  The modified $cell
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_cellone/
	 */
	function publish_admin_edit_field_type_cellone($data, $cell)
	{
		$cell = $this->_get_last_call($cell);
		return $cell;
	}

	/**
	 * Edit Field Type - Cell Two
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $cell  The contents of the cell
	 * @return string  The modified $cell
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_celltwo/
	 */
	function publish_admin_edit_field_type_celltwo($data, $cell)
	{
		$cell = $this->_get_last_call($cell);
		return $cell;
	}

	/**
	 * Edit Field - Add Extra Row
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $r     The current contents of the page
	 * @return string  The modified $r
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_extra_row/
	 */
	function publish_admin_edit_field_extra_row($data, $r)
	{
		$r = $this->_get_last_call($r);
		return $r;
	}

	/**
	 * Edit Field Format Menu
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $y     The current contents of the format cell
	 * @return string  The modified $y
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_format/
	 */
	function publish_admin_edit_field_format($data, $y)
	{
		$y = $this->_get_last_call($y);
		return $y;
	}

	/**
	 * Edit Field Javascript
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $js    Currently existing javascript
	 * @return string  The modified $js
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_js/
	 */
	function publish_admin_edit_field_js($data, $js)
	{
		$js = $this->_get_last_call($js);
		return $js;
	}

	/**
	 * Register a New Addon Source
	 *
	 * @param  array  $sources  The existing sources
	 * @return array  The new source list
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_source($sources)
	{
		$sources = $this->_get_last_call($sources);
		if ($this->settings['check_for_updates'] == 'y') {
			$source = 'http://brandon-kelly.com/downloads/versions.xml';
			if ( ! in_array($source, $sources)) {
				$sources[] = $source;
			}
		}
		return $sources;
	}

	/**
	 * Register a New Addon ID
	 *
	 * @param  array   $addons   The existing sources
	 * @return array             The new addon list
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_addon($addons)
	{
		$addons = $this->_get_last_call($addons);
	    if ($this->settings['check_for_updates'] == 'y') {
	        $addons[$this->class] = $this->version;
	    }
	    return $addons;
	}
}


/**
 * Settings Display Class
 *
 * Provides FieldFrame settings-specific display methods
 *
 * @package  FieldFrame
 * @author   Brandon Kelly <me@brandon-kelly.com>
 */
class FFSettingsDisplay
{
	/**
	 * FFSettingsDisplay Constructor
	 */
	function FFSettingsDisplay()
	{
		// initialize Display Class
		global $DSP;
		if ( ! $DSP)
		{
			if ( ! class_exists('Display'))
			{
				require PATH_CP.'cp.display'.EXT;
			}
			$DSP = new Display();
		}
	}

	/**
	 * Open Settings Block
	 *
	 * @param  string  $title_line  The block's title
	 * @return string  The block's head
	 */
	function block($title_line, $num_cols=2)
	{
		$this->row_count = 0;
		$this->num_cols = $num_cols;

		global $DSP;
		return $DSP->table_open(array(
		                          'class'  => 'tableBorder',
		                          'border' => '0',
		                          'style' => 'margin-top:18px; width:100%'
		                        ))
		     . $this->row(array($this->line($title_line)), 'tableHeading');
	}

	/**
	 * Close Settings Block
	 */
	function block_c()
	{
		global $DSP;
		return $DSP->table_c();
	}

	/**
	 * Settings Row
	 *
	 * @param  array   $col_data   Each column's contents
	 * @param  string  $row_class  CSS class to be added to each cell
	 * @return string  The settings row
	 */
	function row($col_data, $row_class=null)
	{
		// get the alternating row class
		if ($row_class === null)
		{
			$this->row_count++;
			$row_class = ($this->row_count % 2)
			 ? 'tableCellOne'
			 : 'tableCellTwo';
		}

		global $DSP;
		$r = $DSP->tr();
		$num_cols = count($col_data);
		foreach($col_data as $i => $col)
		{
			$width = ($i == 0) ? 55 : floor(45/($num_cols-1));
			$colspan = ($i == $num_cols-1) ? $this->num_cols - $i : null;
			$r .= $DSP->td($row_class, $width.'%', $colspan)
			    . $col
			    . $DSP->td_c();
		}
		$r .= $DSP->tr_c();
		return $r;
	}

	/**
	 * Heading Row
	 *
	 * @param  array   $cols  Each column's heading line
	 * @return string  The settings heading row
	 */
	function heading_row($cols)
	{
		return $this->row($cols, 'tableHeadingAlt');
	}

	/**
	 * Info Row
	 *
	 * @param  string  $info_line  Info text
	 * @return string  The settings info row
	 */
	function info_row($info_line)
	{
		return $this->row(array(
		                   '<div class="box" style="border-width:0 0 1px 0; margin:0; padding:10px 5px">'
		                 . '<p>'.$this->line($info_line).'</p>'
		                 . '</div>'
		                  ), '');
	}

	/**
	 * Label
	 *
	 * @param  string  $label_line    The main label text
	 * @param  string  $subtext_line  The label's subtext
	 * @return string  The label
	 */
	function label($label_line, $subtext_line='')
	{
		global $DSP;
		$r = $DSP->qdiv('defaultBold', $this->line($label_line));
		if ($subtext_line) $r .= $DSP->qdiv('subtext', $this->line($subtext_line));
		return $r;
	}

	/**
	 * Settings Text Input
	 *
	 * @param  string  $name   Name of the text field
	 * @param  string  $value  Initial value
	 * @param  array   $vars   Input variables
	 * @return string  The text field
	 */
	function text($name, $value, $vars=array())
	{
		global $DSP;
		$vars = array_merge(array('size'=>'','maxlength'=>'','style'=>'input','width'=>'90%','extras'=>'','convert'=>FALSE), $vars);
		return $DSP->input_text($name, $value, $vars['size'], $vars['maxlength'], $vars['style'], $vars['width'], $vars['extras'], $vars['convert']);
	}

	/**
	 * Textarea
	 *
	 * @param  string  $name   Name of the textarea
	 * @param  string  $value  Initial value
	 * @param  array   $vars   Input variables
	 * @return string  The textarea
	 */
	function textarea($name, $value, $vars=array())
	{
		global $DSP;
		$vars = array_merge(array('rows'=>'3','style'=>'textarea','width'=>'91%','extras'=>'','convert'=>FALSE), $vars);
		return $DSP->input_textarea($name, $value, $vars['rows'], $vars['style'], $vars['width'], $vars['extras'], $vars['convert']);
	}

	/**
	 * Select input
	 *
	 * @param  string  $name     Name of the select
	 * @param  mixed   $value    Initial selected value(s)
	 * @param  array   $options  List of the options
	 * @param  array   $vars     Input variables
	 * @return string  The select input
	 */
	function select($name, $value, $options, $vars=array())
	{
		global $DSP;
		$vars = array_merge(array('multi'=>0, 'size'=>0, 'width'=>''), $vars);
		$r = $DSP->input_select_header($name, $vars['multi'], $vars['size'], $vars['width']);
		foreach($options as $option_value => $option_line) {
			$selected = is_array($value)
			 ? in_array($option_value, $value)
			 : ($option_value == $value);
			$r .= $DSP->input_select_option($option_value, $this->line($option_line), $selected ? 1 : 0);
		}
		$r .= $DSP->input_select_footer();
		return $r;
	}

	/**
	 * Multiselect Input
	 *
	 * @param  string  $name     Name of the textfield
	 * @param  array   $values   Initial selected values
	 * @param  array   $options  List of the options
	 * @param  array   $vars     Input variables
	 * @return string  The multiselect input
	 */
	function multiselect($name, $values, $options, $vars=array())
	{
		$vars = array_merge($vars, array('multi'=>1));
		return $this->select($name, $values, $options, $vars);
	}

	/**
	 * Radio Group
	 *
	 * @param  string  $name     Name of the radio inputs
	 * @param  string  $value    Initial selected value
	 * @param  array   $options  List of the options
	 * @param  array   $vars     Input variables
	 * @return string  The text input
	 */
	function radio_group($name, $value, $options, $vars=array())
	{
		global $DSP;
		$vars = array_merge($vars, array('extras'=>''));
		$r = '';
		foreach($options as $option_value=>$option_name)
		{
			if ($r) $r .= NBS.NBS.' ';
			$r .= '<label style="white-space:nowrap;">'
			    . $DSP->input_radio($name, $option_value, ($option_value == $value) ? 1 : 0, $vars['extras'])
			    . ' '.$this->line($option_name)
			    . '</label>';
		}
		return $r;
	}

	/**
	 * Line
	 *
	 * @param  string  $line  unlocalized string or the name of a $LANG line
	 * @return string  Localized string
	 */
	function line($line)
	{
		global $LANG;
		$loc_line = $LANG->line($line);
		return $loc_line ? $loc_line : $line;
	}
}

?>