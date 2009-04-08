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
			$tr.find('> td:eq(0)').prepend(
				$('<a class="button sort">').attr('title', $.fn.ffMatrix.lang.sortRow)
			);
			$tr.find('> td:last-child').prepend(
				$('<a class="button delete">').attr('title', $.fn.ffMatrix.lang.deleteRow)
					.click(function() {
						if (confirm($.fn.ffMatrix.lang.confirmDeleteRow)) {
							$tr.remove();
						}
					})
			);
		}

		// add deletes
		obj.dom.$table.find('tbody:first > tr:not(.tableHeading)').each(function() {
			addButtons($(this));
		});

		var resetRows = function() {
			obj.dom.$table.find('tbody:first > tr:not(.tableHeading)').each(function(rowIndex) {
				$(this).find('td').each(function(cellType) {
					$td = $(this);
					if (rowIndex % 2) $td.removeClass('tableCellOne').addClass('tableCellTwo');
					else $td.removeClass('tableCellTwo').addClass('tableCellOne');
					$td.find('*[name]').each(function() {
						this.name = obj.fieldName+'['+rowIndex+']' + this.name.substring(this.name.indexOf(']')+1);
					});
				});
			});
		};

		for (var cellType in $.fn.ffMatrix.onDisplayCell) {
			obj.dom.$table.find('td.'+cellType).each(function() {
				$.fn.ffMatrix.onDisplayCell[cellType]($(this));
			});
		}

		obj.dom.$table.sortable({
			items: 'tbody:first > tr:not(.tableHeading)',
			axis: 'y',
			handle: '.sort',
			opacity: 0.8,
			change: function(event, ui) {
				resetRows();
				ui.helper.find('td').attr('className', ui.item.find('td').attr('className'));
			}
		});

		obj.dom.$add = $('<a class="button add row">')
			.appendTo(obj.dom.$container)
			.html($.fn.ffMatrix.lang.addRow)
			.click(function() {
				$tr = $('<tr>').appendTo(obj.dom.$table);
				$.each(cellDefaults, function() {
					$td = $('<td class="'+this.type+'">').appendTo($tr).html(this.cell);
					if ($.fn.ffMatrix.onDisplayCell[this.type]) {
						$.fn.ffMatrix.onDisplayCell[this.type]($td);
					}
				});
				addButtons($tr);
				resetRows();
			});

	});
};


// Language
$.fn.ffMatrix.lang = {};


$.fn.ffMatrix.onDisplayCell = {};


})(jQuery);