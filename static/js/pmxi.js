/**
 * Import Anything plugin javascript
 */
(function($){$(function () {
		
	$('#dismiss').click(function(){

		$(this).parents('div.updated:first').slideUp();
		$.post('admin.php?page=pmxi-admin-settings&action=dismiss', {dismiss: true}, function (data) {
			
		}, 'html');
	});
	
});})(jQuery);