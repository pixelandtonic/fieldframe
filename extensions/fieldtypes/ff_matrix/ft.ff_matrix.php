<?php

if ( ! defined('EXT')) exit('Invalid file request');


/**
 * FF Matrix Class
 *
 * @package   FieldFrame
 * @author    Brandon Kelly <me@brandon-kelly.com>
 * @copyright Copyright (c) 2009 Brandon Kelly
 * @license   http://creativecommons.org/licenses/by-sa/3.0/ Attribution-Share Alike 3.0 Unported
 */
class Ff_matrix extends Fieldframe_Fieldtype {

	/**
	 * Fieldtype Info
	 * @var array
	 */
	var $info = array(
		'name'     => 'FF Matrix',
		'version'  => '0.0.3',
		'desc'     => 'Provides a tabular data fieldtype',
		'docs_url' => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-checkbox'
	);

	/**
	 * Fieldtype Hooks
	 * @var array
	 */
	var $hooks = array(
		'sessions_start' => array('priority' => '1'),
		'show_full_control_panel_end'
	);

	/**
	 * Default Field Settings
	 * @var array
	 */
	var $default_field_settings = array(
		'cols' => array(
			'1' => array('name' => 'cell_1', 'label' => 'Cell 1', 'type' => 'ff_matrix_text'),
			'2' => array('name' => 'cell_2', 'label' => 'Cell 2', 'type' => 'ff_matrix_textarea')
		)
	);

	/**
	 * Get Fieldtypes
	 *
	 * @access private
	 */
	function _get_ftypes()
	{
		global $FF;

		if ( ! isset($this->ftypes))
		{
			$this->ftypes = array();

			// Add the included FF Matrix cell types
			$this->ftypes['ff_matrix_text'] = new Ff_matrix_text();
			$this->ftypes['ff_matrix_textarea'] = new Ff_matrix_textarea();

			// Add the FF fieldtyes with display_cell
			foreach($FF->_get_ftypes() as $class_name => $ftype)
			{
				if (method_exists($ftype, 'display_cell'))
				{
					$this->ftypes[$class_name] = $ftype;
				}
			}

			// Sort by name
			$FF->_sort_ftypes($this->ftypes);
		}

		return $this->ftypes;
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

		// save field settings for show_full_control_panel_end
		$this->field_settings = $field_settings;

		// display the config skeleton
		$preview = $DSP->qdiv('defaultBold', $LANG->line('conf_label'))
                 . $DSP->qdiv('itemWrapper', $LANG->line('conf_subtext'))
		         . $DSP->div('ff_matrix_conf')
		         .   '<a class="button add" title="'.$LANG->line('add_column').'"></a>'
		         .   '<table cellspacing="0">'
		         .     '<tr class="tableHeading"></tr>'
		         .     '<tr class="preview"></tr>'
		         .     '<tr class="conf col"></tr>'
		         .     '<tr class="conf celltype"></tr>'
		         .     '<tr class="conf cellsettings"></tr>'
		         .     '<tr class="delete"></tr>'
		         .   '</table>'
		         . $DSP->div_c();

		return array('rows' => array(array($preview)));
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
		global $LANG;

		$out = $this->get_last_call($out);

		// are we displaying the field settings?
		if (isset($this->field_settings))
		{
			$this->include_css('styles/ff_matrix.css', $out);
			$this->include_js('scripts/jquery.sortable_table.js', $out);
			$this->include_js('scripts/jquery.ff_matrix_conf.js', $out);

			$cell_types = '';
			foreach($this->_get_ftypes() as $class_name => $ftype)
			{
				$cell_settings = isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array();
				$preview = $ftype->display_cell('', '', $cell_settings);
				$settings_display = method_exists($ftype, 'display_cell_settings') ? $ftype->display_cell_settings($cell_settings) : '';
				$cell_types .= ($cell_types ? ','.NL : '')
				             . '"'.$class_name.'": {' . NL
				             .    'name: "'.$ftype->info['name'].'",' . NL
				             .    'preview: "'.preg_replace('/[\n\r]/', ' ', addslashes($preview)).'",' . NL
				             .    'settings: "'.preg_replace('/[\n\r]/', "\\n", addslashes($settings_display)).'"' . NL
				             . '}';
			}

			$cols = '';
			foreach($this->field_settings['cols'] as $col_id => $col)
			{
				$cols .= ($cols ? ','.NL : '')
				       . $col_id.': {'. NL
				       .   'name: "'.$col['name'].'",' . NL
				       .   'label: "'.$col['label'].'",' . NL
				       .   'type: "'.$col['type'].'"' . NL
				       . '}';
			}

			$js = 'jQuery(window).bind("load", function() {' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.colName = "'.$LANG->line('col_name').'";' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.colLabel = "'.$LANG->line('col_label').'";' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.cellType = "'.$LANG->line('cell_type').'";' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.cell = "'.$LANG->line('cell').'";' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.deleteColumn = "'.$LANG->line('delete_column').'";' . NL
			    . '  jQuery.fn.ffMatrixConf.lang.confirmDeleteColumn = "'.$LANG->line('confirm_delete_column').'";' . NL
			    . NL
			    . '  jQuery.fn.ffMatrixConf.cellTypes = {' . NL
			    .      $cell_types . NL
			    . '  };' . NL
			    . NL
			    . '  jQuery(".ff_matrix_conf").ffMatrixConf('.$this->_fieldtype_id.', {' . NL
			    .      $cols . NL
			    .   '});' . NL
			    . '});';

			$this->insert_js($js, $out);
		}

		return $out;
	}

}


class Ff_matrix_text {

	var $_class_name = 'ff_matrix_text';

	var $info = array(
		'name' => 'Text'
	);

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		global $DSP;
		return $DSP->input_text($cell_name, $cell_value, '', '', '', '95%');
	}

}

class Ff_matrix_textarea {

	var $_class_name = 'ff_matrix_textarea';

	var $info = array(
		'name' => 'Textarea'
	);

	function display_cell($cell_name, $cell_value, $cell_settings)
	{
		global $DSP;
		return $DSP->input_textarea($cell_name, $cell_value, '', '', '95%');
	}

}


/* End of file ft.ff_matrix.php */
/* Location: ./system/fieldtypes/ff_matrix/ft.ff_matrix.php */