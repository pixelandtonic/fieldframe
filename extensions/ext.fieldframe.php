<?php

if ( ! defined('EXT'))
{
	exit('Invalid file request');
}

/**
 * FieldFrame Class
 *
 * This extension provides a framework for ExpressionEngine field type development.
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Fieldframe {

	var $class = 'Fieldframe';
	var $name = 'FieldFrame';
	var $version = '0.0.4';
	var $description = 'Field Type Framework';
	var $settings_exist = 'y';
	var $docs_url = 'http://eefields.com/';

	/**
	 * FieldFrame Class Constructor
	 *
	 * @param array  $settings
	 */
	function Fieldframe($settings=array())
	{
		// only initialize if we're not on the Settings page
		global $IN;
		if ( ! ($IN->GBL('M', 'GET') == 'utilities' AND ($IN->GBL('P', 'GET') == 'extension_settings')))
		{
			$this->_init($settings);
		}
	}

	/**
	 * FieldFrame Class Initialization
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

	/**
	 * Define constants
	 *
	 * @access private
	 */
	function _define_constants()
	{
		// define constants
		if ( ! defined('FT_PATH') AND $this->settings['fieldtypes_path'])
		{
			define('FT_PATH', $this->settings['fieldtypes_path']);
		}
		if ( ! defined('FT_URL') AND $this->settings['fieldtypes_url'])
		{
			define('FT_URL', $this->settings['fieldtypes_url']);
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
	 * Get Settings
	 *
	 * @param  array  $settings  All saved settings data
	 * @return array  Default settings merged with any site-specific settings in $settings
	 * @access private
	 */
	function _get_settings($settings=array())
	{
		global $PREFS;
		$defaults = array(
			'fieldtypes_url' => '',
			'fieldtypes_path' => '',
			'check_for_updates' => 'y'
		);
		$site_id = $PREFS->ini('site_id');
		return isset($settings[$site_id])
		 ? array_merge($defaults, $settings[$site_id])
		 : $defaults;
	}

	/**
	 * Get Field Types
	 *
	 * @return array  All enabled FF field types, indexed by class name
	 * @access private
	 */
	function _get_ftypes()
	{
		if ( ! isset($this->cache['ftypes']))
		{
			$this->cache['ftypes'] = array();

			// get enabled fields from the DB
			global $DB;
			$query = $DB->query("SELECT * FROM exp_ff_fieldtypes WHERE enabled = 'y'");

			if ($query->num_rows)
			{
				foreach($query->result as $ftype)
				{
					if (($OBJ = $this->_init_ftype($ftype)) !== FALSE)
					{
						$this->cache['ftypes'][$ftype['class']] = $OBJ;
					}
				}
			}
		}

		return $this->cache['ftypes'];
	}

	/**
	 * Get All Installed Field Types
	 *
	 * @return array  All installed FF field types, indexed by class name
	 * @access private
	 */
	function _get_all_installed_ftypes()
	{
		$ftypes = array();

		if ( $fp = @opendir(FT_PATH))
		{
			// iterate through the field folder contents
			while (($file = readdir($fp)) !== FALSE)
			{
				// skip hidden/navigational files
				if (substr($file, 0, 1) == '.') continue;

				// is this a directory, and does a ftype file exist inside it?
				if (is_dir(FT_PATH.$file) AND is_file(FT_PATH.$file.'/ft.'.$file.EXT))
				{
					$ftypes[$file] = $this->_init_ftype($file);
				}
			}
			closedir($fp);
		}

		return $ftypes;
	}

	/**
	 * Get Field Types Indexed By Field ID
	 *
	 * @return array  All enabled FF field types, indexed by the weblog field ID they're used in.
	 *                Strong possibility that there will be duplicate field types in here,
	 *                but it's not a big deal because they're just object references
	 * @access private
	 */
	function _get_ftypes_by_field_id()
	{
		global $DB;

		if ( ! isset($this->cache['ftypes_by_field_id']))
		{
			$this->cache['ftypes_by_field_id'] = array();
			// get the field types
			if ($ftypes = $this->_get_ftypes())
			{
				// get the field IDs and class names
				$query = $DB->query("SELECT field_id, field_type FROM exp_weblog_fields
				                       WHERE field_type IN ('ff_".implode("', 'ff_", array_keys($ftypes))."')");
				if ($query->num_rows)
				{
					foreach($query->result as $row)
					{
						// add fieldtype name to this field
						$class_name = substr($row['field_type'], 3);
						$this->cache['ftypes_by_field_id'][$row['field_id']] = &$ftypes[$class_name];
					}
				}
			}
		}

		return $this->cache['ftypes_by_field_id'];
	}

	/**
	 * Initialize Field Type
	 *
	 * @param  mixed   $ftype  field type's class name or its row in exp_ff_fieldtypes
	 * @return object  Initialized field type object
	 * @access private
	 */
	function _init_ftype($ftype)
	{
		$file = is_array($ftype) ? $ftype['class'] : $ftype;
		$class_name = ucfirst($file);

		if ( ! class_exists($class_name))
		{
			// import the file
			@include(FT_PATH.$file.'/ft.'.$file.EXT);

			// skip if the class doesn't exist
			if ( ! class_exists($class_name))
			{
				exit("Couldn't fild class '{$class_name}' - file is ".FT_PATH.$file.'/ft.'.$file.EXT);
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

		// do we already know about this field type?
		if (is_string($ftype))
		{
			global $DB;
			$query = $DB->query("SELECT * FROM exp_ff_fieldtypes WHERE class = '{$file}' LIMIT 1");
			$ftype = $query->row;
		}
		if ($ftype)
		{
			$OBJ->_is_new = FALSE;
			$OBJ->_is_enabled = $ftype['enabled'] == 'y' ? TRUE : FALSE;

			if ($OBJ->info['version'] != $ftype['version'])
			{
				$DB->query($DB->update_string('exp_ff_fieldtypes',
				                              array('version' => $OBJ->info['version']),
				                              "fieldtype_id = '{$ftype['fieldtype_id']}'"));

				if (method_exists($OBJ, 'update'))
				{
					$OBJ->update($ftype['version']);
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
	 * @param array  $current  Current extension settings (not site-specific)
	 * @see   http://expressionengine.com/docs/development/extensions.html#settings
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

		// fieldtypes folder
		$DSP->body .= $SD->block('fieldtypes_folder_title')
		            . $SD->row(array(
		                           $SD->label('fieldtypes_url_label', 'fieldtypes_url_subtext'),
		                           $SD->text('fieldtypes_url', $this->settings['fieldtypes_url'])
		                         ))
		            . $SD->row(array(
		                           $SD->label('fieldtypes_path_label', 'fieldtypes_path_subtext'),
		                           $SD->text('fieldtypes_path', $this->settings['fieldtypes_path'])
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

		// field type settings
		$DSP->body .= $SD->block('fieldtype_manager', 4);

		// initialize field types
		$ftypes = $this->_get_all_installed_ftypes();

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
			                                   $LANG->line('fieldtype'),
			                                   $LANG->line('fieldtype_enabled'),
			                                   $LANG->line('documentation')
			                                 ));

			foreach($ftypes as $class_name => $OBJ)
			{
				$DSP->body .= $SD->row(array(
				                         $SD->label($OBJ->info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $OBJ->info['version']), $OBJ->info['desc']),
				                         $SD->radio_group('ftypes['.$class_name.'][enabled]', ($OBJ->_is_enabled ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no')),
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
	 */
	function save_settings()
	{
		global $DB, $PREFS;
		$sql = array();

		// get the default FF settings
		$this->settings = $this->_get_settings();

		$this->settings['fieldtypes_url'] = $_POST['fieldtypes_url'] ? $this->_add_slash($_POST['fieldtypes_url']) : '';
		$this->settings['fieldtypes_path'] = $_POST['fieldtypes_path'] ? $this->_add_slash($_POST['fieldtypes_path']) : '';
		$this->settings['check_for_updates'] = ($_POST['check_for_updates'] != 'n') ? 'y' : 'n';

		// save all FF settings
		$settings = $this->_get_all_settings();
		$settings[$PREFS->ini('site_id')] = $this->settings;
		$sql[] = "UPDATE exp_extensions
		            SET settings = '".addslashes(serialize($settings))."'
		            WHERE class = '{$this->class}'";


		// field type settings
		if (isset($_POST['ftypes']))
		{
			$this->_define_constants();

			foreach($_POST['ftypes'] as $file => $ftype_post)
			{
				// skip if it's disabled
				if ($ftype_post['enabled'] != 'y') continue;

				// Initialize or skip
				if (($OBJ = $this->_init_ftype($file)) === FALSE) continue;

				$data = array('enabled' => $ftype_post['enabled'] == 'y' ? 'y' : 'n');

				// insert a new row if it's new
				if ($OBJ->_is_new)
				{
					$data['class'] = $file;
					$data['version'] = $OBJ->info['version'];
					$sql[] = $DB->insert_string('exp_ff_fieldtypes', $data);
				}
				else
				{
					$sql[] = $DB->update_string('exp_ff_fieldtypes', $data, "class = '{$file}'");
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
	 */
	function activate_extension()
	{
		global $DB;

		// Get settings
		$settings = $this->_get_all_settings();

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

		// exp_ff_fieldtypes
		if ( ! $DB->table_exists('exp_ff_fieldtypes'))
		{
			$DB->query("CREATE TABLE exp_ff_fieldtypes (
			              `fieldtype_id` int(10) unsigned NOT NULL auto_increment,
			              `class` varchar(50) NOT NULL default '',
			              `version` varchar(10) NOT NULL default '',
			              `enabled` char(1) NOT NULL default 'n',
			              PRIMARY KEY (`fieldtype_id`)
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

		//if ($current < '0.0.3')
		//{
			// hooks have changed, so go through
			// the whole activate_extension() process
			$this->activate_extension();
		//}
		//else
		//{
		//	// just update the version nums
		//	global $DB;
		//	$DB->query("UPDATE exp_extensions
		//	              SET version = '".$DB->escape_str($this->version)."'
		//	              WHERE class = '{$this->class}'");
		//}
	}

	/**
	 * Disable Extension
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
	 * @access private
	 */
	function _get_last_call($param='')
	{
		global $EXT;
		return ($EXT->last_call !== FALSE) ? $EXT->last_call : $param;
	}

	/**
	 * Publish Admin - Edit Field Form - Field Type Menu
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

		$ftypes = $this->_get_ftypes();
		foreach($ftypes as $class_name => $OBJ)
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
	 */
	function show_full_control_panel_end($out)
	{
		$out = $this->_get_last_call($out);
		global $IN, $DB, $REGX;

		// if we are displaying the custom field list
		if($IN->GBL('M', 'GET') == 'blog_admin' AND in_array($IN->GBL('P', 'GET'), array('field_editor', 'update_weblog_fields', 'delete_field')))
		{
			// get the FF fieldtypes
			foreach($this->_get_ftypes_by_field_id() as $field_id => $ftype)
			{
				// add fieldtype name to this field
				$out = preg_replace("/(C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id={$field_id}.*?<\/td>.*?<td.*?>.*?<\/td>.*?)<\/td>/is",
				                      '$1'.$REGX->form_prep($ftype->info['name']).'</td>', $out);
			}
		}

		return $out;
	}

	/**
	 * Publish Form - Unique Field
	 *
	 * @param  array   $row  Parameters for the field from the database
	 * @param  array   $field_data  If entry is not new, this will have field's current value
	 * @return string  The field's HTML
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_form_field_unique/
	 */
	function publish_form_field_unique($row, $field_data)
	{
		$ftypes = $this->_get_ftypes_by_field_id();

		if ( ! array_key_exists($row['field_id'], $ftypes))
		{
			return $this->_get_last_call();
		}

		$field_name = 'field_id_'.$row['field_id'];

		$OBJ = $ftypes[$row['field_id']];
		if (method_exists($OBJ, 'display_field'))
		{
			$r = $OBJ->display_field($field_name, $field_data);
		}
		else
		{
			global $DSP;
			$r = $DSP->input_text($field_name, $field_data, null, null, 'input', '100%');
		}

		return '<div style="padding:5px 27px 17px 17px;">'.$r.'</div>';
	}

	/**
	 * Publish Form - Submit New Entry
	 *
	 * @see    http://expressionengine.com/developers/extension_hooks/submit_new_entry_start/
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