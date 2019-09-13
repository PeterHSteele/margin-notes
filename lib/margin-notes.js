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
		

				$('body').on('click',function(e){
					//console.log(e.target);
				})
				/*
				$('body').on('click','.annotation button',function(e){
							//console.log('click')
							let idAttr, id, post;
							
							idAttr = $(this).attr('id');
							id = idAttr.match(/\d+/)[0];
							post = idAttr.match(/[^(?:delete)-](?:\w+-)+/)[0];
							post = post.slice(0,post.length-1);
							
							data = {
								secure_delete:settings.secure_delete,
								action:'delete_annotation',
								id:id,
								post:post
							}

							$.post(settings.ajaxURL,data,function(response){
								console.log(response);
							});

				})
				*/

		

		function findAnnotationFromHighlight( tag ){

			let regex = /\d+/;
			let class_attr = $(tag).attr('class');
			let annotation_number = class_attr.match(regex);
			return '.annotation.annotation-'+annotation_number;

		}

		function findHighlightFromAnnotation( tag ){

			let regex = /\d+/;
			let class_attr = $(tag).attr('class');
			let highlight_number = class_attr.match(regex);
			return '.mn-highlight.annotation-'+highlight_number;

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
			console.log(note_background_color);
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
				 //console.log('direction: '+direction+' width: '+width);
				offset = -1*width_value + width_unit;

				$(this).children('svg').append( el );
				$('#margin-notes-wrapper').css( direction, offset).removeClass('expand');

			} else {

				$('svg',this).children('.vertical').remove();

				offset = -1*width_value+width_unit;
				$('#margin-notes-wrapper').css( direction, 0).addClass('expand');

			}
		});	

		//Maybe intercept submission of form if source is not in content
		
		$('#margin-notes-submit').click(function(e){
			//e.preventDefault();
			//return false;
			//const url = 
			let highlight = $('#highlight-input').val(),
				annotation = $('#annotation-input').val(),
				postName = $('#post-name').val(),
				thoughts = $('#thoughts-on-article').val(),
				deleteAll = $('#deleteAll').val(),
				//text = $(content).text(),
				match,
				regex;
			
			if ( ! highlight.length ){
				$('#highlight-error').text('Please provide some source text.');
				return false;
			}

			regex = new RegExp( highlight );
			match = content.search( regex );
			//console.log(match);
			if ( match === -1){
				$('#highlight-error').text('We couldn\'t find this text in the document.');
				return false;
			} 
			/*
			const data = {
				highlight,
				annotation,
				postname:postName,
				thoughtsonarticle:thoughts,
				ddelete:deleteAll,
				action:'annotation'
			}

			const options = {
				method:'POST',
				headers:{
					'Content-Type':'application/json'
				},
				body:JSON.stringify(data)
			}


			fetch( action, options)
				.then(function(response){console.log(response);return response.json()})
				.then(json=>console.log(json))
				.catch(error=>console.log('there has been problem: '+error))
			*/
		})
		

	if ( display_type === 'margins' ) {	
		
		$.post(settings.ajaxURL,{
			_ajax_nonce:settings.security,
			action:'handle_annotations',
			post:settings.post
		},
		function(data){
			//console.log(data);
			function alignAnnotations(){

				if (! $(".mn-highlight").length ) {
					return;
				}

				let currentOffset = 0, 
					//contentContainer = $('.highlight:eq(0)').parents().eq(2),
					contentContainer = $(container);
					containerHeight = contentContainer.height(),
					topMargins = [];
					
				function findOffsetTop( element ){
					//console.log(element.offset().top, contentContainer.offset().top);
					return element.offset().top - contentContainer.offset().top;
					//return element.offset().top;
					/*let annotationClass = element.attr('class').match(/annotation-\d/)[0];
					return document.getElementsByClassName( annotationClass )[1].offsetTop;*/
				}

				$('.mn-highlight').each(function(){
					//console.log('co',currentOffset);
					let offsetTop, annotation, heightOfAnnotation, distance;
					offsetTop = findOffsetTop( $(this) );
					annotation = findAnnotationFromHighlight( $(this) );
					heightOfAnnotation = $(annotation).height();
					/*if (annotation.match(/1/) || annotation.match(/2/)){
						console.log($(this).attr('class')+' offsetTop: '+findOffsetTop($(this))+" currentOffset: "+currentOffset);
					}*/
					distance = offsetTop - currentOffset;
					//console.log(distance)
					if ( distance < 0 ){
						offsetTop -= distance;
						distance = 0;
					}
					topMargins.push( distance );
					$(annotation).css('margin-top',distance+'px');
					
					if ( offsetTop + heightOfAnnotation > containerHeight ) {
						//console.log('conditional','containerHeight: '+containerHeight+" offset: "+offsetTop+" anno height: "+heightOfAnnotation);
						let annotationToShift, margin;
						for (let i = topMargins.length-1; i >= 0 ; i-- ){
							if ( topMargins[i] > heightOfAnnotation){
								//console.log('margin to decrease', topMargins[i],i);
								topMargins[i] -= heightOfAnnotation;
								//console.log('after',topMargins[i]);
								annotationToShift = $('.annotation').eq(i);
								annotationToShift.css('margin-top', topMargins[i]);
								break;
							}
						}
					}
					
					currentOffset = offsetTop + heightOfAnnotation;


					
				})

		/*
				$('.mn-highlight').each(function(){
					//console.log('co',currentOffset);
					let offsetTop, annotation, heightOfAnnotation, distance;
					offsetTop = findOffsetTop( $(this) );
					annotation = findAnnotationFromHighlight( $(this) );
					heightOfAnnotation = $(annotation).height();
					if (annotation.match(/1/) || annotation.match(/2/)){
						console.log($(this).attr('class')+' offsetTop: '+findOffsetTop($(this))+" currentOffset: "+currentOffset);
					}
					
					console.log('containerHeight: '+containerHeight+" offset: "+offsetTop+" anno height: "+heightOfAnnotation);
					if ( offsetTop + heightOfAnnotation < currentOffset){
						offsetTop = currentOffset;
					}
					/*if ( offsetTop + heightOfAnnotation > containerHeight ) {
						console.log('conditional',topMargins+" "+heightOfAnnotation);
						let annotationToShift, margin;
						for (let i = topMargins.length-1; i >= 0 ; i-- ){
							if ( topMargins[i] > heightOfAnnotation){

								console.log('margin to decrease', topMargins[i],i);
								topMargins[i] -= heightOfAnnotation;
								console.log('after',topMargins[i]);
								annotationToShift = $('.annotation').eq(i);
								annotationToShift.css('margin-top', topMargins[i]);
								break;
							}
						}
					}
					$(annotation).offset({top:offsetTop,left:'70%'});
					//topMargins.push( distance );
					currentOffset = offsetTop + heightOfAnnotation;
				})
		*/
			}

			function addAnnotations( element, string ){
				element.prepend(string);
			}
		
			addAnnotations( $(container), data );

			alignAnnotations();

		});

	}	
})