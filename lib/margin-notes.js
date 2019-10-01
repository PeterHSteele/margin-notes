jQuery(document).ready(function($){

		let { width_value,
			  width_unit, 
			  direction, 
			  primary_color, 
			  secondary_color, 
			  tertiary_color, 
			  note_background_color, 
			  container, 
			  display_type,
			  content,
			  action,
			} = settings;
		
		function findAnnotationFromHighlight( tag ){

			let regex = /\d+/;
			let class_attr = $(tag).attr('class');
			let annotation_number = class_attr.match(regex);
			return '.annotation.annotation-'+annotation_number;

		}

		let toggleColors = function ( element, background, text, accent) {
			
			let query = findAnnotationFromHighlight( element );
				
			$(query).css({
				'background':background,
				'color':text,
				'border-left':'3px solid '+accent
			});
		}

		//Highlight annotation when you mouseover its source text

		$('.mn-highlight').on('mouseenter', function(){
			toggleColors( $(this) , tertiary_color, note_background_color, tertiary_color );
		})

		$('.mn-highlight').on('mouseleave',function(){
			toggleColors( $(this), note_background_color, tertiary_color, primary_color );
		})

		//Slide out the form when the '+' button is clicked and change '+' to '-'
		
		$('.margin-notes-add').on('click',function(){
			
			let offset;
			if ( $('#margin-notes-wrapper').hasClass('expand') ) {
				 var el= document.createElementNS('http://www.w3.org/2000/svg', 'line');
				 var attrs = {
				 	class:"vertical",
				 	x1:'25',
				 	y1:'15',
				 	x2:'25',
				 	y2:'35',
				 	stroke:secondary_color,
				 	'stroke-width':5,
				 	'stroke-linecap':'round'
				 }
				 for (let prop in attrs){
				 	el.setAttribute( prop , attrs[prop] );
				 }
				 
				offset = -1*width_value + width_unit;

				$(this).children('svg').append( el );
				$('#margin-notes-wrapper').css( direction, offset).removeClass('expand');

			} else {

				$('svg',this).children('.vertical').remove();

				offset = -1*width_value+width_unit;
				$('#margin-notes-wrapper').css( direction, 0).addClass('expand');

			}
		});	

		//Intercept submission of form if source is not in content
		
		$('#margin-notes-submit').click(function(e){

			let highlight = $('#highlight-input').val(),
				annotation = $('#annotation-input').val(),
				postName = $('#post-name').val(),
				thoughts = $('#thoughts-on-article').val(),
				deleteAll = $('#deleteAll').prop('checked'),
				//text = $(content).text(),
				match,
				regex;

				
			
			if ( ! highlight.length && ! deleteAll ){
				
				$('#highlight-error').text('Please provide some source text.');
				return false;
			} 

			//highlight = highlight.replace(/[”“]/g,'"').replace(/[‘’]/g,"'");
			
			regex = new RegExp( highlight );
			match = content.search( regex );
			
			if ( match === -1){
				$('#highlight-error').text('We couldn\'t find this text in the document.');
				return false;
			} 
			
		})
		
	console.log(display_type)
	if ( display_type === 'margins' ) {	
		console.log('margins', settings.post)
		$.post(settings.ajaxURL,{
			_ajax_nonce:settings.security,
			action:'handle_annotations',
			post:settings.post
		},
		function(data){
			console.log('data',data)
			function alignAnnotations(){

				if (! $(".mn-highlight").length ) {
					return;
				}

				let currentOffset = 0, 
					
					contentContainer = $(container);
					containerHeight = contentContainer.height(),
					topMargins = [];
					
				function findOffsetTop( element ){
					return element.offset().top - contentContainer.offset().top;
				}

				$('.mn-highlight').each(function(){
					console.log('mn hih',$(this))
					let offsetTop, annotation, heightOfAnnotation, distance;
					offsetTop = findOffsetTop( $(this) );
					annotation = findAnnotationFromHighlight( $(this) );
					heightOfAnnotation = $(annotation).height();
					
					distance = offsetTop - currentOffset;
					
					if ( distance < 0 ){
						offsetTop -= distance;
						distance = 0;
					}
					topMargins.push( distance );
					$(annotation).css('margin-top',distance+'px');
					
					if ( offsetTop + heightOfAnnotation > containerHeight ) {
						
						let annotationToShift, margin;
						for (let i = topMargins.length-1; i >= 0 ; i-- ){
							if ( topMargins[i] > heightOfAnnotation){
								
								topMargins[i] -= heightOfAnnotation;
								
								annotationToShift = $('.annotation').eq(i);
								annotationToShift.css('margin-top', topMargins[i]);
								break;
							}
						}
					}
					
					currentOffset = offsetTop + heightOfAnnotation;
					
				})
			}

			function addAnnotations( element, string ){
				element.prepend(string);
			}
		
			addAnnotations( $(container), data );

			alignAnnotations();

		});

	}	
})