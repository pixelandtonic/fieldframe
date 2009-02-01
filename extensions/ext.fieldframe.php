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
class FieldFrame
{
	var $name = 'FieldFrame';
	var $version = '0.0.1';
	var $description = 'Field Type framework';
	var $settings_exist = 'y';
	var $docs_url = 'http://exp.fieldframe.com';

	/**
	 * Extension Constructor
	 *
	 * @param array   $settings
	 * @since version 1.0.0
	 */
	function FieldFrame($settings=array())
	{
		// globalization
		global $FF;
		$FF = $this;

		// get the site-specific settings
		$this->settings = $this->_get_site_settings($settings);

		// define constants
		if ($this->settings['fields_url']) define('FIELDS_URL', $this->settings['fields_url']);
		if ($this->settings['fields_path']) define('FIELDS_PATH', $this->settings['fields_path']);

		// create fields array
		$this->field_files = array();
		$this->fields = array();
		$this->errors = array();
	}

	/**
	 * Get All Settings
	 *
	 * @return array   All extension settings
	 * @since  version 1.0.0
	 */
	function _get_all_settings()
	{
		global $DB;
		$query = $DB->query("SELECT settings
		                     FROM exp_extensions
		                     WHERE class = '".get_class($this)."'
		                       AND settings != ''
		                     LIMIT 1");
		return $query->num_rows
		 ? unserialize($query->row['settings'])
		 : array();
	}

	/**
	 * Get Site Settings
	 *
	 * @param  array   $settings   Current extension settings (not site-specific)
	 * @return array               Site-specific extension settings
	 * @since  version 1.0.0
	 */
	function _get_site_settings($settings=array())
	{
		global $PREFS;
		$defaults = array(
			'fields_url' => '',
			'fields_path' => '',
			'check_for_updates' => 'y'
		);
		$site_id = $PREFS->ini('site_id');
		return isset($settings[$site_id])
		 ? array_merge($defaults, $settings[$site_id])
		 : $defaults;
	}

	function _match_field_filename($file)
	{
		return (substr($file, 0, 6) == 'field.' AND substr($file, -strlen(EXT) == EXT))
		 ? substr($file, 6, -strlen(EXT))
		 : FALSE;
	}

	function _fetch_field_files()
	{
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

				// 
				if ($fp_sd = @opendir(FIELDS_PATH.$file))
				{
					while (($sd_file = readdir($fp_sd)) !== FALSE)
					{
						if (substr($sd_file, 0, 1) != '.' AND ($class_name = $this->_match_field_filename($sd_file)))
						{
							$this->field_files[$class_name] = $file.'/'.$sd_file;
						}
					}
					closedir($fp_sd);
				}
				elseif ($class_name = $this->_match_field_filename($file))
				{
					$this->field_files[$class_name] = $file;
				}
			}
			closedir($fp);

			if ( ! $this->field_files)
			{
				$this->errors[] = 'no_fields';
			}
		}
	}

	function _init_fields()
	{
		if ( ! $this->field_files)
		{
			$this->_fetch_field_files();
		}
		if ( ! $this->errors)
		{
			foreach($this->field_files as $class_name => $file)
			{
				$class_name = ucfirst($class_name);

				// import the file
				if ( ! class_exists($class_name))
				{
					@include(FIELDS_PATH.$file);
					if ( ! class_exists($class_name)) continue;
				}

				$this->fields[] = new $class_name();
			}
		}
	}

	/**
	 * Settings Form
	 *
	 * Construct the custom settings form.
	 *
	 * @param  array   $current   Current extension settings (not site-specific)
	 * @see    http://expressionengine.com/docs/development/extensions.html#settings
	 * @since  version 1.0.0
	 */
	function settings_form($current)
	{
		// EE doesn't send the settings when initializing
		// extensions on settings forms, so we have to
		// re-call FieldFrame() here
		$this->FieldFrame($current);

		global $DB, $DSP, $LANG, $IN;

		// Breadcrumbs
		$DSP->crumbline = TRUE;
		$DSP->title = $LANG->line('extension_settings');
		$DSP->crumb = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities'))
		            . $DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')))
		            . $DSP->crumb_item($this->name);
	    $DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

		// Open form
		$DSP->body .= "<h1>{$this->name} <small>{$this->version}</small></h1>"
		            . $DSP->form_open(
		                  array(
		                    'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
		                    'name'   => 'settings_example',
		                    'id'     => 'settings_example'
		                  ),
		                  array(
		                    'name' => strtolower(get_class($this))
		                  ));

		// Initialize FFSettingsDisplay
		$SD = new FFSettingsDisplay();

		// Fields folder
		$DSP->body .= $SD->block('Fields Folder')
		            . $SD->info_row('This setting is required for FieldFrame to work.')
		            . $SD->row(array(
		                           $SD->label('URL to your &ldquo;fields&rdquo; folder', '<i>ex:</i>'.NBS.NBS.'http://www.example.com/system/extensions/fields/'),
		                           $SD->text('fields_url', $this->settings['fields_url'])
		                         ))
		            . $SD->row(array(
		                           $SD->label('Fields Folder Path', '<i>ex:</i>'.NBS.NBS.'/var/www/public_html/system/extensions/fields/'),
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
		                           $SD->label('check_for_updates_label'),
		                           $SD->select('check_for_updates', (($lgau_enabled OR $this->settings['check_for_updates'] == 'y') ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no'))
		                         ))
		            . $SD->block_c();

		// Field settings
		$DSP->body .= $SD->block('Field Management', 3);

		// initialize fields
		$this->_init_fields();

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
			                                   'Field Name',
			                                   'Documentation',
			                                   'Settings'
			                                 ));

			foreach($this->fields as $field)
			{
				$info = &$field->info;
				$DSP->body .= $SD->row(array(
				                         $SD->label($info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $info['version']), $info['desc']),
				                         '<a href="'.stripslashes($info['docs_url']).'">Documentation</a>',
				                         '<a href="#">Settings</a>'
				                       ));
			}
		}

		$DSP->body .= $SD->block_c();

		// Close form
		$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit())
		            . $DSP->form_c();
	}

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
	 * @since version 1.0.0
	 */
	function save_settings()
	{
		global $DB, $PREFS;

		$settings = $this->_get_all_settings();

		// Save new settings
		$settings[$PREFS->ini('site_id')] = $this->settings = array(
			'fields_url' => ($_POST['fields_url'] ? $this->_add_slash($_POST['fields_url']) : ''),
			'fields_path' => ($_POST['fields_path'] ? $this->_add_slash($_POST['fields_path']) : ''),
			'check_for_updates' => ($_POST['check_for_updates'] ? $_POST['check_for_updates'] : 'y')
		);

		$DB->query("UPDATE exp_extensions
		            SET settings = '".addslashes(serialize($settings))."'
		            WHERE class = '".get_class($this)."'");
	}

	/**
	 * Activate Extension
	 *
	 * Resets all Editor exp_extensions rows
	 *
	 * @since version 1.0.0
	 */
	function activate_extension()
	{
		global $DB;

		// Get settings
		$settings = array();

		// Delete old hooks
		$DB->query("DELETE FROM exp_extensions
		            WHERE class = '".get_class($this)."'");

		// Add new extensions
		$ext_template = array(
			'class'    => get_class($this),
			'settings' => addslashes(serialize($settings)),
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		);

		$extensions = array(
			// LG Addon Updater
			array('hook'=>'lg_addon_update_register_source',    'method'=>'register_my_addon_source'),
			array('hook'=>'lg_addon_update_register_addon',     'method'=>'register_my_addon_id')
		);

		foreach($extensions as $extension)
		{
			$ext = array_merge($ext_template, $extension);
			$DB->query($DB->insert_string('exp_extensions', $ext));
		}
	}

	/**
	 * Update Extension
	 *
	 * @param string   $current   Previous installed version of the extension
	 * @since version 1.0.0
	 */
	function update_extension($current='')
	{
		//global $DB;
        //
		//if ($current == '' OR $current == $this->version)
		//{
		//	return FALSE;
		//}
        //
		//$DB->query("UPDATE exp_extensions
		//            SET version = '".$DB->escape_str($this->version)."'
		//            WHERE class = '".get_class($this)."'");
	}

	/**
	 * Disable Extension
	 *
	 * @since version 1.0.0
	 */
	function disable_extension()
	{
		global $DB;
		$DB->query("UPDATE exp_extensions
		            SET enabled = 'n'
		            WHERE class = '".get_class($this)."'");
	}

	/**
	 * Get Last Call
	 *
	 * @param  mixed   $param   Parameter sent by extension hook
	 * @return mixed            Return value of last extension call if any, or $param
	 * @since  version 1.0.0
	 */
	function _get_last_call($param='')
	{
		global $EXT;
		return ($EXT->last_call !== FALSE) ? $EXT->last_call : $param;
	}

	/**
	 * Register a New Addon Source
	 *
	 * @param  array   $sources   The existing sources
	 * @return array              The new source list
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 * @since  version 1.0.0
	 */
	function register_my_addon_source($sources)
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
	 * @since  version 1.0.0
	 */
	function register_my_addon_id($addons)
	{
		$addons = $this->_get_last_call($addons);
	    if ($this->settings['check_for_updates'] == 'y') {
	        $addons[get_class($this)] = $this->version;
	    }
	    return $addons;
	}
}


/**
 * Settings Display Class
 *
 * Provides FieldFrame settings-specific display methods
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 */
class FFSettingsDisplay
{
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

	function block_c()
	{
		global $DSP;
		return $DSP->table_c();
	}

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

	function heading_row($cols)
	{
		return $this->row($cols, 'tableHeadingAlt');
	}

	function info_row($info_line)
	{
		return $this->row(array(
		                   '<div class="box" style="border-width:0 0 1px 0; margin:0; padding:10px 5px">'
		                 . '<p>'.$this->line($info_line).'</p>'
		                 . '</div>'
		                  ), '');
	}

	function label($label_line, $subtext_line='')
	{
		global $DSP;
		$r = $DSP->qdiv('defaultBold', $this->line($label_line));
		if ($subtext_line) $r .= $DSP->qdiv('subtext', $this->line($subtext_line));
		return $r;
	}
   
	function text($name, $value, $vars=array())
	{
		global $DSP;
		$vars = array_merge(array('size'=>'','maxlength'=>'','style'=>'input','width'=>'90%','extras'=>'','convert'=>FALSE), $vars);
		return $DSP->input_text($name, $value, $vars['size'], $vars['maxlength'], $vars['style'], $vars['width'], $vars['extras'], $vars['convert']);
	}

	function textarea($name, $value, $vars=array())
	{
		global $DSP;
		$vars = array_merge(array('rows'=>'3','style'=>'textarea','width'=>'91%','extras'=>'','convert'=>FALSE), $vars);
		return $DSP->input_textarea($name, $value, $vars['rows'], $vars['style'], $vars['width'], $vars['extras'], $vars['convert']);
	}

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

	function multiselect($name, $values, $options, $vars=array())
	{
		$vars = array_merge($vars, array('multi'=>1));
		return $this->select($name, $values, $options, $vars);
	}

	function line($line)
	{
		global $LANG;
		$loc_line = $LANG->line($line);
		return $loc_line ? $loc_line : $line;
	}
}

?>