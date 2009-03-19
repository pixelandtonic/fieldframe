(function($){


$.fn.ffMatrix = function(fieldName, cellDefaults) {
	return this.each(function() {

		// Initialize obj
		var obj = {
			fieldName: fieldName,
			dom: { $container: $(this) }
		};

		obj.dom.$table = obj.dom.$container.find('table');

		var addButtons = function($tr) {
			$tr.find('td:first-child').prepend(
				$('<a class="button sort">').attr('title', 'Sort row')
			);
			$tr.find('td:last-child').prepend(
				$('<a class="button delete">').attr('title', 'Delete row')
					.click(function() {
						if (confirm('Delete this row?')) {
							$tr.remove();
						}
					})
			);
		}

		// add deletes
		obj.dom.$table.find('tr:not(.tableHeading)').each(function() {
			addButtons($(this));
		});

		var resetRows = function() {
			obj.dom.$table.find('tr:not(.tableHeading)').each(function(rowIndex) {
				$(this).find('td').each(function(cellIndex) {
					$(this)
						.attr('className', rowIndex % 2 ? 'tableCellTwo' : 'tableCellOne')
						.find('*[name]').each(function() {
							this.name = obj.fieldName+'['+rowIndex+']' + this.name.substring(this.name.indexOf(']')+1);
						});
				});
			});
		};

		obj.dom.$table.sortable({
			items: 'tr:not(.tableHeading)',
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
			.html('Add row')
			.click(function() {
				$tr = $('<tr>').appendTo(obj.dom.$table);
				$.each(cellDefaults, function(cellIndex) {
					$td = $('<td>').appendTo($tr).html(cellDefaults[cellIndex]);
				});
				addButtons($tr);
				resetRows();
			});

	});
};


})(jQuery);