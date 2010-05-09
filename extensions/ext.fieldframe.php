<?php

if ( ! defined('EXT')) exit('Invalid file request');

// define FF constants
// (used by Fieldframe and Fieldframe_Main)
if ( ! defined('FF_CLASS'))
{
	define('FF_CLASS',   'Fieldframe');
	define('FF_NAME',    'FieldFrame');
	define('FF_VERSION', '1.4.2');
}


/**
 * FieldFrame Class
 *
 * This extension provides a framework for ExpressionEngine fieldtype development.
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <brandon@pixelandtonic.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Fieldframe {

	var $name           = FF_NAME;
	var $version        = FF_VERSION;
	var $description    = 'Fieldtype Framework';
	var $settings_exist = 'y';
	var $docs_url       = 'http://pixelandtonic.com/fieldframe/docs';

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
		'show_full_control_panel_start',
		'show_full_control_panel_end',

		// Entry Form
		'publish_form_field_unique',
		'submit_new_entry_start',
		'submit_new_entry_end',
		'publish_form_start',

		// SAEF
		'weblog_standalone_form_start',
		'weblog_standalone_form_end',

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
	function __construct($settings=array())
	{
		$this->_init_main($settings);
	}

	/**
	 * Activate Extension
	 */
	function activate_extension()
	{
		global $DB;

		// require PHP 5
		if (phpversion() < 5) return;

		// Get settings
		$query = $DB->query('SELECT settings FROM exp_extensions WHERE class = "'.FF_CLASS.'" AND settings != "" LIMIT 1');
		$settings = $query->num_rows ? $this->_unserialize($query->row['settings']) : array();
		$this->_init_main($settings, TRUE);

		global $FF;
		$FF->activate_extension($settings);
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
			return FALSE;
		}

		if ($current < '1.1.3')
		{
			// no longer saving settings on a per-site basis

			$sql = array();

			// no more per-site FF settings
			$query = $DB->query('SELECT settings FROM exp_extensions WHERE class = "'.FF_CLASS.'" AND settings != "" LIMIT 1');
			if ($query->row)
			{
				$settings = array_shift(Fieldframe_Main::_unserialize($query->row['settings']));
				$sql[] = $DB->update_string('exp_extensions', array('settings' => Fieldframe_Main::_serialize($settings)), 'class = "'.FF_CLASS.'"');
			}

			// collect conversion info
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes ORDER BY enabled DESC, site_id ASC');
			$firsts = array();
			$conversions = array();
			foreach ($query->result as $ftype)
			{
				if ( ! isset($firsts[$ftype['class']]))
				{
					$firsts[$ftype['class']] = $ftype['fieldtype_id'];
				}
				else
				{
					$conversions[$ftype['fieldtype_id']] = $firsts[$ftype['class']];
				}
			}

			if ($conversions)
			{
				// remove duplicate ftype rows in exp_ff_fieldtypes
				$sql[] = 'DELETE FROM exp_ff_fieldtypes WHERE fieldtype_id IN ('.implode(',', array_keys($conversions)).')';

				// update field_type's in exp_weblog_fields
				foreach($conversions as $old_id => $new_id)
				{
					$sql[] = $DB->update_string('exp_weblog_fields', array('field_type' => 'ftype_id_'.$new_id), 'field_type = "ftype_id_'.$old_id.'"');
				}
			}

			// remove site_id column from exp_ff_fieldtypes
			$sql[] = 'ALTER TABLE exp_ff_fieldtypes DROP COLUMN site_id';

			// apply changes
			foreach($sql as $query)
			{
				$DB->query($query);
			}
		}

		if ($current < '1.1.0')
		{
			// hooks have changed, so go through
			// the whole activate_extension() process
			$this->activate_extension();
		}
		else
		{
			// just update the version #s
			$DB->query('UPDATE exp_extensions
			              SET version = "'.FF_VERSION.'"
			              WHERE class = "'.FF_CLASS.'"');
		}
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
		$this->_init_main(array(), TRUE);

		global $FF;
		$FF->save_settings();
	}

	/**
	 * Initialize Main class
	 *
	 * @param  array  $settings
	 * @access private
	 */
	function _init_main($settings, $force=FALSE)
	{
		global $SESS, $FF;

		if ( ! isset($FF) OR $force)
		{
			$FF = new Fieldframe_Main($settings, $this->hooks);
		}
	}

	/**
	 * __call Magic Method
	 *
	 * Routes calls to missing methods to $FF
	 *
	 * @param string  $method  Name of the missing method
	 * @param array   $args    Arguments sent to the missing method
	 */
	function __call($method, $args)
	{
		global $FF, $EXT;

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

	var $errors = array();
	var $postponed_saves = array();
	var $snippets = array('head' => array(), 'body' => array());

	var $saef = FALSE;

	/**
	 * FieldFrame_Main Class Initialization
	 *
	 * @param array  $settings
	 */
	function __construct($settings, $hooks)
	{
		global $SESS, $DB;

		$this->hooks = $hooks;

		// merge settings with defaults
		$default_settings = array(
			'fieldtypes_url' => '',
			'fieldtypes_path' => '',
			'check_for_updates' => 'y'
		);
		$this->settings = array_merge($default_settings, $settings);

		// set the FT_PATH and FT_URL constants
		$this->_define_constants();
	}

	/**
	 * Define Constants
	 *
	 * @access private
	 */
	function _define_constants()
	{
		global $PREFS;

		if ( ! defined('FT_PATH') AND ($ft_path = isset($PREFS->core_ini['ft_path']) ? $PREFS->core_ini['ft_path'] : $this->settings['fieldtypes_path']))
		{
			define('FT_PATH', $ft_path);
		}

		if ( ! defined('FT_URL') AND ($ft_path = isset($PREFS->core_ini['ft_url']) ? $PREFS->core_ini['ft_url'] : $this->settings['fieldtypes_url']))
		{
			define('FT_URL', $ft_path);
			$this->snippets['body'][] = '<script type="text/javascript">;FT_URL = "'.FT_URL.'";</script>';
		}
	}

	/**
	 * Array Ascii to Entities
	 *
	 * @access private
	 */
	function _array_ascii_to_entities($vals)
	{
		if (is_array($vals))
		{
			foreach ($vals as &$val)
			{
				$val = $this->_array_ascii_to_entities($val);
			}
		}
		else
		{
			global $REGX;
			$vals = $REGX->ascii_to_entities($vals);
		}

		return $vals;
	}

	/**
	 * Array Ascii to Entities
	 *
	 * @access private
	 */
	function _array_entities_to_ascii($vals)
	{
		if (is_array($vals))
		{
			foreach ($vals as &$val)
			{
				$val = $this->_array_entities_to_ascii($val);
			}
		}
		else
		{
			global $REGX;
			$vals = $REGX->entities_to_ascii($vals);
		}

		return $vals;
	}

	/**
	 * Serialize
	 *
	 * @access private
	 */
	function _serialize($vals)
	{
		global $PREFS;

		if ($PREFS->ini('auto_convert_high_ascii') == 'y')
		{
			$vals = $this->_array_ascii_to_entities($vals);
		}

     	return addslashes(serialize($vals));
	}

	/**
	 * Unserialize
	 *
	 * @access private
	 */
	function _unserialize($vals, $convert=TRUE)
	{
		global $REGX, $PREFS;

		if ($vals && (preg_match('/^(i|s|a|o|d):(.*);/si', $vals) !== FALSE) && ($tmp_vals = @unserialize($vals)) !== FALSE)
		{
			$vals = $REGX->array_stripslashes($tmp_vals);

			if ($convert AND $PREFS->ini('auto_convert_high_ascii') == 'y')
			{
				$vals = $this->_array_entities_to_ascii($vals);
			}
		}

     	return $vals;
	}

	/**
	 * Get Fieldtypes
	 *
	 * @return array  All enabled FF fieldtypes, indexed by class name
	 * @access private
	 */
	function _get_ftypes()
	{
		global $DB;

		if ( ! isset($this->ftypes))
		{
			$this->ftypes = array();

			// get enabled fields from the DB
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes WHERE enabled = "y"');

			if ($query->num_rows)
			{
				foreach($query->result as $row)
				{
					if (($ftype = $this->_init_ftype($row)) !== FALSE)
					{
						$this->ftypes[] = $ftype;
					}
				}

				$this->_sort_ftypes($this->ftypes);
			}
		}

		return $this->ftypes;
	}

	function _get_ftype($class_name)
	{
		$ftypes = $this->_get_ftypes();
		return isset($ftypes[$class_name])
		  ?  $ftypes[$class_name]
		  :  FALSE;
	}

	/**
	 * Get All Installed Fieldtypes
	 *
	 * @return array  All installed FF fieldtypes, indexed by class name
	 * @access private
	 */
	function _get_all_installed_ftypes()
	{
		$ftypes = array();

		if (defined('FT_PATH'))
		{
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
						if (($ftype = $this->_init_ftype($file, FALSE)) !== FALSE)
						{
							$ftypes[] = $ftype;
						}
					}
				}
				closedir($fp);

				$this->_sort_ftypes($ftypes);
			}
			else
			{
				$this->errors[] = 'bad_ft_path';
			}
		}

		return $ftypes;
	}

	/**
	 * Sort Fieldtypes
	 *
	 * @param  array  $ftypes  the array of fieldtypes
	 * @access private
	 */
	function _sort_ftypes(&$ftypes)
	{
		$ftypes_by_name = array();
		while($ftype = array_shift($ftypes))
		{
			$ftypes_by_name[$ftype->info['name']] = $ftype;
		}
		ksort($ftypes_by_name);
		foreach($ftypes_by_name as $ftype)
		{
			$ftypes[$ftype->_class_name] = $ftype;
		}
	}

	/**
	 * Get Fieldtypes Indexed By Field ID
	 *
	 * @return array  All enabled FF fieldtypes, indexed by the weblog field ID they're used in.
	 *                Strong possibility that there will be duplicate fieldtypes in here,
	 *                but it's not a big deal because they're just object references
	 * @access private
	 */
	function _get_fields()
	{
		global $DB, $REGX;

		if ( ! isset($this->ftypes_by_field_id))
		{
			$this->ftypes_by_field_id = array();

			// get the fieldtypes
			if ($ftypes = $this->_get_ftypes())
			{
				// index them by ID rather than class
				$ftypes_by_id = array();
				foreach($ftypes as $class_name => $ftype)
				{
					$ftypes_by_id[$ftype->_fieldtype_id] = $ftype;
				}

				// get the field info
				$query = $DB->query("SELECT field_id, site_id, field_name, field_type, ff_settings FROM exp_weblog_fields
				                       WHERE field_type IN ('ftype_id_".implode("', 'ftype_id_", array_keys($ftypes_by_id))."')");
				if ($query->num_rows)
				{
					// sort the current site's fields on top
					function sort_fields($a, $b)
					{
						global $PREFS;
						if ($a['site_id'] == $b['site_id']) return 0;
						if ($a['site_id'] == $PREFS->ini('site_id')) return 1;
						if ($b['site_id'] == $PREFS->ini('site_id')) return -1;
						return 0;
					}
					usort($query->result, 'sort_fields');

					foreach($query->result as $row)
					{
						$ftype_id = substr($row['field_type'], 9);
						$this->ftypes_by_field_id[$row['field_id']] = array(
							'name' => $row['field_name'],
							'ftype' => $ftypes_by_id[$ftype_id],
							'settings' => array_merge(
							                           (isset($ftypes_by_id[$ftype_id]->default_field_settings) ? $ftypes_by_id[$ftype_id]->default_field_settings : array()),
							                           ($row['ff_settings'] ? $this->_unserialize($row['ff_settings']) : array())
							                          )
						);
					}
				}
			}
		}

		return $this->ftypes_by_field_id;
	}

	/**
	 * Initialize Fieldtype
	 *
	 * @param  mixed   $ftype  fieldtype's class name or its row in exp_ff_fieldtypes
	 * @return object  Initialized fieldtype object
	 * @access private
	 */
	function _init_ftype($ftype, $req_strict=TRUE)
	{
		global $DB, $REGX, $LANG;

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

		// is this a FieldFrame fieldtype?
		if ( ! isset($OBJ->_fieldframe)) return FALSE;

		$OBJ->_class_name = $file;
		$OBJ->_is_new     = FALSE;
		$OBJ->_is_enabled = FALSE;
		$OBJ->_is_updated = FALSE;

		// settings
		$OBJ->site_settings = isset($OBJ->default_site_settings)
		  ?  $OBJ->default_site_settings
		  :  array();

		// info
		if ( ! isset($OBJ->info)) $OBJ->info = array();

		// requirements
		if ( ! isset($OBJ->_requires)) $OBJ->_requires = array();
		if (isset($OBJ->requires))
		{
			// PHP
			if (isset($OBJ->requires['php']) AND phpversion() < $OBJ->requires['php'])
			{
				if ($req_strict) return FALSE;
				else $OBJ->_requires['PHP'] = $OBJ->requires['php'];
			}

			// ExpressionEngine
			if (isset($OBJ->requires['ee']) AND APP_VER < $OBJ->requires['ee'])
			{
				if ($req_strict) return FALSE;
				else $OBJ->_requires['ExpressionEngine'] = $OBJ->requires['ee'];
			}

			// ExpressionEngine Build
			if (isset($OBJ->requires['ee_build']) AND APP_BUILD < $OBJ->requires['ee_build'])
			{
				if ($req_strict) return FALSE;
				else
				{
					$req = substr(strtolower($LANG->line('build')), 0, -1).' '.$OBJ->requires['ee_build'];
					if (isset($OBJ->_requires['ExpressionEngine'])) $OBJ->_requires['ExpressionEngine'] .= ' '.$req;
					else $OBJ->_requires['ExpressionEngine'] = $req;
				}
			}

			// FieldFrame
			if (isset($OBJ->requires['ff']) AND FF_VERSION < $OBJ->requires['ff'])
			{
				if ($req_strict) return FALSE;
				else $OBJ->_requires[FF_NAME] = $OBJ->requires['ff'];
			}

			// CP jQuery
			if (isset($OBJ->requires['cp_jquery']))
			{
				if ( ! isset($OBJ->requires['ext']))
				{
					$OBJ->requires['ext'] = array();
				}
				$OBJ->requires['ext'][] = array('class' => 'Cp_jquery', 'name' => 'CP jQuery', 'url' => 'http://www.ngenworks.com/software/ee/cp_jquery/', 'version' => $OBJ->requires['cp_jquery']);
			}

			// Extensions
			if (isset($OBJ->requires['ext']))
			{
				if ( ! isset($this->req_ext_versions))
				{
					$this->req_ext_versions = array();
				}
				foreach($OBJ->requires['ext'] as $ext)
				{
					if ( ! isset($this->req_ext_versions[$ext['class']]))
					{
						$ext_query = $DB->query('SELECT version FROM exp_extensions
						                           WHERE class="'.$ext['class'].'"'
						                           . (isset($ext['version']) ? ' AND version >= "'.$ext['version'].'"' : '')
						                           . ' AND enabled = "y"
						                           ORDER BY version DESC
						                           LIMIT 1');
						$this->req_ext_versions[$ext['class']] = $ext_query->row
						  ?  $ext_query->row['version']
						  :  '';
					}
					if ($this->req_ext_versions[$ext['class']] < $ext['version'])
					{
						if ($req_strict) return FALSE;
						else
						{
							$name = isset($ext['name']) ? $ext['name'] : ucfirst($ext['class']);
							if ($ext['url']) $name = '<a href="'.$ext['url'].'">'.$name.'</a>';
							$OBJ->_requires[$name] = $ext['version'];
						}
					}
				}
			}
		}

		if ( ! isset($OBJ->info['name'])) $OBJ->info['name'] = ucwords(str_replace('_', ' ', $class_name));
		if ( ! isset($OBJ->info['version'])) $OBJ->info['version'] = '';
		if ( ! isset($OBJ->info['desc'])) $OBJ->info['desc'] = '';
		if ( ! isset($OBJ->info['docs_url'])) $OBJ->info['docs_url'] = '';
		if ( ! isset($OBJ->info['author'])) $OBJ->info['author'] = '';
		if ( ! isset($OBJ->info['author_url'])) $OBJ->info['author_url'] = '';
		if ( ! isset($OBJ->info['versions_xml_url'])) $OBJ->info['versions_xml_url'] = '';
		if ( ! isset($OBJ->info['no_lang'])) $OBJ->info['no_lang'] = FALSE;

		if ( ! isset($OBJ->hooks)) $OBJ->hooks = array();
		if ( ! isset($OBJ->postpone_saves)) $OBJ->postpone_saves = FALSE;

		// do we already know about this fieldtype?
		if (is_string($ftype))
		{
			$query = $DB->query('SELECT * FROM exp_ff_fieldtypes WHERE class = "'.$file.'"');
			$ftype = $query->row;
		}
		if ($ftype)
		{
			$OBJ->_fieldtype_id = $ftype['fieldtype_id'];
			if ($ftype['enabled'] == 'y') $OBJ->_is_enabled = TRUE;
			if ($ftype['settings']) $OBJ->site_settings = array_merge($OBJ->site_settings, $this->_unserialize($ftype['settings']));

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

	/**
	 * Insert Fieldtype Hooks
	 *
	 * @access private
	 */
	function _insert_ftype_hooks($ftype)
	{
		global $DB, $FF;

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

		if ($reset OR ! isset($this->ftype_hooks))
		{
			$this->ftype_hooks = array();

			$hooks_q = $DB->query('SELECT * FROM exp_ff_fieldtype_hooks');
			foreach($hooks_q->result as $hook_r)
			{
				$this->ftype_hooks[$hook_r['hook']][$hook_r['priority']][$hook_r['class']] = $hook_r['method'];
			}
		}

		return $this->ftype_hooks;
	}

	/**
	 * Group Inputs
	 *
	 * @param  string  $name_prefix  The Fieldtype ID
	 * @param  string  $settings     The Fieldtype settings
	 * @return string  The modified settings
	 * @access private
	 */
	function _group_inputs($name_prefix, $settings)
	{
		return preg_replace('/(name=([\'\"]))([^\'"\[\]]+)([^\'"]*)(\2)/i', '$1'.$name_prefix.'[$3]$4$5', $settings);
	}

	/**
	 * Group Fieldtype Inputs
	 *
	 * @param  string  $ftype_id  The Fieldtype ID
	 * @param  string  $settings  The Fieldtype settings
	 * @return string  The modified settings
	 * @access private
	 */
	function _group_ftype_inputs($ftype_id, $settings)
	{
		return $this->_group_inputs('ftype['.$ftype_id.']', $settings);
	}

	/**
	 * Activate Extension
	 */
	function activate_extension($settings)
	{
		global $DB;

		// Delete old hooks
		$DB->query('DELETE FROM exp_extensions
		              WHERE class = "'.FF_CLASS.'"');

		// Add new extensions
		$hook_tmpl = array(
			'class'    => FF_CLASS,
			'settings' => $this->_serialize($settings),
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
		$query = $DB->query('SHOW COLUMNS FROM `'.$DB->prefix.'weblog_fields` LIKE "ff_settings"');
		if ( ! $query->num_rows)
		{
			$DB->query('ALTER TABLE `'.$DB->prefix.'weblog_fields` ADD COLUMN `ff_settings` text NOT NULL');
		}

		// reset all ftype hooks
		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$this->_insert_ftype_hooks($ftype);
		}
	}

	/**
	 * Settings Form
	 *
	 * @param array  $current  Current extension settings (not site-specific)
	 * @see   http://expressionengine.com/docs/development/extensions.html#settings
	 */
	function settings_form()
	{
		global $DB, $DSP, $LANG, $IN, $SD, $PREFS;

		// Breadcrumbs
		$DSP->crumbline = TRUE;
		$DSP->title = $LANG->line('extension_settings');
		$DSP->crumb = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities'))
		            . $DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')))
		            . $DSP->crumb_item(FF_NAME);
		$DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

		// Donations button
	    $DSP->body .= '<div class="donations">'
	                . '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=2181794" target="_blank">'
	                . $LANG->line('donate')
	                . '</a>'
	                . '</div>';

		// open form
		$DSP->body .= '<h1>'.FF_NAME.' <small>'.FF_VERSION.'</small></h1>'
		            . $DSP->form_open(
		                  array(
		                    'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
		                    'name'   => 'settings_subtext',
		                    'id'     => 'ffsettings'
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
		            . $SD->info_row('fieldtypes_folder_info')
		            . $SD->row(array(
		                         $SD->label('fieldtypes_path_label', 'fieldtypes_path_subtext'),
		                         $SD->text('fieldtypes_path',
		                                       (isset($PREFS->core_ini['ft_path']) ? $PREFS->core_ini['ft_path'] : $this->settings['fieldtypes_path']),
		                                       array('extras' => (isset($PREFS->core_ini['ft_path']) ? ' disabled="disabled" ' : '')))
		                       ))
		            . $SD->row(array(
		                         $SD->label('fieldtypes_url_label', 'fieldtypes_url_subtext'),
		                         $SD->text('fieldtypes_url',
		                                       (isset($PREFS->core_ini['ft_url']) ? $PREFS->core_ini['ft_url'] : $this->settings['fieldtypes_url']),
		                                       array('extras' => (isset($PREFS->core_ini['ft_url']) ? ' disabled="disabled" ' : '')))
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

		// Fieldtypes Manager
		$this->fieldtypes_manager(FALSE, $SD);

		// Close form
		$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit())
		            . $DSP->form_c();


		ob_start();
?>
<style type="text/css" charset="utf-8">
  .donations { float:right; }
  .donations a { display:block; margin:-2px 10px 0 0; padding:5px 0 5px 67px; width:193px; height:15px; font-size:12px; line-height:15px; background:url(http://brandon-kelly.com/images/shared/donations.png) no-repeat 0 0; color:#000; font-weight:bold; }
  h1 { padding:7px 0; }
</style>
<?php
		$this->snippets['head'][] = ob_get_contents();
		ob_end_clean();
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
		global $DB;

		// save new FF site settings
		$this->settings = array(
			'fieldtypes_url'    => ((isset($_POST['fieldtypes_url']) AND $_POST['fieldtypes_url']) ? $this->_add_slash($_POST['fieldtypes_url']) : ''),
			'fieldtypes_path'   => ((isset($_POST['fieldtypes_path']) AND $_POST['fieldtypes_path']) ? $this->_add_slash($_POST['fieldtypes_path']) : ''),
			'check_for_updates' => ($_POST['check_for_updates'] != 'n' ? 'y' : 'n')
		);
		$DB->query($DB->update_string('exp_extensions', array('settings' => $this->_serialize($this->settings)), 'class = "'.FF_CLASS.'"'));

		// set the FT_PATH and FT_URL constants
		$this->_define_constants();

		// save Fieldtypes Manager data
		$this->save_fieldtypes_manager();
	}

	/**
	 * Fieldtypes Manager
	 */
	function fieldtypes_manager($standalone=TRUE, $SD=NULL)
	{
		global $DB, $DSP, $LANG, $IN, $PREFS, $SD;

		if ( ! $SD)
		{
			// initialize Fieldframe_SettingsDisplay
			$SD = new Fieldframe_SettingsDisplay();
		}

		if ($standalone)
		{
			// save submitted settings
			if ($this->save_fieldtypes_manager())
			{
				$DSP->body .= $DSP->qdiv('box', $DSP->qdiv('success', $LANG->line('settings_update')));
			}

			// load language file
			$LANG->fetch_language_file('fieldframe');

			// Breadcrumbs
			$DSP->crumbline = TRUE;
			$DSP->title = $LANG->line('fieldtypes_manager');
			$DSP->crumb = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities'))
			            . $DSP->crumb_item($LANG->line('fieldtypes_manager'));

			// open form
			$DSP->body .= $DSP->form_open(
			                  array(
			                    'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=fieldtypes_manager',
			                    'name'   => 'settings_subtext',
			                    'id'     => 'ffsettings'
			                  ),
			                  array(
			                    'name' => strtolower(FF_CLASS)
			                  ));
		}

		// fieldtype settings
		$DSP->body .= $SD->block('fieldtypes_manager', 5);

		// initialize fieldtypes
		if ($ftypes = $this->_get_all_installed_ftypes())
		{
			// add the headers
			$DSP->body .= $SD->heading_row(array(
			                                   $LANG->line('fieldtype'),
			                                   $LANG->line('fieldtype_enabled'),
			                                   $LANG->line('settings'),
			                                   $LANG->line('documentation')
			                                 ));

			$row_ids = array();

			foreach($ftypes as $class_name => $ftype)
			{
				$row_id = 'ft_'.$ftype->_class_name;
				$row_ids[] = '"'.$row_id.'"';

				if (method_exists($ftype, 'display_site_settings'))
				{
					if ( ! $ftype->info['no_lang']) $LANG->fetch_language_file($class_name);
					$site_settings = $ftype->display_site_settings();
				}
				else
				{
					$site_settings = FALSE;
				}

				$DSP->body .= $SD->row(array(
				                         $SD->label($ftype->info['name'].NBS.$DSP->qspan('xhtmlWrapperLight defaultSmall', $ftype->info['version']), $ftype->info['desc']),
				                         ($ftype->_requires
				                            ?  '--'
				                            :  $SD->radio_group('ftypes['.$class_name.'][enabled]', ($ftype->_is_enabled ? 'y' : 'n'), array('y'=>'yes', 'n'=>'no'))),
				                         ($site_settings
				                            ?  '<a class="toggle show" id="'.$row_id.'_show" href="#ft_'.$class_name.'_settings"><img src="'.$PREFS->ini('theme_folder_url', 1).'cp_global_images/expand.gif" border="0">  '.$LANG->line('show').'</a>'
				                               . '<a class="toggle hide" id="'.$row_id.'_hide"><img src="'.$PREFS->ini('theme_folder_url', 1).'cp_global_images/collapse.gif" border="0">  '.$LANG->line('hide').'</a>'
				                            :  '--'),
				                         ($ftype->info['docs_url'] ? '<a href="'.stripslashes($ftype->info['docs_url']).'">'.$LANG->line('documentation').'</a>' : '--')
				                       ), NULL, array('id' => $row_id));

				if ($ftype->_requires)
				{
					$data = '<p>'.$ftype->info['name'].' '.$LANG->line('requires').':</p>'
					      . '<ul>';
					foreach($ftype->_requires as $dependency => $version)
					{
						$data .= '<li class="default">'.$dependency.' '.$version.' '.$LANG->line('or_later').'</li>';
					}
					$data .= '</ul>';
					$DSP->body .= $SD->row(array('', $data), $SD->row_class);
				}
				else if ($site_settings)
				{
					$data = '<div class="ftsettings">'
					      .   $this->_group_ftype_inputs($ftype->_class_name, $site_settings)
					      . $DSP->div_c();
					$DSP->body .= $SD->row(array($data), '', array('id' => $row_id.'_settings', 'style' => 'display:none;'));
				}
			}

			ob_start();
?>
<script type="text/javascript" charset="utf-8">
	;var urlParts = document.location.href.split('#'),
		anchor = urlParts[1];
	function ffEnable(ft) {
		ft.show.className = "toggle show";
		ft.show.onclick = function() {
			ft.show.style.display = "none";
			ft.hide.style.display = "block";
			ft.settings.style.display = "table-row";
		};
		ft.hide.onclick = function() {
			ft.show.style.display = "block";
			ft.hide.style.display = "none";
			ft.settings.style.display = "none";
		};
	}
	function ffDisable(ft) {
		ft.show.className = "toggle show disabled";
		ft.show.onclick = null;
		ft.show.style.display = "block";
		ft.hide.style.display = "none";
		ft.settings.style.display = "none";
	}
	function ffInitRow(rowId) {
		var ft = {
			tr: document.getElementById(rowId),
			show: document.getElementById(rowId+"_show"),
			hide: document.getElementById(rowId+"_hide"),
			settings: document.getElementById(rowId+"_settings")
		};
		if (ft.settings) {
			ft.toggles = ft.tr.getElementsByTagName("input");
			ft.toggles[0].onchange = function() { ffEnable(ft); };
			ft.toggles[1].onchange = function() { ffDisable(ft); };
			if (ft.toggles[0].checked) ffEnable(ft);
			else ffDisable(ft);
			if (anchor == rowId+'_settings') {
				ft.show.onclick();
			}
		}
	}
	var ffRowIds = [<?php echo implode(',', $row_ids) ?>];
	for (var i = 0; i < ffRowIds.length; i++) {
		ffInitRow(ffRowIds[i]);
	}
</script>
<?php
			$this->snippets['body'][] = ob_get_contents();
			ob_end_clean();
		}
		else if ( ! defined('FT_PATH'))
		{
			$DSP->body .= $SD->info_row('no_fieldtypes_path');
		}
		else if (in_array('bad_ft_path', $this->errors))
		{
			$DSP->body .= $SD->info_row('bad_fieldtypes_path');
		}
		else
		{
			$DSP->body .= $SD->info_row('no_fieldtypes');
		}

		$DSP->body .= $SD->block_c();

		if ($standalone)
		{
			// Close form
			$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit($LANG->line('apply')))
			            . $DSP->form_c();
		}

		ob_start();
?>
<style type="text/css" charset="utf-8">
	#ffsettings a.toggle { display:block; cursor:pointer; }
	#ffsettings a.toggle.hide { display:none; }
	#ffsettings a.toggle.disabled { color:#000; opacity:0.4; cursor:default; }
	#ffsettings .ftsettings { margin:-3px -1px -1px; }
	#ffsettings .ftsettings, #ffsettings .ftsettings .tableCellOne, #ffsettings .ftsettings .tableCellTwo, #ffsettings .ftsettings .box { background:#262e33; color:#999; }
	#ffsettings .ftsettings .box { margin: 0; padding: 10px 15px; border: none; border-top: 1px solid #283036; background: -webkit-gradient(linear, 0 0, 0 100%, from(#262e33), to(#22292e)); }
	#ffsettings .ftsettings table tr td .box { margin: -1px -8px; }
	#ffsettings .ftsettings a, #ffsettings .ftsettings p, #ffsettings .ftsettings .subtext { color: #999; }
	#ffsettings .ftsettings input.input, #ffsettings .ftsettings textarea { background:#fff; color:#333; }
	#ffsettings .ftsettings table { border:none; }
	#ffsettings .ftsettings table tr td { border-top:1px solid #1d2326; padding-left:8px; padding-right:8px;  }
	#ffsettings .ftsettings table tr td.tableHeading { color:#ddd; background:#232a2e; }
	#ffsettings .ftsettings .defaultBold { color:#ccc; }
	#ffsettings .ftsettings .tableCellOne, #ffsettings .ftsettings .tableCellOneBold, #ffsettings .ftsettings .tableCellTwo, #ffsettings .ftsettings .tableCellTwoBold { border-bottom:none; }
</style>
<?php
		$this->snippets['head'][] = ob_get_contents();
		ob_end_clean();
	}

	/**
	 * Save Fieldtypes Manager Settings
	 */
	function save_fieldtypes_manager()
	{
		global $DB;

		// fieldtype settings
		if (isset($_POST['ftypes']))
		{
			foreach($_POST['ftypes'] as $file => $ftype_post)
			{
				// Initialize
				if (($ftype = $this->_init_ftype($file)) !== FALSE)
				{
					$enabled = ($ftype_post['enabled'] == 'y');

					// skip if new and not enabled yet
					if ( ! $enabled AND $ftype->_is_new) continue;

					// insert a new row if it's new
					if ($enabled AND $ftype->_is_new)
					{
						$DB->query($DB->insert_string('exp_ff_fieldtypes', array(
							'class'   => $file,
							'version' => $ftype->info['version']
						)));

						// get the fieldtype_id
						$query = $DB->query('SELECT fieldtype_id FROM exp_ff_fieldtypes WHERE class = "'.$file.'"');
						$ftype->_fieldtype_id = $query->row['fieldtype_id'];

						// insert hooks
						$this->_insert_ftype_hooks($ftype);

						// call update()
						if (method_exists($ftype, 'update'))
						{
							$ftype->update(FALSE);
						}
					}

					$data = array(
						'enabled' => ($enabled ? 'y' : 'n')
					);

					if ($enabled)
					{
						$settings = (isset($_POST['ftype']) AND isset($_POST['ftype'][$ftype->_class_name]))
						  ?  $_POST['ftype'][$ftype->_class_name]
						  :  array();

						// let the fieldtype do what it wants with them
						if (method_exists($ftype, 'save_site_settings'))
						{
							$settings = $ftype->save_site_settings($settings);
							if ( ! is_array($settings)) $settings = array();
						}
						$data['settings'] = $this->_serialize($settings);
					}

					$DB->query($DB->update_string('exp_ff_fieldtypes', $data, 'fieldtype_id = "'.$ftype->_fieldtype_id.'"'));
				}
			}

			return TRUE;
		}

		return FALSE;
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
		//           ------------ These braces brought to you by Leevi Graham ------------
		//          ↓                                                                     ↓
		$priority = (isset($this->hooks[$hook]) AND isset($this->hooks[$hook]['priority']))
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
		global $IN;

		// are we saving a field?
		if($IN->GBL('M', 'GET') == 'blog_admin' AND $IN->GBL('P', 'GET') == 'update_weblog_fields' AND isset($_POST['field_type']))
		{
			$this->publish_admin_edit_field_save();
		}

		$args = func_get_args();
		return $this->forward_ff_hook('sessions_start', $args);
	}

	/**
	 * Publish Admin - Edit Field Form - Fieldtype Menu
	 *
	 * Allows modifying or adding onto Custom Weblog Fieldtype Pulldown
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $typemenu  The contents of the type menu
	 * @return string  The modified $typemenu
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_pulldown/
	 */
	function publish_admin_edit_field_type_pulldown($data, $typemenu)
	{
		global $DSP;

		$r = $this->get_last_call($typemenu);

		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			// only list normal fieldtypes
			if (method_exists($ftype, 'display_field'))
			{
				$field_type = 'ftype_id_'.$ftype->_fieldtype_id;
				$r .= $DSP->input_select_option($field_type, $ftype->info['name'], ($data['field_type'] == $field_type ? 1 : 0));
			}
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

		// Fetch the FF lang file
		$LANG->fetch_language_file('fieldframe');

		// Prepare fieldtypes for following Publish Admin hooks
		$field_settings_tmpl = array(
			'cell1' => '',
			'cell2' => '',
			'rows' => array(),
			'formatting_available' => FALSE,
			'direction_available' => FALSE
		);

		$formatting_available = array();
		$direction_available = array();
		$prev_ftype_id = '';

		$this->data = $data;

		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
			$selected = ($ftype_id == $this->data['field_type']) ? TRUE : FALSE;
			if (method_exists($ftype, 'display_field_settings'))
			{
				// Load the language file
				if ( ! $ftype->info['no_lang']) $LANG->fetch_language_file($class_name);

				$field_settings = array_merge(
					(isset($ftype->default_field_settings) ? $ftype->default_field_settings : array()),
					($selected ? $this->_unserialize($this->data['ff_settings']) : array())
				);
				$ftype->_field_settings = array_merge($field_settings_tmpl, $ftype->display_field_settings($field_settings));
			}
			else
			{
				$ftype->_field_settings = $field_settings_tmpl;
			}

			if ($ftype->_field_settings['formatting_available']) $formatting_available[] = $ftype->_fieldtype_id;
			if ($ftype->_field_settings['direction_available']) $direction_available[] = $ftype->_fieldtype_id;

			if ($selected) $prev_ftype_id = $ftype_id;
		}

		unset($this->data);

		// Add the JS
		ob_start();
?>
var prev_ftype_id = '<?php echo $prev_ftype_id ?>';

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

			// show cells
			while(cell = document.getElementById(id+'_cell'+c))
			{
				//var showDiv = document.getElementById(id+'_cell'+c);
				var divs = cell.parentNode.childNodes;
				for(var i=0; i < divs.length; i++)
				{
					var div = divs[i];
					if ( ! (div.nodeType == 1 && div.id)) continue;
					div.style.display = (div == cell) ? 'block' : 'none';
				}
				c++;
			}

			// show rows
			while(row = document.getElementById(id+'_row'+r))
			{
				row.style.display = 'table-row';
				r++;
			}

			// show/hide formatting
			if ([<?php echo implode(',', $formatting_available) ?>].indexOf(parseInt(id.substring(9))) != -1)
			{
				document.getElementById('formatting_block').style.display = 'block';
				document.getElementById('formatting_unavailable').style.display = 'none';
			}
			else
			{
				document.getElementById('formatting_block').style.display = 'none';
				document.getElementById('formatting_unavailable').style.display = 'block';
			}

			// show/hide direction
			if ([<?php echo implode(',', $direction_available) ?>].indexOf(parseInt(id.substring(9))) != -1)
			{
				document.getElementById('direction_available').style.display = 'block';
				document.getElementById('direction_unavailable').style.display = 'none';
			}
			else
			{
				document.getElementById('direction_available').style.display = 'none';
				document.getElementById('direction_unavailable').style.display = 'block';
			}

			prev_ftype_id = id;
		}
<?php
		$r = $this->get_last_call($js);
		$r = preg_replace('/(function\s+showhide_element\(\s*id\s*\)\s*{)/is', ob_get_contents(), $r);
		ob_end_clean();

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
	 * Allows modifying or adding onto Custom Weblog Fieldtype - First Table Cell
	 *
	 * @param  array   $data  The data about this field from the database
	 * @param  string  $cell  The contents of the cell
	 * @return string  The modified $cell
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_admin_edit_field_type_cellone/
	 */
	function publish_admin_edit_field_type_cellone($data, $cell)
	{
		global $DSP;

		$r = $this->_publish_admin_edit_field_type_cell($data, $cell, '1');

		// formatting
		foreach($this->_get_ftypes() as $class_name => $ftype)
		{
			$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
			$r .= $DSP->input_hidden('ftype['.$ftype_id.'][formatting_available]', ($ftype->_field_settings['formatting_available'] ? 'y' : 'n'));
		}

		$args = func_get_args();
		return $this->forward_ff_hook('publish_admin_edit_field_type_cellone', $args, $r);
	}

	/**
	 * Publish Admin - Edit Field Form - Cell Two
	 *
	 * Allows modifying or adding onto Custom Weblog Fieldtype - Second Table Cell
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

		$r = $this->get_last_call($r);

		if ($ftypes = $this->_get_ftypes())
		{
			$rows = '';
			foreach($ftypes as $class_name => $ftype)
			{
				$ftype_id = 'ftype_id_'.$ftype->_fieldtype_id;
				$selected = ($data['field_type'] == $ftype_id);

				foreach($ftype->_field_settings['rows'] as $index => $row)
				{
					$class = $index % 2 ? 'tableCellOne' : 'tableCellTwo';
					$rows .= '<tr id="'.$ftype_id.'_row'.($index+1).'"' . ($selected ? '' : ' style="display:none;"') . '>'
					       . '<td class="'.$class.'"'.(isset($row[1]) ? '' : ' colspan="2"').'>'
					       . $this->_group_ftype_inputs($ftype_id, $row[0])
					       . $DSP->td_c()
					       . (isset($row[1])
					            ?  $DSP->td($class)
					             . $this->_group_ftype_inputs($ftype_id, $row[1])
					             . $DSP->td_c()
					             . $DSP->tr_c()
					            : '');
				}

				if ($selected)
				{
					// show/hide formatting
					if ($ftype->_field_settings['formatting_available'])
					{
						$formatting_search = 'none';
						$formatting_replace = 'block';
					}
					else
					{
						$formatting_search = 'block';
						$formatting_replace = 'none';
					}
					$r = preg_replace('/(\sid\s*=\s*([\'\"])formatting_block\2.*display\s*:\s*)'.$formatting_search.'(\s*;)/isU', '$1'.$formatting_replace.'$3', $r);
					$r = preg_replace('/(\sid\s*=\s*([\'\"])formatting_unavailable\2.*display\s*:\s*)'.$formatting_replace.'(\s*;)/isU', '$1'.$formatting_search.'$3', $r);

					// show/hide direction
					if ($ftype->_field_settings['direction_available'])
					{
						$direction_search = 'none';
						$direction_replace = 'block';
					}
					else
					{
						$direction_search = 'block';
						$direction_replace = 'none';
					}
					$r = preg_replace('/(\sid\s*=\s*([\'\"])direction_available\2.*display\s*:\s*)'.$direction_search.'(\s*;)/isU', '$1'.$direction_replace.'$3', $r);
					$r = preg_replace('/(\sid\s*=\s*([\'\"])direction_unavailable\2.*display\s*:\s*)'.$direction_replace.'(\s*;)/isU', '$1'.$direction_search.'$3', $r);
				}
			}

			$r = preg_replace('/(<tr>\s*<td[^>]*>\s*<div[^>]*>\s*'.$LANG->line('deft_field_formatting').'\s*<\/div>)/is', $rows.'$1', $r);
		}

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
			if (isset($matches[1]))
			{
				$ftype_id = $matches[1];
				$settings = (isset($_POST['ftype']) AND isset($_POST['ftype'][$_POST['field_type']]))
				  ?  $_POST['ftype'][$_POST['field_type']]
				  :  array();

				// formatting
				if (isset($settings['formatting_available']))
				{
					if ($settings['formatting_available'] == 'n')
					{
						$_POST['field_fmt'] = 'none';
						$_POST['field_show_fmt'] = 'n';
					}
					unset($settings['formatting_available']);
				}

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
				$_POST['ff_settings'] = $this->_serialize($settings);
			}
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
	 * Display - Show Full Control Panel - Start
	 *
	 * - Rewrite CP's HTML
	 * - Find/Replace stuff, etc.
	 *
	 * @param  string  $end  The content of the admin page to be outputted
	 * @return string  The modified $out
	 * @see    http://expressionengine.com/developers/extension_hooks/show_full_control_panel_end/
	 */
	function show_full_control_panel_start($out = '')
	{
		global $IN, $DB, $REGX, $DSP;

		$out = $this->get_last_call($out);

		// are we displaying the custom field list?
		if ($IN->GBL('C', 'GET') == 'admin' AND $IN->GBL('M', 'GET') == 'utilities' AND $IN->GBL('P', 'GET') == 'fieldtypes_manager' AND defined('FT_PATH'))
		{
			$this->fieldtypes_manager();
		}

		$args = func_get_args();
		return $this->forward_ff_hook('show_full_control_panel_start', $args, $out);
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
	function show_full_control_panel_end($out = '')
	{
		global $IN, $DB, $REGX, $DSP, $LANG;

		$out = $this->get_last_call($out);

		// are we displaying the custom field list?
		if ($IN->GBL('M', 'GET') == 'blog_admin' AND in_array($IN->GBL('P', 'GET'), array('field_editor', 'update_weblog_fields', 'delete_field', 'update_field_order')))
		{
			// get the FF fieldtypes
			foreach($this->_get_fields() as $field_id => $field)
			{
				// add fieldtype name to this field
				$out = preg_replace("/(C=admin&amp;M=blog_admin&amp;P=edit_field&amp;field_id={$field_id}[\'\"].*?<\/td>.*?<td.*?>.*?<\/td>.*?)<\/td>/is",
				                      '$1'.$REGX->form_prep($field['ftype']->info['name']).'</td>', $out);
			}
		}
		// is this the main admin page?
		else if ($IN->GBL('C', 'GET') == 'admin' AND ! ($IN->GBL('M', 'GET') AND $IN->GBL('P', 'GET')))
		{
			$LANG->fetch_language_file('fieldframe');
			$out = preg_replace('/(<li><a href=.+C=admin&amp;M=utilities&amp;P=extensions_manager.+<\/a><\/li>)/',
				"$1\n<li>".$DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=fieldtypes_manager', $LANG->line('fieldtypes_manager')).'</li>', $out, 1);
		}

		foreach($this->snippets as $placement => $snippets)
		{
			$placement = '</'.$placement.'>';
			foreach(array_unique($snippets) as $snippet)
			{
				$out = str_replace($placement, NL.$snippet.NL.$placement, $out);
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
		global $REGX, $DSP;

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

			// is there post data available?
			if (isset($_POST[$field_name])) $field_data = $_POST[$field_name];

			$this->row = $row;
			$r = $DSP->qdiv('ff-ft', $field['ftype']->display_field($field_name, $this->_unserialize($field_data), $field['settings']));
			unset($this->row);
		}

		// place field data in a basic textfield if the fieldtype
		// wasn't enabled or didn't have a display_field method
		if ( ! isset($r))
		{
			$r = $DSP->input_textarea($field_name, $field_data, 1, 'textarea', '100%');
		}

		$r = '<input type="hidden" name="field_ft_'.$row['field_id'].'" value="none" />'.$r;

		$args = func_get_args();
		return $this->forward_ff_hook('publish_form_field_unique', $args, $r);
	}

	/**
	 * Publish Form - Submit New Entry - Start
	 *
	 * Add More Stuff to do when you first submit an entry
	 *
	 * @see http://expressionengine.com/developers/extension_hooks/submit_new_entry_start/
	 */
	function submit_new_entry_start()
	{
		$this->_save_fields();

		return $this->forward_ff_hook('submit_new_entry_start');
	}

	/**
	 * Save Fields
	 *
	 * @access private
	 */
	function _save_fields()
	{
		foreach($this->_get_fields() as $this->field_id => $field)
		{
			$this->field_name = 'field_id_'.$this->field_id;

			if (isset($_POST[$this->field_name]))
			{
				if (method_exists($field['ftype'], 'save_field'))
				{
					if ($field['ftype']->postpone_saves)
					{
						// save it for later
						$field['data'] = $_POST[$this->field_name];
						$this->postponed_saves[$this->field_id] = $field;

						// prevent the system from overwriting the current data
						unset($_POST[$this->field_name]);
					}
					else
					{
						$_POST[$this->field_name] = $field['ftype']->save_field($_POST[$this->field_name], $field['settings']);
					}
				}

				if (isset($_POST[$this->field_name]) AND is_array($_POST[$this->field_name]))
				{
					// serialize for DB storage
					$_POST[$this->field_name] = $_POST[$this->field_name]
					  ?  $this->_serialize($_POST[$this->field_name])
					  :  '';
				}

				// unset extra FF post vars
				$prefix = $this->field_name.'_';
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

		if (isset($this->field_id)) unset($this->field_id);
		if (isset($this->field_name)) unset($this->field_name);
	}

	/**
	 * Publish Form - Submit New Entry - End
	 *
	 * After an entry is submitted, do more processing
	 *
	 * @param string  $entry_id      Entry's ID
	 * @param array   $data          Array of data about entry (title, url_title)
	 * @param string  $ping_message  Error message if trackbacks or pings have failed to be sent
	 * @see   http://expressionengine.com/developers/extension_hooks/submit_new_entry_end/
	 */
	function submit_new_entry_end($entry_id, $data, $ping_message)
	{
		$this->_postponed_save($entry_id);

		$args = func_get_args();
		return $this->forward_ff_hook('submit_new_entry_end', $args);
	}

	/**
	 * Publish Form - Start
	 *
	 * Allows complete rewrite of Publish page
	 *
	 * @param  string  $which             new, preview, edit, or save
	 * @param  string  $submission_error  submission error, if any
	 * @param  string  $entry_id          Entry ID being sent to the form
	 * @see    http://expressionengine.com/developers/extension_hooks/publish_form_start/
	 */
	function publish_form_start($which, $submission_error, $entry_id)
	{
		global $IN;

		$this->which = $which;

		// is this a quicksave/preview?
		if (in_array($this->which, array('save', 'preview')))
		{
			if ($this->which == 'preview')
			{
				// submit_new_entry_start() doesn't get called on preview
				// so fill in for it here
				$this->_save_fields();
			}

			if ( ! $entry_id) $entry_id = $IN->GBL('entry_id');
			$this->_postponed_save($entry_id);
		}

		unset($this->which);

		$args = func_get_args();
		return $this->forward_ff_hook('publish_form_start', $args);
	}

	/**
	 * Postponed Save
	 *
	 * @access private
	 */
	function _postponed_save($entry_id)
	{
		global $DB;

		foreach($this->postponed_saves as $this->field_id => $field)
		{
			$this->field_name = 'field_id_'.$this->field_id;

			$_POST[$this->field_name] = $field['ftype']->save_field($field['data'], $field['settings'], $entry_id);

			if (is_array($_POST[$this->field_name]))
			{
				$_POST[$this->field_name] = $_POST[$this->field_name]
				  ?  $this->_serialize($_POST[$this->field_name])
				  :  '';
			}

			// manually save it to the db
			if ($entry_id && (! isset($this->which) || $this->which == 'save'))
			{
				$DB->query($DB->update_string('exp_weblog_data', array($this->field_name => $_POST[$this->field_name]), 'entry_id = '.$entry_id));
			}
		}

		if (isset($this->field_id)) unset($this->field_id);
		if (isset($this->field_name)) unset($this->field_name);
	}

	/**
	 * Weblog - SAEF - Start
	 *
	 * Rewrite the SAEF completely
	 *
	 * @param bool    $return_form  Return the No Cache version of the form or not
	 * @param string  $captcha  Cached CAPTCHA value from preview
	 * @param string  $weblog_id  Weblog ID for this form
	 * @see   http://expressionengine.com/developers/extension_hooks/weblog_standalone_form_start/
	 */
	function weblog_standalone_form_start($return_form, $captcha, $weblog_id)
	{
		global $DSP, $DB;

		// initialize Display
		if ( ! $DSP)
		{
			if ( ! class_exists('Display'))
			{
				require PATH_CP.'cp.display'.EXT;
			}
			$DSP = new Display();
		}

		// remember this is a SAEF for publish_form_field_unique
		$this->saef = TRUE;
		$this->saef_tag_count = 0;

		$args = func_get_args();
		return $this->forward_ff_hook('weblog_standalone_form_start', $args);
	}

	/**
	 * Weblog - SAEF - End
	 *
	 * Allows adding to end of submission form
	 *
	 * @param  string  $tagdata  The tag data for the submission form at the end of processing
	 * @return string  Modified $tagdata
	 * @see    http://expressionengine.com/developers/extension_hooks/weblog_standalone_form_end/
	 */
	function weblog_standalone_form_end($tagdata)
	{
		global $DSP;

		$tagdata = $this->get_last_call($tagdata);

		// parse fieldtype tags
		$tagdata = $this->weblog_entries_tagdata($tagdata);

		//if ($this->saef_tag_count)
		//{
			// append all snippets to the end of $tagdata
			foreach($this->snippets as $placement => $snippets)
			{
				foreach(array_unique($snippets) as $snippet)
				{
					$tagdata .= "\n".$snippet."\n";
				}
			}
		//}

		$this->saef = FALSE;
		unset($this->saef_tag_count);

		$args = func_get_args();
		return $this->forward_ff_hook('weblog_standalone_form_start', $args, $tagdata);
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
	function weblog_entries_tagdata($tagdata, $row=array(), $weblog=NULL)
	{
		global $REGX;

		$this->tagdata = $this->get_last_call($tagdata);
		$this->row = $row;
		$this->weblog = &$weblog;

		if ($fields = $this->_get_fields())
		{
			$fields_by_name = array();
			foreach($fields as $this->field_id => $this->field)
			{
				$fields_by_name[$this->field['name']] = array(
					'data'     => (isset($row['field_id_'.$this->field_id]) ? $this->_unserialize($row['field_id_'.$this->field_id], FALSE) : ''),
					'settings' => $this->field['settings'],
					'ftype'    => $this->field['ftype'],
					'helpers'  => array('field_id' => $this->field_id, 'field_name' => $this->field['name'])
				);
			}

			if (isset($this->field_id)) unset($this->field_id);
			if (isset($this->field)) unset($this->field);

			$this->_parse_tagdata($this->tagdata, $fields_by_name, TRUE);
		}

		// unset temporary helper vars
		$tagdata = $this->tagdata;
		unset($this->tagdata);
		unset($this->row);
		unset($this->weblog);

		$args = func_get_args();
		return $this->forward_ff_hook('weblog_entries_tagdata', $args, $tagdata);
	}

	/**
	 * Parse Tagdata
	 *
	 * @param  string  $tagdata  The Weblog Entries tagdata
	 * @param  string  $field_name  Name of the field to search for
	 * @param  mixed   $field_data  The field's value
	 * @param  array   $field_settings  The field's settings
	 * @param  object  $ftype  The field's fieldtype object
	 * @access private
	 */
	function _parse_tagdata(&$tagdata, $fields, $skip_unmatched_tags = FALSE)
	{
		global $FNS, $DSP;

		// -------------------------------------------
		//  Conditionals
		// -------------------------------------------

		$conditionals = array();

		foreach ($fields as $name => $field)
		{
			$conditionals[$name] = is_array($field['data']) ? ($field['data'] ? '1' : '') : $field['data'];
		}

		$tagdata = $FNS->prep_conditionals($tagdata, $conditionals);

		// -------------------------------------------
		//  Tag parsing
		// -------------------------------------------

		// find the next ftype tag
		$offset = 0;
		while (preg_match('/'.LD.'('.implode('|', array_keys($fields)).')(:(\w+))?(\s+.*)?'.RD.'/sU', $tagdata, $matches, PREG_OFFSET_CAPTURE, $offset))
		{
			$field_name = $matches[1][0];
			$field = $fields[$field_name];

			$tag_pos = $matches[0][1];
			$tag_len = strlen($matches[0][0]);
			$tagdata_pos = $tag_pos + $tag_len;
			$endtag = LD.SLASH.$field_name.(isset($matches[2][0]) ? $matches[2][0] : '').RD;
			$endtag_len = strlen($endtag);
			$endtag_pos = strpos($tagdata, $endtag, $tagdata_pos);
			$tag_func = (isset($matches[3][0]) AND $matches[3][0]) ? $matches[3][0] : '';

			// is this SAEF?
			if ($this->saef AND ! $tag_func)
			{
				// call display_field rather than display_tag

				foreach($field['helpers'] as $name => $value)
				{
					$this->$name = $value;
				}

				$new_tagdata = $DSP->qdiv('ff-ft', $field['ftype']->display_field('field_id_'.$field['helpers']['field_id'], $field['data'], $field['settings']));

				foreach($field['helpers'] as $name => $value)
				{
					unset($this->$name);
				}

				// update the tag count
				$this->saef_tag_count++;
			}
			else
			{
				if ( ! $tag_func) $tag_func = 'display_tag';
				$method_exists = method_exists($field['ftype'], $tag_func);

				if ($method_exists || ! $skip_unmatched_tags)
				{
					// get the params
					$params = isset($field['ftype']->default_tag_params)
					  ?  $field['ftype']->default_tag_params
					  :  array();
					if (isset($matches[4][0]) AND $matches[4][0] AND preg_match_all('/\s+([\w-:]+)\s*=\s*([\'\"])([^\2]*)\2/sU', $matches[4][0], $param_matches))
					{
						for ($j = 0; $j < count($param_matches[0]); $j++)
						{
							$params[$param_matches[1][$j]] = $param_matches[3][$j];
						}
					}

					// is this a tag pair?
					$field_tagdata = ($endtag_pos !== FALSE)
					  ?  substr($tagdata, $tagdata_pos, $endtag_pos - $tagdata_pos)
					  :  '';

					if ( ! $tag_func) $tag_func = 'display_tag';

					if ($method_exists)
					{
						foreach($field['helpers'] as $name => $value)
						{
							$this->$name = $value;
						}

						$new_tagdata = call_user_func_array(array(&$field['ftype'], $tag_func), array($params, $field_tagdata, $field['data'], $field['settings']));

						foreach($field['helpers'] as $name => $value)
						{
							unset($this->$name);
						}
					}
					else
					{
						$new_tagdata = $field['data'];
					}
				}
			}

			if (isset($new_tagdata))
			{
				$offset = $tag_pos;

				$tagdata = substr($tagdata, 0, $tag_pos)
				         . $new_tagdata
				         . substr($tagdata, ($endtag_pos !== FALSE ? $endtag_pos+$endtag_len : $tagdata_pos));

				unset($new_tagdata);
			}
			else
			{
				$offset = $tag_pos + $tag_len;
			}
		}
	}

	/**
	 * LG Addon Updater - Register a New Addon Source
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
			$source = 'http://pixelandtonic.com/ee/versions.xml';
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

		$args = func_get_args();
		return $this->forward_ff_hook('lg_addon_update_register_source', $args, $sources);
	}

	/**
	 * LG Addon Updater - Register a New Addon ID
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

		$args = func_get_args();
		return $this->forward_ff_hook('lg_addon_update_register_addon', $args, $addons);
	}

}


/**
 * Settings Display Class
 *
 * Provides FieldFrame settings-specific display methods
 *
 * @package  FieldFrame
 * @author   Brandon Kelly <brandon@pixelandtonic.com>
 */
class Fieldframe_SettingsDisplay {

	/**
	 * Fieldframe_SettingsDisplay Constructor
	 */
	function __construct()
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

		$this->block_count = 0;
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
		                        'style' => 'margin:'.($this->block_count ? '18px' : '0').' 0 0 0; width:100%;'.($title_line ? '' : ' border-top:1px solid #CACFD4;')
		                      ));
		if ($title_line)
		{
			$r .= $this->row(array($this->get_line($title_line)), 'tableHeading');
		}

		$this->block_count++;

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
	function info_row($info_line, $styles=TRUE)
	{
		return $this->row(array(
		                   '<div class="box"' . ($styles ? ' style="border-width:0 0 1px 0; margin:0; padding:10px 5px"' : '') . '>'
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
	 * @param  array   $attr   Input variables
	 * @return string  The text field
	 */
	function text($name, $value, $attr=array())
	{
		global $DSP;
		$attr = array_merge(array('size'=>'','maxlength'=>'','style'=>'input','width'=>'90%','extras'=>'','convert'=>FALSE), $attr);
		return $DSP->input_text($name, $value, $attr['size'], $attr['maxlength'], $attr['style'], $attr['width'], $attr['extras'], $attr['convert']);
	}

	/**
	 * Textarea
	 *
	 * @param  string  $name   Name of the textarea
	 * @param  string  $value  Initial value
	 * @param  array   $attr   Input variables
	 * @return string  The textarea
	 */
	function textarea($name, $value, $attr=array())
	{
		global $DSP;
		$attr = array_merge(array('rows'=>'3','style'=>'textarea','width'=>'91%','extras'=>'','convert'=>FALSE), $attr);
		return $DSP->input_textarea($name, $value, $attr['rows'], $attr['style'], $attr['width'], $attr['extras'], $attr['convert']);
	}

	/**
	 * Select Options
	 *
	 * @param  string  $value    initial selected value(s)
	 * @param  array   $options  list of the options
	 * @return string  the select/multi-select options HTML
	 */
	function _select_options($value, $options)
	{
		global $DSP;

		$r = '';
		foreach($options as $option_value => $option_line)
		{
			if (is_array($option_line))
			{
				$r .= '<optgroup label="'.$option_value.'">'."\n"
				    .   $this->_select_options($value, $option_line)
				    . '</optgroup>'."\n";
			}
			else
			{
				$selected = is_array($value)
				              ?  in_array($option_value, $value)
				              :  ($option_value == $value);
				$r .= $DSP->input_select_option($option_value, $this->get_line($option_line), $selected ? 1 : 0);
			}
		}
		return $r;
	}

	/**
	 * Select input
	 *
	 * @param  string  $name     Name of the select
	 * @param  mixed   $value    Initial selected value(s)
	 * @param  array   $options  List of the options
	 * @param  array   $attr     Input variables
	 * @return string  The select input
	 */
	function select($name, $value, $options, $attr=array())
	{
		global $DSP;
		$attr = array_merge(array('multi'=>NULL, 'size'=>0, 'width'=>''), $attr);
		return $DSP->input_select_header($name, $attr['multi'], $attr['size'], $attr['width'])
		     . $this->_select_options($value, $options)
		     . $DSP->input_select_footer();
	}

	/**
	 * Multiselect Input
	 *
	 * @param  string  $name     Name of the textfield
	 * @param  array   $values   Initial selected values
	 * @param  array   $options  List of the options
	 * @param  array   $attr     Input variables
	 * @return string  The multiselect input
	 */
	function multiselect($name, $values, $options, $attr=array())
	{
		$attr = array_merge($attr, array('multi' => 1));
		return $this->select($name, $values, $options, $attr);
	}

	/**
	 * Radio Group
	 *
	 * @param  string  $name     Name of the radio inputs
	 * @param  string  $value    Initial selected value
	 * @param  array   $options  List of the options
	 * @param  array   $attr     Input variables
	 * @return string  The text input
	 */
	function radio_group($name, $value, $options, $attr=array())
	{
		global $DSP;
		$attr = array_merge(array('extras'=>''), $attr);
		$r = '';
		foreach($options as $option_value => $option_name)
		{
			if ($r) $r .= NBS.NBS.' ';
			$r .= '<label style="white-space:nowrap;">'
			    . $DSP->input_radio($name, $option_value, ($option_value == $value) ? 1 : 0, $attr['extras'])
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
 * @author   Brandon Kelly <brandon@pixelandtonic.com>
 */
class Fieldframe_Fieldtype {

	var $_fieldframe = TRUE;

	function get_last_call($param=FALSE)
	{
		global $FF;
		return $FF->get_last_call($param);
	}

	function insert($at, $html)
	{
		global $FF;
		$FF->snippets[$at][] = $html;
	}

	function insert_css($css)
	{
		$this->insert('head', '<style type="text/css" charset="utf-8">'.NL.$css.NL.'</style>');
	}

	function insert_js($js)
	{
		$this->insert('body', '<script type="text/javascript">;'.NL.$js.NL.'</script>');
	}

	function include_css($filename)
	{
		$this->insert('head', '<link rel="stylesheet" type="text/css" href="'.FT_URL.$this->_class_name.'/'.$filename.'" charset="utf-8" />');
	}

	function include_js($filename)
	{
		$this->insert('body', '<script type="text/javascript" src="'.FT_URL.$this->_class_name.'/'.$filename.'" charset="utf-8"></script>');
	}

	function options_setting($options=array(), $indent = '')
	{
		$r = '';
		foreach($options as $name => $label)
		{
			if ($r !== '') $r .= "\n";
			$r .= $indent.$name;
			if (is_array($label)) $r .= "\n".$this->options_setting($label, $indent.'    ');
			else if ($name != $label) $r .= ' : '.$label;
		}
		return $r;
	}

	function save_options_setting($options = '', $total_levels = 1)
	{
		// prepare options
		$options = preg_split('/[\r\n]+/', $options);
		foreach($options as &$option)
		{
			$option_parts = preg_split('/\s:\s/', $option, 2);
			$option = array();
			$option['indent'] = preg_match('/^\s+/', $option_parts[0], $matches) ? strlen(str_replace("\t", '    ', $matches[0])) : 0;
			$option['name']   = trim($option_parts[0]);
			$option['value']  = isset($option_parts[1]) ? trim($option_parts[1]) : $option['name'];
		}

		return $this->_structure_options($options, $total_levels);
	}

	function _structure_options(&$options, $total_levels, $level = 1, $indent = -1)
	{
		$r = array();

		while ($options)
		{
			if ($indent == -1 || $options[0]['indent'] > $indent)
			{
				$option = array_shift($options);
				$children = ( ! $total_levels OR $level < $total_levels)
				              ?  $this->_structure_options($options, $total_levels, $level+1, $option['indent']+1)
				              :  FALSE;
				$r[(string)$option['name']] = $children ? $children : (string)$option['value'];
			}
			else if ($options[0]['indent'] <= $indent)
			{
				break;
			}
		}

		return $r;
	}

	function prep_iterators(&$tagdata)
	{
		// find {switch} tags
		$this->_switches = array();
		$tagdata = preg_replace_callback('/'.LD.'switch\s*=\s*([\'\"])([^\1]+)\1'.RD.'/sU', array(&$this, '_get_switch_options'), $tagdata);

		$this->_count_tag = 'count';
		$this->_iterator_count = 0;
	}

	function _get_switch_options($match)
	{
		global $FNS;

		$marker = LD.'SWITCH['.$FNS->random('alpha', 8).']SWITCH'.RD;
		$this->_switches[] = array('marker' => $marker, 'options' => explode('|', $match[2]));
		return $marker;
	}

	function parse_iterators(&$tagdata)
	{
		global $TMPL;

		// {switch} tags
		foreach($this->_switches as $i => $switch)
		{
			$option = $this->_iterator_count % count($switch['options']);
			$tagdata = str_replace($switch['marker'], $switch['options'][$option], $tagdata);
		}

		// update the count
		$this->_iterator_count++;

		// {count} tags
		$tagdata = $TMPL->swap_var_single($this->_count_tag, $this->_iterator_count, $tagdata);
	}

}

/**
 * Fieldframe Multi-select Fieldtype Base Class
 *
 * Provides Multi-select fieldtypes with their base functionality
 *
 * @package  FieldFrame
 * @author   Brandon Kelly <brandon@pixelandtonic.com>
 */
class Fieldframe_Multi_Fieldtype extends Fieldframe_Fieldtype {

	var $default_field_settings = array(
		'options' => array(
			'Option 1' => 'Option 1',
			'Option 2' => 'Option 2',
			'Option 3' => 'Option 3'
		)
	);

	var $default_cell_settings = array(
		'options' => array(
			'Opt 1' => 'Opt 1',
			'Opt 2' => 'Opt 2'
		)
	);

	var $default_tag_params = array(
		'sort'      => '',
		'backspace' => '0'
	);

	var $settings_label = 'field_list_items';
	var $total_option_levels = 1;

	/**
	 * Display Field Settings
	 * 
	 * @param  array  $field_settings  The field's settings
	 * @return array  Settings HTML (cell1, cell2, rows)
	 */
	function display_field_settings($field_settings)
	{
		global $DSP, $LANG;

		$cell2 = $DSP->qdiv('defaultBold', $LANG->line($this->settings_label))
		       . $DSP->qdiv('default', $LANG->line('field_list_instructions'))
		       . $DSP->input_textarea('options', $this->options_setting($field_settings['options']), '6', 'textarea', '99%')
		       . $DSP->qdiv('default', $LANG->line('option_setting_examples'));

		return array('cell2' => $cell2);
	}

	/**
	 * Display Cell Settings
	 * 
	 * @param  array  $cell_settings  The cell's settings
	 * @return string  Settings HTML
	 */
	function display_cell_settings($cell_settings)
	{
		global $DSP, $LANG;

		$r = '<label class="itemWrapper">'
		   . $DSP->qdiv('defaultBold', $LANG->line($this->settings_label))
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
		$field_settings['options'] = $this->save_options_setting($field_settings['options'], $this->total_option_levels);
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
	 * Prep Field Data
	 *
	 * Ensures $field_data is an array.
	 *
	 * @param  mixed  &$field_data  The currently-saved $field_data
	 */
	function prep_field_data(&$field_data)
	{
		if ( ! is_array($field_data))
		{
			$field_data = array_filter(preg_split("/[\r\n]+/", $field_data));
		}
	}

	function _find_option($needle, $haystack)
	{
		foreach ($haystack as $key => $value)
		{
			$r = $value;
			if ($needle == $key OR (is_array($value) AND (($r = $this->_find_option($needle, $value)) !== FALSE)))
			{
				return $r;
			}
		}
		return FALSE;
	}

	/**
	 * Display Tag
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  Modified $tagdata
	 */
	function display_tag($params, $tagdata, $field_data, $field_settings)
	{
		global $TMPL;

		if ( ! $tagdata)
		{
			return $this->ul($params, $tagdata, $field_data, $field_settings);
		}

		$this->prep_field_data($field_data);
		$r = '';

		if ($field_settings['options'] AND $field_data)
		{
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

			// prepare for {switch} and {count} tags
			$this->prep_iterators($tagdata);

			foreach($field_data as $option_name)
			{
				if (($option = $this->_find_option($option_name, $field_settings['options'])) !== FALSE)
				{
					// copy $tagdata
					$option_tagdata = $tagdata;

					// simple var swaps
					$option_tagdata = $TMPL->swap_var_single('option', $option, $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('option_name', $option_name, $option_tagdata);

					// parse {switch} and {count} tags
					$this->parse_iterators($option_tagdata);

					$r .= $option_tagdata;
				}
			}

			if ($params['backspace'])
			{
				$r = substr($r, 0, -$params['backspace']);
			}
		}

		return $r;
	}

	/**
	 * Unordered List
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  unordered list of options
	 */
	function ul($params, $tagdata, $field_data, $field_settings)
	{
		return "<ul>\n"
		     .   $this->display_tag($params, "  <li>{option}</li>\n", $field_data, $field_settings)
		     . '</ul>';
	}

	/**
	 * Ordered List
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  ordered list of options
	 */
	function ol($params, $tagdata, $field_data, $field_settings)
	{
		return "<ol>\n"
		     .   $this->display_tag($params, "  <li>{option}</li>\n", $field_data, $field_settings)
		     . '</ol>';
	}

	/**
	 * All Options
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return string  Modified $tagdata
	 */
	function all_options($params, $tagdata, $field_data, $field_settings, $iterator_count = 0)
	{
		global $TMPL;

		Fieldframe_Multi_Fieldtype::prep_field_data($field_data);
		$r = '';

		if ($field_settings['options'])
		{
			// optional sorting
			if ($sort = strtolower($params['sort']))
			{
				if ($sort == 'asc')
				{
					asort($field_settings['options']);
				}
				else if ($sort == 'desc')
				{
					arsort($field_settings['options']);
				}
			}

			// prepare for {switch} and {count} tags
			$this->prep_iterators($tagdata);
			$this->_iterator_count += $iterator_count;

			foreach($field_settings['options'] as $option_name => $option)
			{
				if (is_array($option))
				{
					$r .= $this->all_options(array_merge($params, array('backspace' => '0')), $tagdata, $field_data, array('options' => $option), $this->_iterator_count);
				}
				else
				{
					// copy $tagdata
					$option_tagdata = $tagdata;

					// simple var swaps
					$option_tagdata = $TMPL->swap_var_single('option', $option, $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('option_name', $option_name, $option_tagdata);
					$option_tagdata = $TMPL->swap_var_single('selected', (in_array($option_name, $field_data) ? 1 : 0), $option_tagdata);

					// parse {switch} and {count} tags
					$this->parse_iterators($option_tagdata);

					$r .= $option_tagdata;
				}
			}

			if ($params['backspace'])
			{
				$r = substr($r, 0, -$params['backspace']);
			}
		}

		return $r;
	}

	/**
	 * Is Selected?
	 *
	 * @param  array   $params          Name/value pairs from the opening tag
	 * @param  string  $tagdata         Chunk of tagdata between field tag pairs
	 * @param  string  $field_data      Currently saved field value
	 * @param  array   $field_settings  The field's settings
	 * @return bool    whether or not the option is selected
	 */
	function selected($params, $tagdata, $field_data, $field_settings)
	{
		$this->prep_field_data($field_data);

		return (isset($params['option']) AND in_array($params['option'], $field_data)) ? 1 : 0;
	}

}
