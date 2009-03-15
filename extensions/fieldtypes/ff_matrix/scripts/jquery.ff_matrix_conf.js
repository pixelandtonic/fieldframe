(function($){


$.fn.ffMatrixConf = function(id, cols) {
	return this.each(function() {

		// Initialize obj
		var obj = {
			id: id,
			namespace: 'ftype[ftype_id_'+id+']',
			dom: { $container: $(this) },
			nextColId: 0,
			cols: { }
		};

		obj.dom.$add = obj.dom.$container.find('a.add');

		obj.dom.$trHeaders = obj.dom.$container.find('tr.tableHeading');
		obj.dom.$trPreviews = obj.dom.$container.find('tr.preview');
		obj.dom.$trColConf = obj.dom.$container.find('tr.conf.col');
		obj.dom.$trCellConf = obj.dom.$container.find('tr.conf.cell');

		var addCol = function(colId, col) {
			if (colId >= obj.nextColId) obj.nextColId = colId + 1;

			col.$header = $('<th>').html(col.label)
				.appendTo(obj.dom.$trHeaders);

			col.$preview = $('<td>').html($.fn.ffMatrixConf.cellTypes[col.type].preview)
				.appendTo(obj.dom.$trPreviews);

			col.$colConf = $('<td>').html(
				  '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.colName+'</div>'
				+   '<input type="text" name="'+obj.namespace+'[cols]['+colId+'][name]" value="'+col.name+'" style="font-family:monospace;" />'
				+ '</label>'
				+ '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.colLabel+'</div>'
				+   '<input type="text" name="'+obj.namespace+'[cols]['+colId+'][label]" value="'+col.label+'" />'
				+ '</label>')
				.appendTo(obj.dom.$trColConf);

			var typeSelect = '';
			$.each($.fn.ffMatrixConf.cellTypes, function(className) {
				typeSelect += '<option value="'+className+'"'
				            + (className == col.type ? ' selected="selected"' : '')
				            + '>'+this.name+'</option>';
			});
			col.$cellConf = $('<td>').html(
				  '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.cellType+'</div>'
				+   '<select name="'+obj.namespace+'[cols]['+colId+'][type]">'
				+     typeSelect
				+   '</select>'
				+ '</label>'
				)
				.appendTo(obj.dom.$trCellConf);

			col.$typeSelect = col.$cellConf.find('select').change(function() {
				col.type = this.value;
				col.$preview.html($.fn.ffMatrixConf.cellTypes[col.type].preview);
			});

			obj.cols[colId] = col;
		}

		$.each(cols, function(colId) {
			addCol(colId, this);
		});

		// add new column
		obj.dom.$add.click(function() {
			var cellNum = obj.dom.$trHeaders.attr('cells').length + 1;
			addCol(obj.nextColId, {
				label: $.fn.ffMatrixConf.lang.cell+' '+cellNum,
				name:  $.fn.ffMatrixConf.lang.cell.toLowerCase().replace(' ', '_')+'_'+cellNum,
				type:  $.fn.ffMatrixConf.options.initialCellType
			});
		});
	});
};


// Language
$.fn.ffMatrixConf.lang = {
	'colName':  'Col Name',
	'colLabel': 'Col Label',
	'cellType': 'Cell Type',
	'cell':     'Cell'
};


// Cell Types
$.fn.ffMatrixConf.cellTypes = { };

// Options
$.fn.ffMatrixConf.options = {
	initialCellType: 'ff_matrix_text'
};


})(jQuery);