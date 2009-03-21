(function($){


$.fn.ffMatrix = {
	onDisplayCell: {}
};


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

		obj.dom.$table = obj.dom.$container.find('table');
		obj.dom.$trHeaders = obj.dom.$container.find('tr.tableHeading');
		obj.dom.$trPreviews = obj.dom.$container.find('tr.preview');
		obj.dom.$trColConf = obj.dom.$container.find('tr.conf.col');
		obj.dom.$trCellType = obj.dom.$container.find('tr.conf.celltype');
		obj.dom.$trCellSettings = obj.dom.$container.find('tr.conf.cellsettings');
		obj.dom.$trDeletes = obj.dom.$container.find('tr.delete');

		var toggleCellSettings = function() {
			var showCellSettings = false;
			$.each(obj.cols, function(colId) {
				if ($.fn.ffMatrixConf.cellTypes[this.type].settings)
				{
					showCellSettings = true;
					return false;
				}
			});
			if (showCellSettings) obj.dom.$trCellSettings.show();
			else obj.dom.$trCellSettings.hide();
		}

		var addCol = function(colId, col) {
			if (colId >= obj.nextColId) obj.nextColId = colId + 1;

			var cellType = $.fn.ffMatrixConf.cellTypes[col.type];

			col.$header = $('<th id="ffMatrixCol'+colId+'">')
				.appendTo(obj.dom.$trHeaders);
			col.$headerText = $('<span>').html(col.label).appendTo(col.$header);

			col.$preview = $('<td>').html(col.preview)
				.appendTo(obj.dom.$trPreviews);
			if ($.fn.ffMatrix.onDisplayCell[col.type]) {
				$.fn.ffMatrix.onDisplayCell[col.type](col.$preview);
			}

			col.$colConf = $('<td>').html(
				  '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.colName+'</div>'
				+   '<input type="text" name="'+obj.namespace+'[cols]['+colId+'][name]" value="'+col.name+'" style="font-family:monospace;" />'
				+ '</label>'
				+ '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.colLabel+'</div>'
				+   '<input type="text" class="label" name="'+obj.namespace+'[cols]['+colId+'][label]" value="'+col.label+'" />'
				+ '</label>')
				.appendTo(obj.dom.$trColConf);
			col.$labelInput = col.$colConf.find('input.label')
				.keydown(function(event) {
					setTimeout(function() {
						col.$headerText.html(col.$labelInput.val());
					}, 1);
				});

			var select = '';
			$.each($.fn.ffMatrixConf.cellTypes, function(className) {
				select += '<option value="'+className+'"'
				        + (className == col.type ? ' selected="selected"' : '')
				        + '>'+this.name+'</option>';
			});
			col.$cellType = $('<td>').html(
				  '<label class="itemWrapper">'
				+   '<div class="defaultBold">'+$.fn.ffMatrixConf.lang.cellType+'</div>'
				+   '<select name="'+obj.namespace+'[cols]['+colId+'][type]">'
				+     select
				+   '</select>'
				+ '</label>'
				)
				.appendTo(obj.dom.$trCellType);

			col.$cellSettings = $('<td>').html(groupCellSettings(obj, col.settings, colId))
				.appendTo(obj.dom.$trCellSettings)

			col.$delete = $('<td>').appendTo(obj.dom.$trDeletes);
			col.$deleteBtn = $('<a class="button delete">')
				.appendTo(col.$delete)
				.attr('title', $.fn.ffMatrixConf.lang.deleteColumn)
				.click(function() {
					if (confirm($.fn.ffMatrixConf.lang.confirmDeleteColumn)) {
						col.$header.remove();
						col.$preview.remove();
						col.$colConf.remove();
						col.$cellType.remove();
						col.$cellSettings.remove();
						col.$delete.remove();
						delete(obj.cols[colId]);
						console.log(obj.cols);
						toggleCellSettings();
					}
				});

			col.$typeSettings = col.$cellType.find('.settings');
			col.$typeSelect = col.$cellType.find('select')
				.change(function() {
					col.type = this.value;
					var cellType = $.fn.ffMatrixConf.cellTypes[col.type];
					col.$preview.html(cellType.preview);
					if ($.fn.ffMatrix.onDisplayCell[col.type]) {
						$.fn.ffMatrix.onDisplayCell[col.type](col.$preview);
					}
					col.$cellSettings.html(groupCellSettings(obj, cellType.settings, colId));
					toggleCellSettings();
				});

			obj.cols[colId] = col;
		}

		$.each(cols, function(colId) {
			addCol(parseInt(colId), this);
		});
		toggleCellSettings();

		obj.dom.$table.sorttable({ axis: 'x' });

		// add new column
		obj.dom.$add.click(function() {
			var cellNum = obj.dom.$trHeaders.attr('cells').length + 1,
				cellType = $.fn.ffMatrixConf.options.initialCellType;
			addCol(obj.nextColId, {
				label:    $.fn.ffMatrixConf.lang.cell+' '+cellNum,
				name:     $.fn.ffMatrixConf.lang.cell.toLowerCase().replace(' ', '_')+'_'+cellNum,
				type:     cellType,
				preview:  $.fn.ffMatrixConf.cellTypes[cellType].preview,
				settings: $.fn.ffMatrixConf.cellTypes[cellType].settings
			});
			toggleCellSettings();
		});
	});
};


// Language
$.fn.ffMatrixConf.lang = {
	colName: 'Col Name',
	colLabel: 'Col Label',
	cellType: 'Cell Type',
	cell: 'Cell',
	deleteColumn: 'Delete Column',
	confirmDeleteColumn: 'Delete this column?'
};


// Cell Types
$.fn.ffMatrixConf.cellTypes = { };

// Options
$.fn.ffMatrixConf.options = {
	initialCellType: 'ff_matrix_text'
};


function groupCellSettings(obj, cellSettings, colId) {
	return cellSettings.replace(/(name=['"])([^'"\[\]]+)([^'"]*)(['"])/i, '$1'+obj.namespace+'[cols]['+colId+'][settings][$2]$3$4');
}


})(jQuery);