$(function() {
	$('#sortable').sortable({
		axis: 'y',
		opacity: 0.7,
		handle: '#move',
		containment: 'table.kb-index-table',
		update: function(event, ui) {
			let list_sortable = $(this).sortable('toArray').toString();
			let page = $('#page').text();
			let reorder_data = $('#kb-reorder-data');
			// change order in the database using Ajax
			$.ajax({
				url: 'set_order',
				type: 'POST',
				data: {
					list_order: list_sortable,
					page: page,
					cat_id: reorder_data.data('cat-id'),
					creation_time: reorder_data.find('input[name="creation_time"]').val(),
					form_token: reorder_data.find('input[name="form_token"]').val()
				},
				success: function(data) {
					//finished
				}
			});
		}
	}); // fin sortable
});
