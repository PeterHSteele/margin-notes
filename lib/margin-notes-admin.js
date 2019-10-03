jQuery(document).ready(function($){

	let classes = $('.width_value, .width_unit, .container, .container_type');

	$('input:radio').on('change',function(){
		if (  $('#margin_notes_display_type_margins').is(':checked') ){
			classes.show();
		} else {
			classes.hide();
		}
		
	});

});