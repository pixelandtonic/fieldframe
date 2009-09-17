(function($){


$.fn.ffMatrix = function(fieldName, cellDefaults, maxRows) {
	return this.each(function() {

		// initialize obj
		var obj = {
			fieldName:    fieldName,
			cellDefaults: cellDefaults,
			maxRows:      maxRows
		};

		// dom
		obj.dom = { $container: $(this) };
		obj.dom.$table = obj.dom.$container.find('table:first');

		// initialize rows
		obj.dom.$table.find('tbody:first > tr:not(.head)').each(function() {
			var $row = $(this);
			addButtons(obj, $row);
			callback(obj, $row, 'onDisplayCell');
		});

		// sorting
		if ($.fn.ffMatrix.useTableDnD) {
			obj.dom.$table.tableDnD({
				onAllowDrop: function(drag, drop) {
					// don't allow dragging over the heading row
					return (drop.rowIndex > 0) ? true : false;
				},
				dragHandle: 'tableDnD-sort',
				onDrop: function(table, row) {
					resetRows(obj);
				}
			});
		}
		else {
			obj.dom.$table.sortable({
				items: 'tbody:first > tr:not(.head)',
				axis: 'y',
				handle: '.sort',
				opacity: 0.8,
				start: function(event, ui) {
					callback(obj, $(ui.item), 'onBeforeSortRow');
				},
				change: function(event, ui) {
					resetRows(obj);
					if (ui.item.rowIndex % 2) ui.helper.find('.td').removeClass('tableCellOne').addClass('tableCellTwo');
					else ui.helper.find('.td').removeClass('tableCellTwo').addClass('tableCellOne');
				},
				stop: function(event, ui) {
					callback(obj, $(ui.item), 'onSortRow');
					resetRows(obj);
				}
			});
		}

		// Add Row button
		obj.dom.$add = $('<a>')
			.appendTo(obj.dom.$container)
			.addClass('button add row')
			.html($.fn.ffMatrix.lang.addRow)
			.click(function() {
				addRow(obj);
			});

		toggleAddRowBtn(obj);
	});
};


$.extend($.fn.ffMatrix, {
	lang: {},
	useTableDnD: false,

	onDisplayCell: {},
	onBeforeSortRow: {},
	onSortRow: {},
	onDeleteRow: {}
});


function addButtons(obj, $tr) {
	// Sort button
	$('<a>').appendTo($('> td:eq(0)', $tr))
		.addClass('button sort')
		.fadeOut(0)
		.attr('title', $.fn.ffMatrix.lang.sortRow);

	// Delete button
	$('<a>').appendTo($('> td:last-child', $tr))
		.addClass('button delete')
		.fadeOut(0)
		.attr('title', $.fn.ffMatrix.lang.deleteRow)
		.click(function() {
			if (confirm($.fn.ffMatrix.lang.confirmDeleteRow)) {
				callback(obj, $tr, 'onDeleteRow');
				$tr.remove();
				resetRows(obj);
				toggleAddRowBtn(obj);
			}
		});

	$tr.hover(
		function(){
			if (getNumRows(obj) > 1) {
				$('a.button', $tr).fadeIn(100);
			}
		},
		function(){
			$('a.button', $tr).fadeOut(100);
		}
	);
}

function resetRows(obj) {
	obj.dom.$table.find('tbody:first > tr:not(.head)').each(function(rowIndex) {
		$(this).find('.td').each(function(cellType) {
			$td = $(this);
			if (rowIndex % 2) $td.removeClass('tableCellOne').addClass('tableCellTwo');
			else $td.removeClass('tableCellTwo').addClass('tableCellOne');
			$td.find('*[name]').each(function() {
				this.name = obj.fieldName+'['+rowIndex+']' + this.name.substring(this.name.indexOf(']')+1);
			});
		});
	});

	if ($.fn.ffMatrix.useTableDnD) {
		obj.dom.$table.tableDnDUpdate();
	}
}

function addRow(obj) {
	// create the row
	var $tr = $('<tr>').appendTo(obj.dom.$table);

	// Sort button cell
	$('<td>').appendTo($tr)
		.addClass('gutter tableDnD-sort');

	// field cells
	var callbacks = new Array();
	$.each(obj.cellDefaults, function(i) {
		var c = '';
		if (i == 0) c += ' first';
		if (i == obj.cellDefaults.length-1) c += ' last';
		$('<td>').appendTo($tr)
			.addClass('td '+this.type+c)
			.html(this.cell);
	});

	// Delete button cell
	$('<td>').appendTo($tr)
		.addClass('gutter');

	// admin
	resetRows(obj);
	addButtons(obj, $tr);
	toggleAddRowBtn(obj);

	callback(obj, $tr, 'onDisplayCell');
}

function getNumRows(obj) {
	return  obj.dom.$table.find('tbody:first > tr:not(.head)').length;
}

function toggleAddRowBtn(obj) {
	if (obj.maxRows) {
		if (getNumRows(obj) < obj.maxRows) {
			obj.dom.$add.show();
		}
		else {
			obj.dom.$add.hide();
		}
	}
}

function callback(obj, $row, callback) {
	$.each(obj.cellDefaults, function(i) {
		var cellType = this.type;
		if ($.fn.ffMatrix[callback][cellType]) {
			$('> .td.'+cellType, $row).each(function() {
				$.fn.ffMatrix[callback][cellType](this, obj);
			});
		}
	});
}


})(jQuery);
