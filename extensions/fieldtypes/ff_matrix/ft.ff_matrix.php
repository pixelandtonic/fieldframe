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
		'version'  => '0.0.4',
		'desc'     => 'Provides a tabular data fieldtype',
		'docs_url' => 'http://wiki.github.com/brandonkelly/bk.fieldframe.ee_addon/ff-checkbox'
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

		$this->include_css('styles/ff_matrix.css');
		$this->include_js('scripts/jquery.sorttable.js');
		$this->include_js('scripts/jquery.ff_matrix_conf.js');

		$ftypes = $this->_get_ftypes();

		$cell_types = array();
		foreach($ftypes as $class_name => $ftype)
		{
			$cell_settings = isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array();

			$cell_types[$class_name] = array(
				'name' => $ftype->info['name'],
				'preview' => $ftype->display_cell('', '', $cell_settings),
				'settings' => (method_exists($ftype, 'display_cell_settings') ? $ftype->display_cell_settings($cell_settings) : '')
			);
		}

		$cols = array();
		foreach($field_settings['cols'] as $col_id => $col)
		{
			$ftype = $ftypes[$col['type']];
			$cell_settings = array_merge(
				(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
				(isset($col['settings']) ? $col['settings'] : array())
			);

			$cols[$col_id] = array(
				'name' => $col['name'],
				'label' => $col['label'],
				'type' => $col['type'],
				'preview' => $ftype->display_cell('', '', $cell_settings),
				'settings' => (method_exists($ftype, 'display_cell_settings') ? $ftype->display_cell_settings($cell_settings) : '')
			);
		}

		$js = 'jQuery(window).bind("load", function() {' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.colName = "'.$LANG->line('col_name').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.colLabel = "'.$LANG->line('col_label').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.cellType = "'.$LANG->line('cell_type').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.cell = "'.$LANG->line('cell').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.deleteColumn = "'.$LANG->line('delete_column').'";' . NL
		    . '  jQuery.fn.ffMatrixConf.lang.confirmDeleteColumn = "'.$LANG->line('confirm_delete_column').'";' . NL
		    . NL
		    . '  jQuery.fn.ffMatrixConf.cellTypes = '.json_encode($cell_types).';' . NL
		    . NL
		    . '  jQuery(".ff_matrix_conf").ffMatrixConf('.$this->_fieldtype_id.', '.json_encode($cols).');' . NL
		    . '});';

		$this->insert_js($js);

		// display the config skeleton
		$preview = $DSP->qdiv('defaultBold', $LANG->line('conf_label'))
                 . $DSP->qdiv('itemWrapper', $LANG->line('conf_subtext'))
		         . $DSP->div('ff_matrix ff_matrix_conf')
		         .   '<a class="button add" title="'.$LANG->line('add_column').'"></a>'
		         .   '<table cellspacing="0" cellpadding="0">'
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
	 * Save Field Settings
	 *
	 * Turn the options textarea value into an array of option names and labels
	 * 
	 * @param  array  $settings  The user-submitted settings, pulled from $_POST
	 * @return array  Modified $settings
	 */
	function save_field_settings($field_settings)
	{
		$ftypes = $this->_get_ftypes();

		foreach($field_settings['cols'] as $col_id => &$col)
		{
			$ftype = $ftypes[$col['type']];
			if (method_exists($ftype, 'save_cell_settings'))
			{
				$col['settings'] = $ftype->save_cell_settings($col['settings']);
			}
		}

		return $field_settings;
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
		global $DSP, $REGX;

		$ftypes = $this->_get_ftypes();

		$this->include_css('styles/ff_matrix.css');
		$this->include_js('scripts/jquery.ff_matrix.js');

		$cell_defaults = array();
		$r = '<div class="ff_matrix" id="'.$field_name.'">'
		   .   '<table cellspacing="0" cellpadding="0">'
		   .     '<tr class="tableHeading">';
		foreach($field_settings['cols'] as $col_id => $col)
		{
			// add the header
			$r .=  '<th>'.$col['label'].'</th>';

			// get the default state
			$ftype = $ftypes[$col['type']];
			$cell_settings = array_merge(
				(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
				(isset($col['settings']) ? $col['settings'] : array())
			);
			$cell_defaults[] = $ftype->display_cell('', '', $cell_settings);
		}
		$r .=    '</tr>';

		$field_data = $field_data ? $REGX->array_stripslashes(unserialize($field_data)) : array();
		if ( ! isset($field_data['data']))
		{
			$field_data['data'] = array('1' => array());
		}

		$row_count = 1;
		$num_cols = count($field_settings['cols']);
		foreach($field_data['data'] as $row_id => $row)
		{
			$r .= '<tr>';
			$col_count = 1;
			foreach($field_settings['cols'] as $col_id => $col)
			{
				$ftype = $ftypes[$col['type']];
				$cell_name = $field_name.'['.$row_id.']['.$col_id.']';
				$cell_settings = array_merge(
					(isset($ftype->default_cell_settings) ? $ftype->default_cell_settings : array()),
					(isset($col['settings']) ? $col['settings'] : array())
				);
				$cell_data = isset($row[$col_id]) ? $row[$col_id] : '';
				$class = ($row_count % 2) ? 'tableCellOne' : 'tableCellTwo';
				$r .= '<td class="'.$class.'">'
				    . ($col_count == 1 ? '<a class="button sort" title="Sort row"></a>' : '')
				    . $ftype->display_cell($cell_name, $cell_data, $cell_settings)
				    . '</td>';
				$col_count++;
			}
			$r .= '</tr>';
			$row_count++;
		}

		$r .=   '</table>'
		    . '</div>';

		$js = 'jQuery(window).bind("load", function() {' . NL
		    . '  jQuery("#'.$field_name.'").ffMatrix("'.$field_name.'", '.json_encode($cell_defaults).');' . NL
		    . '});';

		$this->insert_js($js);

		return $r;
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