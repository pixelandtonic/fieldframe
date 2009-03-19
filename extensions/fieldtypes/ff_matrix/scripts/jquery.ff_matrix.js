(function($){


$.fn.ffMatrix = function(field_name, cellDefaults) {
	return this.each(function() {

		// Initialize obj
		var obj = {
			dom: { $container: $(this) }
		};

		obj.dom.$table = obj.dom.$container.find('table')
			.sorTable();

		var resetCellClasses = function() {
			obj.dom.$table.find('tr:odd td').addClass('tableCellOne');
			obj.dom.$table.find('tr:even td').addClass('tableCellTwo');
		};

		obj.dom.$add = $('<a class="button add row">')
			.appendTo(obj.dom.$container)
			.html('Add row')
			.click(function() {
				$tr = $('<tr>').appendTo(obj.dom.$table);
				$.each(cellDefaults, function(cellIndex) {
					$td = $('<td>').appendTo($tr);
					$td.html(cellDefaults[cellIndex]);
				});
				resetCellClasses();
			});

	});
};


})(jQuery);