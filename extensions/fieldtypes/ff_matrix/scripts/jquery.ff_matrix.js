(function($){


$.fn.ffMatrix = function(field_name, cellDefaults) {
	return this.each(function() {

		// Initialize obj
		var obj = {
			dom: { $container: $(this) }
		};

		obj.dom.$table = obj.dom.$container.find('table');

		var addDeleteButton = function($tr) {
			$tr.find('td:last-child').prepend(
				$('<a class="button delete">').attr('title', 'Delete row')
					.click(function() {
						$tr.remove();
					})
			);
		}

		// add deletes
		obj.dom.$table.find('tr:not(.tableHeading)').each(function() {
			addDeleteButton($(this));
		});

		var resetCellClasses = function() {
			obj.dom.$table.find('tr:odd td').attr('className', 'tableCellOne');
			obj.dom.$table.find('tr:even td').attr('className', 'tableCellTwo');
		};

		obj.dom.$table.sortable({
			items: 'tr:not(.tableHeading)',
			axis: 'y',
			handle: '.sort',
			change: function(event, ui) {
				resetCellClasses();
				ui.helper.find('td').attr('className', ui.item.find('td').attr('className'));
			}
		});

		obj.dom.$add = $('<a class="button add row">')
			.appendTo(obj.dom.$container)
			.html('Add row')
			.click(function() {
				$tr = $('<tr>').appendTo(obj.dom.$table);
				$.each(cellDefaults, function(cellIndex) {
					$td = $('<td>').appendTo($tr).html(cellDefaults[cellIndex]);
					if (cellIndex == 0) $td.prepend($('<a class="button sort">').attr('title', 'Sort row'));
				})
				addDeleteButton($tr);
				resetCellClasses();
			});

	});
};


})(jQuery);