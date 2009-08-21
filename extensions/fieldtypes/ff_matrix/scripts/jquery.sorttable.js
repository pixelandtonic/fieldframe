(function($){


$.fn.sorttable = function(options) {	
	var options = $.extend($.fn.sorttable.defaults, options);

	return this.each(function() {

		var $table = $(this);

		if (options.axis == 'x') {
			var rows = $table.attr('rows'),
			    numRows = rows.length,
			    cells;

			$(rows[0]).sortable($.extend(options, {
				helper: function(event, item) {
					var $helper = $('<table>')
						.attr({ 'class':$table.attr('className'), cellspacing:0, cellpadding:0 })
						.width(item.width()+24)
						.appendTo($table.parent())

					for (r = 0; r < numRows; r++) {
						$helper.append($('<tr>').attr('class', rows[r].className)
							.append($(rows[r].cells[item.attr('cellIndex')]).clone(true))
						);
					}
					return $helper;
				},
				start: function(event, ui) {
					ui.item.attr('rowspan', numRows).width(ui.helper.width()-24);
					cells = [];
					for (r = 1; r < numRows; r++) {
						cells.push($(rows[r].cells[ui.item.attr('cellIndex')]).remove());
					}
				},
				stop: function(event, ui) {
					ui.item.attr('rowspan', 1).width('');
					var cellIndex = ui.item.attr('cellIndex');
					for (r = 0; r < cells.length; r++) {
						if (cellIndex == 0) $(rows[r+1]).prepend(cells[r]);
						else cells[r].insertAfter($(rows[r+1].cells[cellIndex-1]));
					}
				}
			}));
		}
		else {
			$table.sortable($.extend(options, {
				items: 'tr:not(.tableHeading)'
			}));
		}

	});
};


$.fn.sorttable.defaults = {
	axis: 'y',
	opacity: .9
}


})(jQuery);
