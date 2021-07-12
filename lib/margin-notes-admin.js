jQuery(document).ready(function($){

	let classes = $('.margin_notes_width_value, .margin_notes_width_unit, .margin_notes_container');

	$('.margin_notes_display_type input:radio').on('change',function(){
		if (  $('#margin_notes_display_type_margins').is(':checked') ){
			classes.show();
		} else {
			classes.hide();
		}
	});
});