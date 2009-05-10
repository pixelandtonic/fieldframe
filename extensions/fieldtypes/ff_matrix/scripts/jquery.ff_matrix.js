(function($){


$.fn.ffMatrix = function(fieldName, cellDefaults) {
	return this.each(function() {

		// Initialize obj
		var obj = {
			fieldName: fieldName,
			dom: { $container: $(this) }
		};

		obj.dom.$table = obj.dom.$container.find('table:first');

		var addButtons = function($tr) {
			$('<a>').appendTo($('> td:eq(0)', $tr))
				.addClass('button sort')
				.attr('title', $.fn.ffMatrix.lang.sortRow);

			$('<a>').appendTo($('> td:last-child', $tr))
				.addClass('button delete')
				.attr('title', $.fn.ffMatrix.lang.deleteRow)
				.click(function() {
					if (confirm($.fn.ffMatrix.lang.confirmDeleteRow)) {
						$tr.remove();
						resetRows();
					}
				});
		};

		// add deletes
		obj.dom.$table.find('tbody:first > tr:not(.head)').each(function() {
			addButtons($(this));
		});

		var resetRows = function() {
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
		};

		for (var cellType in $.fn.ffMatrix.onDisplayCell) {
			obj.dom.$table.find('td.'+cellType).each(function() {
				$.fn.ffMatrix.onDisplayCell[cellType]($(this));
			});
		}

		if ($.fn.ffMatrix.useTableDnD) {
			obj.dom.$table.tableDnD({
				onAllowDrop: function(drag, drop) {
					// don't allow dragging over the heading row
					return (drop.rowIndex > 0) ? true : false;
				},
				dragHandle: 'tableDnD-sort',
				onDrop: function(table, row) {
					resetRows();
				}
			});
		}
		else {
			obj.dom.$table.sortable({
				items: 'tbody:first > tr:not(.head)',
				axis: 'y',
				handle: '.sort',
				opacity: 0.8,
				change: function(event, ui) {
					resetRows();
					if (ui.item.rowIndex % 2) ui.helper.find('.td').removeClass('tableCellOne').addClass('tableCellTwo');
					else ui.helper.find('.td').removeClass('tableCellTwo').addClass('tableCellOne');
				},
				stop: function(event, ui) {
					resetRows();
				}
			});
		}

		obj.dom.$add = $('<a>')
			.appendTo(obj.dom.$container)
			.addClass('button add row')
			.html($.fn.ffMatrix.lang.addRow)
			.click(function() {
				$tr = $('<tr>').appendTo(obj.dom.$table);
				$('<td>').appendTo($tr)
					.addClass('gutter tableDnD-sort');
				$.each(cellDefaults, function(i) {
					var c = ((i == 0) ? ' first' : ((i == cellDefaults.length-1) ? ' last' : ''));
					$td = $('<td class="'+this.type+' td'+c+'">').appendTo($tr).html(this.cell);
					if ($.fn.ffMatrix.onDisplayCell[this.type]) {
						$.fn.ffMatrix.onDisplayCell[this.type]($td);
					}
				});
				$('<td>').appendTo($tr)
					.addClass('gutter');
				resetRows();
				addButtons($tr);
			});

	});
};


$.fn.ffMatrix.useTableDnD = false;


// Language
$.fn.ffMatrix.lang = {};


$.fn.ffMatrix.onDisplayCell = {};


})(jQuery);