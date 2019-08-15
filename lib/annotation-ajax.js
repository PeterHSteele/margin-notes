jQuery(document).ready(function($){
	//console.log('nonce',ajax_obj.security);
	/*var _nonce = "<?php echo wp_create_nonce( 'wp_rest' ); ?>";

	var request = $.ajax({
	    type: 'POST',
	    url: ajax_obj.ajaxURL,
	    data: {
	       action:'handle_annotations'
	    },
	    dataType: 'json',
	    beforeSend: function ( xhr ) {
	        xhr.setRequestHeader( 'X-WP-Nonce', _nonce );
	    }
	});

	request.done(function(response){
		alert(response);
	});*/
	//console.log('anything');
	
	$.post(ajax_obj.ajaxURL,{
		_ajax_nonce:ajax_obj.security,
		action:'handle_annotations',
	},
	function(data){
		//console.log(data);
		
		primary_color = data.primary_color;
		secondary_color = data.secondary_color;

	});
});