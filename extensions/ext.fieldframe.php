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
class Fieldframe {

	var $class = 'Fieldframe';
	var $name = 'FieldFrame';
	var $version = '0.0.3';
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
		// only initialize if we're not on the Settings page
		if ( ! ($IN->GBL('M', 'GET') == 'utilities' AND ($IN->GBL('P', 'GET') == 'extension_settings')))
		{
			$this->_init($settings);
		}
	}

	/**
	 * FieldFrame Initialization
	 *
	 * @param array  $settings
	 */
	function _init($settings)
	{
		global $SESS;

		// get the site-specific settings
		$this->settings = $this->_get_settings($settings);

		// create a reference to the cache
		if ( ! isset($SESS->cache[$this->class]))
		{
			$SESS->cache[$this->class] = array();
		}
		$this->cache = &$SESS->cache[$this->class];

		$this->_define_constants();

		$this->errors = array();
	}

	function _define_constants()
	{
		// define constants
		if ( ! defined('FIELDS_PATH') AND $this->settings['fields_path'])
		{
			define('FIELDS_PATH', $this->settings['fields_path']);
		}
		if ( ! defined('FIELDS_URL') AND $this->settings['fields_url'])
		{
			define('FIELDS_URL', $this->settings['fields_url']);
		}
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
		$query = $DB->query("SELECT settings FROM exp_extensions
		                       WHERE class = '{$this->class}' AND settings != '' LIMIT 1");
		return $query->num_rows
		 ? unserialize($query->row['settings'])
		 : array();
	}

	/**
	 * Get Site Settings
	 *
	 * @param  array  $settings  All saved settings data
	 * @return array  Default settings merged with any site-specific settings in $settings
	 * @access private
	 */
	function _get_settings($settings=array())
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

	function _get_fields()
	{
		if ( ! isset($this->cache['fields']))
		{
			$this->cache['fields'] = array();

			// get enabled fields from the DB
			global $DB;
			$query = $DB->query("SELECT class FROM exp_ff_fields WHERE enabled = 'y'");

			if ($query->num_rows)
			{
				foreach($query->result as $field)
				{
					if (($OBJ = $this->_init_field($field['class'])) !== FALSE)
					{
						$this->cache['fields'][$field['class']] = $OBJ;
					}
				}
			}
		}

		return $this->cache['fields'];
	}

	function _get_installed_fields()
	{
		$fields = array();

		if ( $fp = @opendir(FIELDS_PATH))
		{
			// iterate through the field folder contents
			while (($file = readdir($fp)) !== FALSE)
			{
				// skip hidden/navigational files
				if (substr($file, 0, 1) == '.') continue;

				// is this a directory, and does a field file exist inside it?
				if (is_dir(FIELDS_PATH.$file) AND is_file(FIELDS_PATH.$file.'/field.'.$file.EXT))
				{
					$fields[$file] = $this->_init_field($file);
				}
			}
			closedir($fp);
		}

		return $fields;
	}

	/**
	 * Initialize Field
	 *
	 * @param  string  $file  Field's folder name
	 * @access private
	 */
	function _init_field($file)
	{
		$class_name = ucfirst($file);

		if ( ! class_exists($class_name))
		{
			// import the file
			@include(FIELDS_PATH.$file.'/field.'.$file.EXT);

			// skip if the class doesn't exist
			if ( ! class_exists($class_name))
			{
				exit("Couldn't fild class '{$class_name}' - file is ".FIELDS_PATH.$file.'/field.'.$file.EXT);
				return FALSE;
			}
		}

		// initialize object
		$OBJ = new $class_name();

		// settings
		if ( ! isset($OBJ->settings)) $OBJ->settings = array();

		// info
		if ( ! isset($OBJ->info)) $OBJ->info = array();
		if ( ! isset($OBJ->info['name'])) $OBJ->info['name'] = ucwords(str_replace('_', ' ', $class_name));
		if ( ! isset($OBJ->info['version'])) $OBJ->info['version'] = '';
		if ( ! isset($OBJ->info['desc'])) $OBJ->info['desc'] = '';
		if ( ! isset($OBJ->info['docs_url'])) $OBJ->info['docs_url'] = '';
		if ( ! isset($OBJ->info['author'])) $OBJ->info['author'] = '';
		if ( ! isset($OBJ->info['author_url'])) $OBJ->info['author_url'] = '';
		if ( ! isset($OBJ->info['versions_xml_url'])) $OBJ->info['versions_xml_url'] = '';

		// do we already know about this field?
		global $DB;
		$query = $DB->query("SELECT * FROM exp_ff_fields WHERE class = '{$file}' LIMIT 1");
		if ($query->row)
		{
			$OBJ->_is_new = FALSE;
			$OBJ->_is_enabled = $query->row['enabled'] == 'y' ? TRUE : FALSE;

			if ($OBJ->info['version'] != $query->row['version'])
			{
				$DB->query($DB->update_string('exp_ff_fields',
				                              array('version' => $OBJ->info['version']),
				                              "field_id = '{$query->row['field_id']}'"));

				if (method_exists($OBJ, 'update'))
				{
					$OBJ->update($query->row['version']);
				}
			}
		}
		else
		{
			$OBJ->_is_new = TRUE;
			$OBJ->_is_enabled = FALSE;
		}

		return $OBJ;
	}

	/**
	 * Settings Form
	 *
	 * Construct the custom settings form.
	 *
	 * @param  array  $current  Current extension settings (not site-specific)
	 * @see    http://expressionengine.com/docs/development/extensions.html#settings
	 */
	function settings_form($current)
	{
		// EE doesn't send the settings when initializing extensions on
		// settings forms, so we have to initialize here instead
		$this->_init($current);

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
		$lgau_query = $DB->query("SELECT class FROM exp_extensions
		                            WHERE class = 'Lg_addon_updater_ext' AND enabled = 'y' LIMIT 1");
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
		$fields = $this->_get_installed_fields();

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

			foreach($fields as $class_name => $OBJ)
			{
				$DSP->body .= $SD->row(array(
				                         $SD->label($OBJ->info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $OBJ->info['version']), $OBJ->info['desc']),
				                         $SD->radio_group('fields['.$class_name.'][enabled]', ($OBJ->_is_enabled ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no')),
				                         (count($OBJ->settings) ? '<a href="#">'.$LANG->line('settings').'</a>' : '--'),
				                         ($OBJ->info['docs_url'] ? '<a href="'.stripslashes($OBJ->info['docs_url']).'">'.$LANG->line('documentation').'</a>' : '--')
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
		$sql = array();

		// get the default FF settings
		$this->settings = $this->_get_settings();

		$this->settings['fields_url'] = $_POST['fields_url'] ? $this->_add_slash($_POST['fields_url']) : '';
		$this->settings['fields_path'] = $_POST['fields_path'] ? $this->_add_slash($_POST['fields_path']) : '';
		$this->settings['check_for_updates'] = ($_POST['check_for_updates'] != 'n') ? 'y' : 'n';

		// save all FF settings
		$settings = $this->_get_all_settings();
		$settings[$PREFS->ini('site_id')] = $this->settings;
		$sql[] = "UPDATE exp_extensions
		            SET settings = '".addslashes(serialize($settings))."'
		            WHERE class = '{$this->class}'";


		// Field settings
		if (isset($_POST['fields']))
		{
			$this->_define_constants();

			foreach($_POST['fields'] as $file => $field_post)
			{
				// skip if it's disabled
				if ($field_post['enabled'] != 'y') continue;

				// Initialize or skip
				if (($OBJ = $this->_init_field($file)) === FALSE) continue;

				$data = array('enabled' => $field_post['enabled'] == 'y' ? 'y' : 'n');

				// insert a new row if it's new
				if ($OBJ->_is_new)
				{
					$data['class'] = $file;
					$data['version'] = $OBJ->info['version'];
					$sql[] = $DB->insert_string('exp_ff_fields', $data);
				}
				else
				{
					$sql[] = $DB->update_string('exp_ff_fields', $data, "class = '{$file}'");
				}
			}
		}

		// write to the DB
		foreach($sql as $query)
		{
			$DB->query($query);
		}
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
		              WHERE class = '{$this->class}'");

		// Add new extensions
		$hook_tmpl = array(
			'class'    => $this->class,
			'settings' => addslashes(serialize($settings)),
			'priority' => 10,
			'version'  => $this->version,
			'enabled'  => 'y'
		);

		$hooks = array(
			// Edit Field Form
			'publish_admin_edit_field_type_pulldown',
			'publish_admin_edit_field_type_cellone',
			'publish_admin_edit_field_type_celltwo',
			'publish_admin_edit_field_extra_row',
			'publish_admin_edit_field_format',
			'publish_admin_edit_field_js',

			// Field Manager
			'show_full_control_panel_end',

			// Entry Form
			'publish_form_field_unique',
			'submit_new_entry_start',

			// LG Addon Updater
			'lg_addon_update_register_source',
			'lg_addon_update_register_addon',
		);

		foreach($hooks as $hook)
		{
			$ext = array_merge($hook_tmpl, is_string($hook)
			                                ? array('hook' => $hook, 'method' => $hook)
			                                : $hook);
			$DB->query($DB->insert_string('exp_extensions', $ext));
		}

		// exp_ff_fields
		if ( ! $DB->table_exists('exp_ff_fields'))
		{
			$DB->query("CREATE TABLE exp_ff_fields (
			              `field_id` int(10) unsigned NOT NULL auto_increment,
			              `class` varchar(50) NOT NULL default '',
			              `version` varchar(10) NOT NULL default '',
			              `enabled` char(1) NOT NULL default 'n',
			              PRIMARY KEY (`field_id`)
			            )");
		}
	}

	/**
	 * Update Extension
	 *
	 * @param string  $current  Previous installed version of the extension
	 */
	function update_extension($current='')
	{
		if ( ! $current OR $current == $this->version)
		{
			// why did you call me again?
			return FALSE;
		}

		if ($current < '0.0.3')
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
			              WHERE class = '{$this->class}'");
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
		              WHERE class = '{$this->class}'");
	}

	/**
	 * Get Last Call
	 *
	 * @param  mixed  $param  Parameter sent by extension hook
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
		foreach($fields as $class_name => $OBJ)
		{
			$r .= $DSP->input_select_option('ff_'.$class_name, $OBJ->info['name']);
		}

		return $r;
	}

	/**
	 * Publish Admin - Edit Field Form - Cell One
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
	 * Publish Admin - Edit Field Form - Cell Two
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
	 * Publish Admin - Edit Field Form - Extra Row
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
	 * Publish Admin - Edit Field Form - Format
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
	 * Publish Admin - Edit Field Form - Javascript
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
	 * Display - Show Full Control Panel - End
	 *
	 * Fill in missing Field Types
	 *
	 * @param  string  $end  The content of the admin page to be outputted
	 * @return string  The modified $out
	 * @see    http://expressionengine.com/developers/extension_hooks/show_full_control_panel_end/
	 * @author Mark Huot
	 */
	function show_full_control_panel_end($out)
	{
		$out = $this->_get_last_call($out);

		/*// if we are displaying the custom field list
		if($IN->GBL('M', 'GET') == 'blog_admin' && ($IN->GBL('P', 'GET') == 'field_editor' || $IN->GBL('P', 'GET') == 'update_weblog_fields')  || $IN->GBL('P', 'GET') == 'delete_field')
		{
			// get the table rows
			if( preg_match_all("/C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id=(\d*).*?<\/td>.*?<td.*?>.*?<\/td>.*?<\/td>/is", $out, $matches) )
			{
				// for each field id
				foreach($matches[1] as $key => $field_id)
				{
					// get the field type
					$query = $DB->query("SELECT field_type FROM exp_weblog_fields WHERE field_id='" . $DB->escape_str($field_id) . "' LIMIT 1");
        
					$out = preg_replace("/(C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id=" . $field_id . ".*?<\/td>.*?<td.*?>.*?<\/td>.*?)<\/td>/is", "$1" . $REGX->form_prep($this->name) . "</td>", $out);
        
					// if the field type is wysiwyg
					if($query->row["field_type"] == $this->type)
					{
						$out = preg_replace("/(C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id=" . $field_id . ".*?<\/td>.*?<td.*?>.*?<\/td>.*?)<\/td>/is", "$1" . $REGX->form_prep($this->name) . "</td>", $out);
					}
				}
			}
		}*/

		return $out;
	}

	/**
	 * Publish Form - Unique Field
	 *
	 * @param  array   $row  Parameters for the field from the database
	 * @param  array   $field_data  If entry is not new, this will have field's current value
	 * @return string  The field
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_form_field_unique/
	 * @author Mark Huot
	 */
	function publish_form_field_unique($row, $field_data)
	{
		$r = $this->_get_last_call();
		return $r;
	}

	/**
	 * Publish Form - Submit New Entry
	 *
	 * @see    http://expressionengine.com/developers/extension_hooks/submit_new_entry_start/
	 * @author Mark Huot
	 */
	function submit_new_entry_start()
	{
		
	}

	/**
	 * LG Data Matrix - Register a New Addon Source
	 *
	 * @param  array  $sources  The existing sources
	 * @return array  The new source list
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_source($sources)
	{
		$sources = $this->_get_last_call($sources);
		if ($this->settings['check_for_updates'] == 'y')
		{
			$source = 'http://brandon-kelly.com/downloads/versions.xml';
			if ( ! in_array($source, $sources))
			{
				$sources[] = $source;
			}
		}
		return $sources;
	}

	/**
	 * LG Data Matrix - Register a New Addon ID
	 *
	 * @param  array  $addons  The existing sources
	 * @return array  The new addon list
	 * @see    http://leevigraham.com/cms-customisation/expressionengine/lg-addon-updater/
	 */
	function lg_addon_update_register_addon($addons)
	{
		$addons = $this->_get_last_call($addons);
		if ($this->settings['check_for_updates'] == 'y')
		{
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
class FFSettingsDisplay {

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
	function row($col_data, $row_class=NULL)
	{
		// get the alternating row class
		if ($row_class === NULL)
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
			$colspan = ($i == $num_cols-1) ? $this->num_cols - $i : NULL;
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
		foreach($options as $option_value => $option_line)
		{
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
		foreach($options as $option_value => $option_name)
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



/* End of file ext.fieldframe.php */
/* Location: ./system/extensions/ext.fieldframe.php */