<?php

if ( ! defined('EXT')) exit('Invalid file request');

// define FF constants
// (used by Fieldframe and Fieldframe_Main)
if ( ! defined('FF_CLASS'))
{
	define('FF_CLASS',   'Fieldframe');
	define('FF_NAME',    'FieldFrame');
	define('FF_VERSION', '0.2.0');
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
class Fieldframe_Base {

	var $name           = FF_NAME;
	var $version        = FF_VERSION;
	var $description    = 'Field Type Framework';
	var $settings_exist = 'y';
	var $docs_url       = 'http://eefields.com/';

	var $hooks = array(
		'sessions_start' => array('priority' => 1),

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

		// Templates
		'weblog_entries_tagdata' => array('priority' => 1),

		// LG Addon Updater
		'lg_addon_update_register_source',
		'lg_addon_update_register_addon'
	);

	/**
	 * FieldFrame Class Constructor
	 *
	 * @param array  $settings
	 */
	function Fieldframe_Base($settings=FALSE)
	{
		// only initialize if we're not on the Settings page
		global $PREFS;
		if ($settings !== FALSE && isset($settings[$PREFS->ini('site_id')]))
		{
			$this->_init_main($settings);
		}
	}

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
		global $DB;

		// Get settings
		$settings = Fieldframe_Main::_get_all_settings();

		// Delete old hooks
		$DB->query('DELETE FROM exp_extensions
		              WHERE class = "'.FF_CLASS.'"
		                      AND method NOT LIKE "forward_hook:%"');

		// Add new extensions
		$hook_tmpl = array(
			'class'    => FF_CLASS,
			'settings' => addslashes(serialize($settings)),
			'priority' => 10,
			'version'  => FF_VERSION,
			'enabled'  => 'y'
		);

		foreach($this->hooks as $hook => $data)
		{
			if (is_string($data))
			{
				$hook = $data;
				$data = array();
			}
			$data = array_merge($hook_tmpl, array('hook' => $hook, 'method' => $hook), $data);
			$DB->query($DB->insert_string('exp_extensions', $data));
		}

		// exp_ff_fieldtypes
		if ( ! $DB->table_exists('exp_ff_fieldtypes'))
		{
			$DB->query("CREATE TABLE exp_ff_fieldtypes (
			              `fieldtype_id` int(10) unsigned NOT NULL auto_increment,
			              `site_id`      int(4)  unsigned NOT NULL default '1',
			              `class`        varchar(50)      NOT NULL default '',
			              `version`      varchar(10)      NOT NULL default '',
			              `settings`     text             NOT NULL default '',
			              `enabled`      char(1)          NOT NULL default 'n',
			              PRIMARY KEY (`fieldtype_id`)
			            )");
		}

		// exp_ff_fieldtype_hooks
		if ( ! $DB->table_exists('exp_ff_fieldtype_hooks'))
		{
			$DB->query("CREATE TABLE exp_ff_fieldtype_hooks (
			              `hook_id`  int(10) unsigned NOT NULL auto_increment,
			              `class`    varchar(50)      NOT NULL default '',
			              `hook`     varchar(50)      NOT NULL default '',
			              `method`   varchar(50)      NOT NULL default '',
			              `priority` int(2)           NOT NULL DEFAULT '10',
			              PRIMARY KEY (`hook_id`)
			            )");
		}

		// exp_weblog_fields.ff_settings
		$query = $DB->query("SHOW COLUMNS FROM `{$DB->prefix}weblog_fields` LIKE 'ff_settings'");
		if ( ! $query->num_rows)
		{
			$DB->query("ALTER TABLE `{$DB->prefix}weblog_fields` ADD COLUMN `ff_settings` text NOT NULL");
		}
	}

	/**
	 * Update Extension
	 *
	 * @param string  $current  Previous installed version of the extension
	 */
	function update_extension($current='')
	{
		global $DB;

		if ( ! $current OR $current == FF_VERSION)
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
		//	              SET version = '".$DB->escape_str(FF_VERSION)."'
		//	              WHERE class = '{FF_CLASS}'");
		//}
	}

	/**
	 * Disable Extension
	 */
	function disable_extension()
	{
		global $DB;
		$DB->query($DB->update_string('exp_extensions', array('enabled' => 'n'), 'class = "'.FF_CLASS.'"'));
	}

	/**
	 * Settings Form
	 *
	 * @param array  $settings
	 */
	function settings_form($settings=array())
	{
		$this->_init_main($settings);

		global $FF;
		$FF->settings_form();
	}

	function save_settings()
	{
		$settings = Fieldframe_Main::_get_all_settings();
		$this->_init_main($settings);

		global $FF;
		$FF->save_settings();
	}

	/**
	 * Initialize Main class
	 *
	 * @param  array  $settings
	 * @access private
	 */
	function _init_main($settings)
	{
		global $SESS, $FF;

		if ( ! isset($FF))
		{
			$SESS->cache[FF_CLASS] = array();
			$FF = new Fieldframe_Main($settings, $this->hooks);
		}
	}

	/**
	 * _call Magic Method
	 *
	 * Routes calls to missing methods to the $OBJ
	 *
	 * @param string  $method  Name of the missing method
	 * @param array   $args    Arguments sent to the missing method
	 */
	function _call($method, $args)
	{
		global $FF;

		if (isset($FF))
		{
			if (method_exists($FF, $method))
			{
				return call_user_func_array(array(&$FF, $method), $args);
			}
			else if (substr($method, 0, 13) == 'forward_hook:')
			{
				$ext = explode(':', $method); // [ forward_hook, hook name, priority ]
				return call_user_func_array(array(&$FF, 'forward_hook'), array($ext[1], $ext[2], $args));
			}
		}

		return FALSE;
	}

}

// define actual Fieldframe class which extends
// Fieldframe_Base with PHP version-targetted methods
if (phpversion() >= '5')
{
	eval('
		class Fieldframe extends Fieldframe_Base {

			function __call($method, $args)
			{
				return $this->_call($method, $args);
			}
		}
	');
}
else
{
	eval('
		class Fieldframe extends Fieldframe_Base {

			function __call($method, $args, &$return_value)
			{
				$return_value = $this->_call($method, $args);
			}
		}
	');
}

/**
 * FieldFrame_Main Class
 *
 * Provides the core extension logic + hooks
 *
 * @package   FieldFrame
 */
class Fieldframe_Main {

	function log()
	{
		foreach(func_get_args() as $var)
		{
			if (is_string($var))
			{
				echo "<code style='display:block; margin:0; padding:5px 10px;'>{$var}</code>";
			}
			else
			{
				echo '<pre style="display:block; margin:0; padding:5px 10px; width:auto">';
				print_r($var);
				echo '</pre>';
			}
		}
	}

	var $ftype_hooks = array();

	/**
	 * FieldFrame_Main Class Initialization
	 *
	 * @param array  $settings
	 */
	function Fieldframe_Main($settings, $hooks)
	{
		global $SESS, $DB;

		$this->hooks = $hooks;

		// get the site-specific settings
		$this->settings = $this->_get_settings($settings);

		// create a reference to the cache
		$this->cache = &$SESS->cache[FF_CLASS];

		// define fieldtype folder constants
		if ( ! defined('FT_PATH') AND $this->settings['fieldtypes_path']) define('FT_PATH', $this->settings['fieldtypes_path']);
		if ( ! defined('FT_URL') AND $this->settings['fieldtypes_url']) define('FT_URL', $this->settings['fieldtypes_url']);
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
		$query = $DB->query('SELECT settings FROM exp_extensions
		                       WHERE class = "'.FF_CLASS.'" AND settings != "" LIMIT 1');
		return $query->num_rows
		  ?  unserialize($query->row['settings'])
		  :  array();
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
		  ?  array_merge($defaults, $settings[$site_id])
		  :  $defaults;
	}

	/**
	 * Get Field Types
	 *
	 * @return array  All enabled FF field types, indexed by class name
	 * @access private
	 */
	function _get_ftypes()
	{
		global $DB, $PREFS;

		if ( ! isset($this->cache['ftypes']))
		{
			$this->cache['ftypes'] = array();

			// get enabled fields from the DB
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes
			                       WHERE site_id = "'.$PREFS->ini('site_id').'"
			                         AND enabled = "y"');

			if ($query->num_rows)
			{
				foreach($query->result as $row)
				{
					if (($ftype = $this->_init_ftype($row)) !== FALSE)
					{
						$this->cache['ftypes'][$row['class']] = $ftype;
					}
				}
			}
		}

		return $this->cache['ftypes'];
	}

	function _get_ftype($class_name)
	{
		$ftypes = $this->_get_ftypes();
		return isset($ftypes[$class_name])
		  ?  $ftypes[$class_name]
		  :  FALSE;
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
					if (($ftype = $this->_init_ftype($file)) !== FALSE)
					{
						$ftypes[$file] = $ftype;
					}
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
	function _get_fields()
	{
		global $DB, $REGX;

		if ( ! isset($this->cache['ftypes_by_field_id']))
		{
			$this->cache['ftypes_by_field_id'] = array();

			// get the field types
			if ($ftypes = $this->_get_ftypes())
			{
				// sort them by ID rather than class
				$ftypes_by_id = array();
				foreach($ftypes as $class_name => $ftype)
				{
					$ftypes_by_id[$ftype->_fieldtype_id] = $ftype;
				}

				// get the field info
				$query = $DB->query("SELECT field_id, field_name, field_type, ff_settings FROM exp_weblog_fields
				                       WHERE field_type IN ('ftype_id_".implode("', 'ftype_id_", array_keys($ftypes_by_id))."')");
				if ($query->num_rows)
				{
					foreach($query->result as $row)
					{
						$ftype_id = substr($row['field_type'], 9);
						$this->cache['ftypes_by_field_id'][$row['field_id']] = array(
							'name' => $row['field_name'],
							'ftype' => $ftypes_by_id[$ftype_id],
							'settings' => $row['ff_settings'] ? $REGX->array_stripslashes(unserialize($row['ff_settings'])) : array()
						);
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
		global $DB, $PREFS, $REGX;

		$file = is_array($ftype) ? $ftype['class'] : $ftype;
		$class_name = ucfirst($file);

		if ( ! class_exists($class_name))
		{
			// import the file
			@include(FT_PATH.$file.'/ft.'.$file.EXT);

			// skip if the class doesn't exist
			if ( ! class_exists($class_name))
			{
				return FALSE;
			}
		}

		// initialize object
		$OBJ = new $class_name();

		// is this a FieldFrame field type?
		if ( ! isset($OBJ->_fieldframe)) return FALSE;

		$OBJ->_class_name = $file;
		$OBJ->_is_new     = FALSE;
		$OBJ->_is_enabled = FALSE;
		$OBJ->_is_updated = FALSE;

		// settings
		$OBJ->site_settings = array();

		// info
		if ( ! isset($OBJ->info)) $OBJ->info = array();
		if ( ! isset($OBJ->info['name'])) $OBJ->info['name'] = ucwords(str_replace('_', ' ', $class_name));
		if ( ! isset($OBJ->info['version'])) $OBJ->info['version'] = '';
		if ( ! isset($OBJ->info['desc'])) $OBJ->info['desc'] = '';
		if ( ! isset($OBJ->info['docs_url'])) $OBJ->info['docs_url'] = '';
		if ( ! isset($OBJ->info['author'])) $OBJ->info['author'] = '';
		if ( ! isset($OBJ->info['author_url'])) $OBJ->info['author_url'] = '';
		if ( ! isset($OBJ->info['versions_xml_url'])) $OBJ->info['versions_xml_url'] = '';

		// hooks
		if ( ! isset($OBJ->hooks)) $OBJ->hooks = array();

		// do we already know about this field type?
		if (is_string($ftype))
		{
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes
			                       WHERE site_id = "'.$PREFS->ini('site_id').'"
			                         AND class = "'.$file.'"');
			$ftype = $query->row;
		}
		if ($ftype)
		{
			$OBJ->_fieldtype_id = $ftype['fieldtype_id'];
			if ($ftype['enabled'] == 'y') $OBJ->_is_enabled = TRUE;
			if ($ftype['settings']) $OBJ->site_settings = $REGX->array_stripslashes(unserialize($ftype['settings']));

			// new version?
			if ($OBJ->info['version'] != $ftype['version'])
			{
				$OBJ->_is_updated = TRUE;

				// update exp_ff_fieldtypes
				$DB->query($DB->update_string('exp_ff_fieldtypes',
				                              array('version' => $OBJ->info['version']),
				                              'fieldtype_id = "'.$ftype['fieldtype_id'].'"'));

				// update the hooks
				$this->_insert_ftype_hooks($OBJ);

				// call update()
				if (method_exists($OBJ, 'update'))
				{
					$OBJ->update($ftype['version']);
				}
			}
		}
		else
		{
			$OBJ->_is_new = TRUE;
		}

		return $OBJ;
	}

	function _insert_ftype_hooks($ftype)
	{
		global $DB;

		// remove any existing hooks from exp_ff_fieldtype_hooks
		$DB->query('DELETE FROM exp_ff_fieldtype_hooks
		              WHERE class = "'.$ftype->_class_name.'"');

		// (re)insert the hooks
		if ($ftype->hooks)
		{
			foreach($ftype->hooks as $hook => $data)
			{
				if (is_string($data))
				{
					$hook = $data;
					$data = array();
				}

				// exp_ff_fieldtype_hooks
				$data = array_merge(array('method' => $hook, 'priority' => 10), $data, array('hook' => $hook, 'class' => $ftype->_class_name));
				$DB->query($DB->insert_string('exp_ff_fieldtype_hooks', $data));

				// exp_extensions
				$hooks_q = $DB->query('SELECT extension_id FROM exp_extensions WHERE class = "'.FF_CLASS.'" AND hook = "'.$hook.'" AND priority = "'.$data['priority'].'"');
				if ( ! $hooks_q->num_rows)
				{
					$ext_data = array('class' => FF_CLASS, 'method' => 'forward_hook:'.$hook.':'.$data['priority'], 'hook' => $hook, 'settings' => '', 'priority' => $data['priority'], 'version' => FF_VERSION, 'enabled' => 'y');
					$DB->query($DB->insert_string('exp_extensions', $ext_data));
				}
			}
		}

		// reset cached hooks array
		$this->_get_ftype_hooks(TRUE);
	}

	function _get_ftype_hooks($reset=FALSE)
	{
		global $DB;

		if ($reset OR ! isset($this->cache['ftype_hooks']))
		{
			$this->cache['ftype_hooks'] = array();

			$hooks_q = $DB->query('SELECT * FROM exp_ff_fieldtype_hooks');
			foreach($hooks_q->result as $hook_r)
			{
				$this->cache['ftype_hooks'][$hook_r['hook']][$hook_r['priority']][$hook_r['class']] = $hook_r['method'];
			}
		}

		return $this->cache['ftype_hooks'];
	}

	/**
	 * Group Fieldtype nputs
	 *
	 * move inputs into ftype namespace
	 *
	 * e.g. name="options"   => name="ftype[ftype_id_1][options]"
	 *      name="options[]" => name="ftype[ftype_id_1][options][]"
	 *
	 * @param  string  $ftype_id  The Fieldtype ID
	 * @param  string  $settings  The Fieldtype settings
	 * @return string  The modified settings
	 * @access private
	 */
	function _group_ftype_inputs($ftype_id, $settings)
	{
		return preg_replace('/(name=[\'"])([^\'"\[\]]+)([^\'"]*)([\'"])/i', '$1ftype['.$ftype_id.'][$2]$3$4', $settings);
	}

	/**
	 * Settings Form
	 *
	 * @param array  $current  Current extension settings (not site-specific)
	 * @see   http://expressionengine.com/docs/development/extensions.html#settings
	 */
	function settings_form()
	{
		global $DB, $DSP, $LANG, $IN, $PREFS, $SD;

		// Breadcrumbs
		$DSP->crumbline = TRUE;
		$DSP->title = $LANG->line('extension_settings');
		$DSP->crumb = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities'))
		            . $DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')))
		            . $DSP->crumb_item(FF_NAME);
		$DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

		// open form
		$DSP->body .= '<h1>'.FF_NAME.' <small>'.FF_VERSION.'</small></h1>'
		            . $DSP->form_open(
		                  array(
		                    'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
		                    'name'   => 'settings_subtext',
		                    'id'     => 'settings_subtext'
		                  ),
		                  array(
		                    'name' => strtolower(FF_CLASS)
		                  ));

		// initialize Fieldframe_SettingsDisplay
		$SD = new Fieldframe_SettingsDisplay();

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
		$DSP->body .= $SD->block('fieldtype_manager', 5);

		// initialize field types
		$ftypes = $this->_get_all_installed_ftypes();

		// add the headers
		$DSP->body .= $SD->heading_row(array(
		                                   $LANG->line('fieldtype'),
		                                   $LANG->line('fieldtype_enabled'),
		                                   $LANG->line('settings'),
		                                   $LANG->line('documentation')
		                                 ));

		foreach($ftypes as $class_name => $ftype)
		{
			$DSP->body .= $SD->row(array(
			                         $SD->label($ftype->info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $ftype->info['version']), $ftype->info['desc']),
			                         $SD->radio_group('ftypes['.$class_name.'][enabled]', ($ftype->_is_enabled ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no')),
			                         (($ftype->_is_enabled AND method_exists($ftype, 'display_site_settings'))
			                            ?  '<a id="ft'.$ftype->_fieldtype_id.'show" href="javascript:void();" style="display:block;" onclick="this.style.display=\'none\'; document.getElementById(\'ft'.$ftype->_fieldtype_id.'hide\').style.display=\'block\'; document.getElementById(\'ft'.$ftype->_fieldtype_id.'settings\').style.display=\'table-row\';"><img src="'.$PREFS->ini('theme_folder_url', 1).'cp_global_images/expand.gif">  '.$LANG->line('show').'</a>'
			                             . '<a id="ft'.$ftype->_fieldtype_id.'hide" href="javascript:void();" style="display:none;" onclick="this.style.display=\'none\'; document.getElementById(\'ft'.$ftype->_fieldtype_id.'show\').style.display=\'block\'; document.getElementById(\'ft'.$ftype->_fieldtype_id.'settings\').style.display=\'none\';"><img src="'.$PREFS->ini('theme_folder_url', 1).'cp_global_images/collapse.gif">  '.$LANG->line('hide').'</a>'
			                            :  '--'),
			                         ($ftype->info['docs_url'] ? '<a href="'.stripslashes($ftype->info['docs_url']).'">'.$LANG->line('documentation').'</a>' : '--')
			                       ));

			if ($ftype->_is_enabled AND method_exists($ftype, 'display_site_settings'))
			{
				$LANG->fetch_language_file($class_name);

				$data = '<div style="margin:-6px 8px 12px 12px;">'
				      . $this->_group_ftype_inputs($ftype->_fieldtype_id, $ftype->display_site_settings())
				      . $DSP->div_c();
				$DSP->body .= $SD->row(array($data), $SD->row_class, array('id' => 'ft'.$ftype->_fieldtype_id.'settings', 'style' => 'display:none;'));
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

		// get the default FF settings
		$this->settings = $this->_get_settings();

		$this->settings['fieldtypes_url'] = $_POST['fieldtypes_url'] ? $this->_add_slash($_POST['fieldtypes_url']) : '';
		$this->settings['fieldtypes_path'] = $_POST['fieldtypes_path'] ? $this->_add_slash($_POST['fieldtypes_path']) : '';
		$this->settings['check_for_updates'] = ($_POST['check_for_updates'] != 'n') ? 'y' : 'n';

		// save all FF settings
		$settings = $this->_get_all_settings();
		$settings[$PREFS->ini('site_id')] = $this->settings;
		$DB->query($DB->update_string('exp_extensions', array('settings' => addslashes(serialize($settings))), 'class = "'.FF_CLASS.'"'));


		// field type settings
		if (isset($_POST['ftypes']))
		{
			foreach($_POST['ftypes'] as $file => $ftype_post)
			{
				// Initialize
				if (($ftype = $this->_init_ftype($file)) !== FALSE)
				{
					$data = array('enabled' => $ftype_post['enabled'] == 'y' ? 'y' : 'n');

					// insert a new row if it's new
					if ($ftype->_is_new)
					{
						$data['site_id'] = $PREFS->ini('site_id');
						$data['class'] = $file;
						$data['version'] = $ftype->info['version'];
						$DB->query($DB->insert_string('exp_ff_fieldtypes', $data));

						// get the fieldtype_id
						$query = $DB->query('SELECT fieldtype_id FROM exp_ff_fieldtypes
						                       WHERE site_id = "'.$PREFS->ini('site_id').'"
						                         AND class = "'.$file.'"');
						$ftype->_fieldtype_id = $query->row['fieldtype_id'];

						// insert hooks
						$this->_insert_ftype_hooks($ftype);

						// call update()
						if (method_exists($ftype, 'update'))
						{
							$ftype->update(FALSE);
						}
					}
					else
					{
						// site settings
						$settings = (isset($_POST['ftype']) AND isset($_POST['ftype'][$ftype->_fieldtype_id]))
						  ?  $_POST['ftype'][$ftype->_fieldtype_id]
						  :  array();

						// let the fieldtype do what it wants with them
						if (method_exists($ftype, 'save_site_settings'))
						{
							$settings = $ftype->save_site_settings($settings);
							if ( ! is_array($settings)) $settings = array();
						}
						$data['settings'] = addslashes(serialize($settings));

						// update the row
						$DB->query($DB->update_string('exp_ff_fieldtypes', $data, 'fieldtype_id = "'.$ftype->_fieldtype_id.'"'));
					}
				}
			}
		}
	}

	/**
	 * Get Last Call
	 *
	 * @param  mixed  $param  Parameter sent by extension hook
	 * @return mixed  Return value of last extension call if any, or $param
	 * @access private
	 */
	function get_last_call($param=FALSE)
	{
		global $EXT;
		return isset($this->_last_call)
		  ?  $this->_last_call
		  :  ($EXT->last_call !== FALSE ? $EXT->last_call : $param);
	}

	/**
	 * Forward hook to fieldtype
	 */
	function forward_hook($hook, $priority, $args=array())
	{
		$ftype_hooks = $this->_get_ftype_hooks();
		if (isset($ftype_hooks[$hook]) AND isset($ftype_hooks[$hook][$priority]))
		{
			$ftypes = $this->_get_ftypes();

			foreach ($ftype_hooks[$hook][$priority] as $class_name => $method)
			{
				if (isset($ftypes[$class_name]) AND method_exists($ftypes[$class_name], $method))
				{
					$this->_last_call = call_user_func_array(array(&$ftypes[$class_name], $method), $args);
				}
			}
		}
		if (isset($this->_last_call))
		{
			$r = $this->_last_call;
			unset($this->_last_call);
		}
		else
		{
			$r = $this->get_last_call();
		}
		return $r;
	}

	function forward_ff_hook($hook, $args=array(), $r=TRUE)
	{
		$this->_last_call = $r;
		$priority = isset($this->hooks[$hook]) AND isset($this->hooks[$hook]['priority'])
		  ?  $this->hooks[$hook]['priority']
		  :  10;
		return $this->forward_hook($hook, $priority, $args);
	}

	/**
	 * Get Line
	 *
	 * @param  string  $line  unlocalized string or the name of a $LANG line
	 * @return string  Localized string
	 */
	function get_line($line)
	{
		global $LANG;
		$loc_line = $LANG->line($line);
		return $loc_line ? $loc_line : $line;
	}

	/**
	 * Sessions Start
	 *
	 * - Reset any session class variable
	 * - Override the whole session check
	 * - Modify default/guest settings
	 *
	 * @param object  $this  The current instantiated Session class with all of its variables and functions,
	 *                       use a reference in your functions to modify.
	 * @see   http://expressionengine.com/developers/extension_hooks/sessions_start/
	 */
	function sessions_start($sess)
	{
		// are we saving a field?
		if (isset($_POST['field_type']))
		{
			$this->publish_admin_edit_field_save();
		}
		$args = func_get_args();
		return $this->forward_ff_hook('sessions_start', $args);
	}

	/**
	 * Publish Admin - Edit Field Form - Field Type Menu
	 *
	 * Allows modifying or adding onto Custom Weblog Field Type Pulldown
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $typemenu  The contents of the type menu
	 * @return string  The modified $typemenu
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_pulldown/
	 */
	function publish_admin_edit_field_type_pulldown($data, $typemenu)
	{
		$r = $this->get_last_call($typemenu);

		global $DSP;

		$ftypes = $this->_get_ftypes();
		foreach($ftypes as $class_name => $ftype)
		{
			$field_type = 'ftype_id_'.$ftype->_fieldtype_id;
			$r .= $DSP->input_select_option($field_type, $ftype->info['name'], ($data['field_type'] == $field_type ? 1 : 0));
		}

		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_type_pulldown', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Javascript
	 *
	 * Allows modifying or adding onto Custom Weblog Field JS
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $js    Currently existing javascript
	 * @return string  The modified $js
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_js/
	 */
	function publish_admin_edit_field_js($data, $js)
	{
		global $LANG, $REGX;

		// Prepare fieldtypes for following Publish Admin hooks
		$field_settings_tmpl = array(
			'cell1' => '',
			'cell2' => '',
			'rows' => array()
		);
		$prev_ftype_id = '';
		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
			$selected = ($ftype_id == $data['field_type']) ? TRUE : FALSE;
			if (method_exists($ftype, 'display_field_settings'))
			{
				// Load the language file
				$LANG->fetch_language_file($class_name);

				$ftype->_field_settings = array_merge($field_settings_tmpl, $ftype->display_field_settings($selected ? $REGX->array_stripslashes(unserialize($data['ff_settings'])) : array()));
			}
			else
			{
				$ftype->_field_settings = $field_settings_tmpl;
			}
			if ($selected) $prev_ftype_id = $ftype_id;
		}

		// Add the JS
		$r = $this->get_last_call($js);
		$r = preg_replace('/(function\s+showhide_element\(\s*id\s*\)\s*{)/is', "
		var prev_ftype_id = '{$prev_ftype_id}';

		$1
			if (prev_ftype_id)
			{
				var c=1, r=1;
				while(cell = document.getElementById(prev_ftype_id+'_cell'+c))
				{
					cell.style.display = 'none';
					c++;
				}
				while(row = document.getElementById(prev_ftype_id+'_row'+r))
				{
					row.style.display = 'none';
					r++;
				}
			}

			if (id.match(/^ftype_id_\d+$/))
			{
				var c=1, r=1;
				while(cell = document.getElementById(id+'_cell'+c))
				{
					//var showDiv = document.getElementById(id+'_cell'+c);
					var divs = cell.parentNode.childNodes;
					for(var i=0; i<divs.length; i++)
					{
						var div = divs[i];
						if ( ! (div.nodeType == 1 && div.id)) continue;
						div.style.display = (div == cell) ? 'block' : 'none';
					}
					c++;
				}
				while(row = document.getElementById(id+'_row'+r))
				{
					row.style.display = 'table-row';
					r++;
				}
				prev_ftype_id = id;
			}\n", $r);

		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_js', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Cell
	 *
	 * @param  array   $data   The data about this field from the database
	 * @param  string  $cell   The contents of the cell
	 * @param  string  $index  The cell index
	 * @return string  The modified $cell
	 * @access private
	 */
	function _publish_admin_edit_field_type_cell($data, $cell, $index)
	{
		$r = $this->get_last_call($cell);
		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
			$selected = ($data['field_type'] == $ftype_id);

			$field_settings = $this->_group_ftype_inputs($ftype_id, $ftype->_field_settings['cell'.$index]);

			$r .= '<div id="'.$ftype_id.'_cell'.$index.'" style="margin-top:5px; display:'.($selected ? 'block' : 'none').';">'
			    . $field_settings
			    . '</div>';
		}

		return $r;
	}

	/**
	 * Publish Admin - Edit Field Form - Cell One
	 *
	 * Allows modifying or adding onto Custom Weblog Field Type - First Table Cell
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $cell  The contents of the cell
	 * @return string  The modified $cell
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_cellone/
	 */
	function publish_admin_edit_field_type_cellone($data, $cell)
	{
		$r = $this->_publish_admin_edit_field_type_cell($data, $cell, '1');
		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_type_cellone', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Cell Two
	 *
	 * Allows modifying or adding onto Custom Weblog Field Type - Second Table Cell
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $cell  The contents of the cell
	 * @return string  The modified $cell
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_celltwo/
	 */
	function publish_admin_edit_field_type_celltwo($data, $cell)
	{
		$r = $this->_publish_admin_edit_field_type_cell($data, $cell, '2');
		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_type_celltwo', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Format
	 *
	 * Allows modifying or adding onto Default Text Formatting Cell
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $y     The current contents of the format cell
	 * @return string  The modified $y
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_format/
	 */
	function publish_admin_edit_field_format($data, $y)
	{
		$y = $this->get_last_call($y);
		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_format', $args, $y);
	}

	/**
	 * Publish Admin - Edit Field Form - Extra Row
	 *
	 * Allows modifying or adding onto the Custom Field settings table
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $r     The current contents of the page
	 * @return string  The modified $r
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_extra_row/
	 */
	function publish_admin_edit_field_extra_row($data, $r)
	{
		global $DSP, $LANG;

		$rows = '';
		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
			$selected = ($data['field_type'] == $ftype_id);

			foreach($ftype->_field_settings['rows'] as $index => $row)
			{
				$rows .= '<tr id="'.$ftype_id.'_row'.($index+1).'"' . ($selected ? '' : ' style="display:none;"') . '>'
				       . $DSP->td('tableCellOne')
				       . $row[0]
				       . $DSP->td_c()
				       . $DSP->td('tableCellOne')
				       . $row[1]
				       . $DSP->td_c()
				       . $DSP->tr_c();
			}
		}
		$rows = $this->_group_ftype_inputs($ftype_id, $rows);

		$r = $this->get_last_call($r);
		$r = preg_replace('/(<tr>\s*<td[^>]*>\s*<div[^>]*>\s*'.$LANG->line('deft_field_formatting').'\s*<\/div>)/is', $rows.'$1', $r);

		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_extra_row', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Save Field
	 *
	 * Made-up hook called by sessions_start
	 * when $_POST['field_type'] is set
	 */
	function publish_admin_edit_field_save()
	{
		global $DB;

		// is this a FF fieldtype?
		if (preg_match('/^ftype_id_(\d+)$/', $_POST['field_type'], $matches) !== FALSE)
		{
			$ftype_id = $matches[1];
			$settings = (isset($_POST['ftype']) AND isset($_POST['ftype'][$_POST['field_type']]))
			  ?  $_POST['ftype'][$_POST['field_type']]
			  :  array();

			// initialize the fieldtype
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes WHERE fieldtype_id = "'.$ftype_id.'"');
			if ($query->row)
			{	
				// let the fieldtype modify the settings
				if (($ftype = $this->_init_ftype($query->row)) !== FALSE)
				{
					if (method_exists($ftype, 'save_field_settings'))
					{
						$settings = $ftype->save_field_settings($settings);
						if ( ! is_array($settings)) $settings = array();
					}
				}
			}

			// save settings as a post var
			$_POST['ff_settings'] = addslashes(serialize($settings));
		}

		// unset extra FF post vars
		foreach($_POST as $key => $value)
		{
			if (substr($key, 0, 5) == 'ftype')
			{
				unset($_POST[$key]);
			}
		}
	}

	/**
	 * Display - Show Full Control Panel - End
	 *
	 * - Rewrite CP's HTML
	 * - Find/Replace stuff, etc.
	 *
	 * @param  string  $end  The content of the admin page to be outputted
	 * @return string  The modified $out
	 * @see    http://expressionengine.com/developers/extension_hooks/show_full_control_panel_end/
	 */
	function show_full_control_panel_end($out)
	{
		$out = $this->get_last_call($out);
		global $IN, $DB, $REGX;

		// if we are displaying the custom field list
		if($IN->GBL('M', 'GET') == 'blog_admin' AND in_array($IN->GBL('P', 'GET'), array('field_editor', 'update_weblog_fields', 'delete_field')))
		{
			// get the FF fieldtypes
			foreach($this->_get_fields() as $field_id => $field)
			{
				// add fieldtype name to this field
				$out = preg_replace("/(C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id={$field_id}.*?<\/td>.*?<td.*?>.*?<\/td>.*?)<\/td>/is",
				                      '$1'.$REGX->form_prep($field['ftype']->info['name']).'</td>', $out);
			}
		}

		$args = func_get_args();
		return $this->forward_ff_hook('show_full_control_panel_end', $args, $out);
	}

	/**
	 * Publish Form - Unique Field
	 *
	 * Allows adding of unique custom fields via extensions
	 *
	 * @param  array   $row  Parameters for the field from the database
	 * @param  array   $field_data  If entry is not new, this will have field's current value
	 * @return string  The field's HTML
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_form_field_unique/
	 */
	function publish_form_field_unique($row, $field_data)
	{
		// return if this isn't a FieldFrame fieldtype
		if (substr($row['field_type'], 0, 9) != 'ftype_id_')
		{
			return $this->get_last_call();
		}

		$field_name = 'field_id_'.$row['field_id'];
		$fields = $this->_get_fields();

		if (array_key_exists($row['field_id'], $fields))
		{
			$field = $fields[$row['field_id']];

			if (method_exists($field['ftype'], 'display_field'))
			{
				$r = $field['ftype']->display_field($field_name, $field_data, $field['settings']);
			}
		}

		// place field data in a basic textfield if the fieldtype
		// wasn't enabled or didn't have a display_field method
		if ( ! isset($r))
		{
			global $DSP;
			$r = $DSP->input_textarea($field_name, $field_data, 1, 'textarea', '100%');
		}

		$r = '<div style="padding:5px 27px 17px 17px;">'.$r.'</div>';
		$args = func_get_args();
		return $this->forward_ff_hook('publish_form_field_unique', $args, $r);
	}

	/**
	 * Publish Form - Submit New Entry
	 *
	 * Add More Stuff to do when you first submit an entry
	 *
	 * @see http://expressionengine.com/developers/extension_hooks/submit_new_entry_start/
	 */
	function submit_new_entry_start()
	{
		foreach($this->_get_fields() as $field_id => $field)
		{
			$field_name = 'field_id_'.$field_id;

			if (isset($_POST[$field_name]))
			{
				if (method_exists($field['ftype'], 'save_field'))
				{
					$field['ftype']->save_field($field_name);
				}

				if (isset($_POST[$field_name]) AND is_array($_POST[$field_name]))
				{
					$_POST[$field_name] = addslashes(serialize($_POST[$field_name]));
				}

				// unset extra FF post vars
				$prefix = $field_name.'_';
				$length = strlen($prefix);
				foreach($_POST as $key => $value)
				{
					if (substr($key, 0, $length) == $prefix)
					{
						unset($_POST[$key]);
					}
				}
			}
		}

		return $this->forward_ff_hook('submit_new_entry_start');
	}

	/**
	 * Weblog - Entry Tag Data
	 *
	 * Modify the tagdata for the weblog entries before anything else is parsed
	 *
	 * @param  string   $tagdata   The Weblog Entries tag data
	 * @param  array    $row       Array of data for the current entry
	 * @param  object   $weblog    The current Weblog object including all data relating to categories and custom fields
	 * @return string              Modified $tagdata
	 * @see    http://expressionengine.com/developers/extension_hooks/weblog_entries_tagdata/
	 */
	function weblog_entries_tagdata($tagdata, $row, &$weblog)
	{
		$this->tagdata = $this->get_last_call($tagdata);
		$this->row = $row;
		$this->weblog = &$weblog;

		foreach($this->_get_fields() as $field_id => $field)
		{
			if (method_exists($field['ftype'], 'display_tag'))
			{
				// find all FF field tags
				if (preg_match_all('/'.LD.$field['name'].'(\s+.*)?'.RD.'/sU', $this->tagdata, $matches, PREG_OFFSET_CAPTURE))
				{
					$this->field_id = $field_id;
					$this->field_name = $field['name'];

					for ($i = count($matches[0])-1; $i >= 0; $i--)
					{
						$tag_pos = $matches[0][$i][1];
						$tag_len = strlen($matches[0][$i][0]);
						$tagdata_pos = $tag_pos + $tag_len;
						$endtag = LD.SLASH.$field['name'].RD;
						$endtag_len = strlen($endtag);
						$endtag_pos = strpos($this->tagdata, $endtag, $tagdata_pos);
        
						// get the params
						$params = array();
						if (isset($matches[1][$i][0]) AND preg_match_all('/\s+(\w+)\s*=\s*[\'\"]([^\'\"]*)[\'\"]/sU', $matches[1][$i][0], $param_matches))
						{
							for ($j = 0; $j < count($param_matches[0]); $j++)
							{
								$params[$param_matches[1][$j]] = $param_matches[2][$j];
							}
						}
        
						// is this a tag pair?
						$field_tagdata = ($endtag_pos !== FALSE)
						  ?  substr($this->tagdata, $tagdata_pos, $endtag_pos - $tagdata_pos)
						  :  '';
        
						// let the fieldtype do what it wants with it
						$this->tagdata = substr($this->tagdata, 0, $tag_pos)
						               . $field['ftype']->display_tag($params, $field_tagdata, $row['field_id_'.$field_id], $field['settings'])
						               . substr($this->tagdata, ($endtag_pos !== FALSE ? $endtag_pos+$endtag_len : $tagdata_pos));
					}
				}
			}
		}

		// unset temporary field helper vars
		$tagdata = $this->tagdata;
		unset($this->tagdata);
		unset($this->row);
		unset($this->weblog);
		if (isset($this->field_id)) unset($this->field_id);
		if (isset($this->field_name)) unset($this->field_name);

		$args = func_get_args();
		return $this->forward_ff_hook('weblog_entries_tagdata', $args, $tagdata);
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
		$sources = $this->get_last_call($sources);
		if ($this->settings['check_for_updates'] == 'y')
		{
			// add FieldFrame source
			$source = 'http://brandon-kelly.com/downloads/versions.xml';
			if ( ! in_array($source, $sources))
			{
				$sources[] = $source;
			}

			// add ftype sources
			foreach($this->_get_ftypes() as $class_name => $ftype)
			{
				$source = $ftype->info['versions_xml_url'];
				if ($source AND ! in_array($source, $sources))
				{
					$sources[] = $source;
				}
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
		$addons = $this->get_last_call($addons);
		if ($this->settings['check_for_updates'] == 'y')
		{
			// add FieldFrame
			$addons[FF_CLASS] = FF_VERSION;

			// add ftypes
			foreach($this->_get_ftypes() as $class_name => $ftype)
			{
				$addons[$class_name] = $ftype->info['version'];
			}
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
class Fieldframe_SettingsDisplay {

	/**
	 * Fieldframe_SettingsDisplay Constructor
	 */
	function Fieldframe_SettingsDisplay()
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
	function block($title_line=FALSE, $num_cols=2)
	{
		global $DSP;

		$this->row_count = 0;
		$this->num_cols = $num_cols;

		$r = $DSP->table_open(array(
		                        'class'  => 'tableBorder',
		                        'border' => '0',
		                        'style' => 'margin-top:18px; width:100%;'.($title_line ? '' : ' border-top:1px solid #CACFD4;')
		                      ));
		if ($title_line)
		{
			$r .= $this->row(array($this->get_line($title_line)), 'tableHeading');
		}

		return $r;
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
	 * @param  array   $attr       HTML attributes to add onto the <tr>
	 * @return string  The settings row
	 */
	function row($col_data, $row_class=NULL, $attr=array())
	{
		global $DSP;

		// get the alternating row class
		if ($row_class === NULL)
		{
			$this->row_count++;
			$this->row_class = ($this->row_count % 2)
			 ? 'tableCellOne'
			 : 'tableCellTwo';
		}
		else
		{
			$this->row_class = $row_class;
		}

		$r = '<tr';
		foreach($attr as $key => $value) $r .= ' '.$key.'="'.$value.'"';
		$r .= '>';
		$num_cols = count($col_data);
		foreach($col_data as $i => $col)
		{
			$width = ($i == 0)
			  ?  '50%'
			  :  ($i < $num_cols-1 ? floor(50/($num_cols-1)).'%' : '');
			$colspan = ($i == $num_cols-1) ? $this->num_cols - $i : NULL;
			$r .= $DSP->td($this->row_class, $width, $colspan)
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
		                 . '<p>'.$this->get_line($info_line).'</p>'
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
		$r = $DSP->qdiv('defaultBold', $this->get_line($label_line));
		if ($subtext_line) $r .= $DSP->qdiv('subtext', $this->get_line($subtext_line));
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
			$r .= $DSP->input_select_option($option_value, $this->get_line($option_line), $selected ? 1 : 0);
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
			    . ' '.$this->get_line($option_name)
			    . '</label>';
		}
		return $r;
	}

	/**
	 * Get Line
	 *
	 * @param  string  $line  unlocalized string or the name of a $LANG line
	 * @return string  Localized string
	 */
	function get_line($line)
	{
		global $FF;
		return $FF->get_line($line);
	}

}

/**
 * Fieldframe Fieldtype Base Class
 *
 * Provides FieldFrame fieldtypes with a couple handy methods
 *
 * @package  FieldFrame
 * @author   Brandon Kelly <me@brandon-kelly.com>
 */
class Fieldframe_Fieldtype {

	var $_fieldframe = TRUE;

	function get_last_call($param=FALSE)
	{
		global $FF;
		return $FF->get_last_call($param);
	}

}

/* End of file ext.fieldframe.php */
/* Location: ./system/extensions/ext.fieldframe.php */