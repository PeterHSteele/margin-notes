jQuery(document).ready(function($){

		/*Definitions*/

		/* Vars */
		let { width_value,
			  width_unit, 
			  direction, 
			  primary_color, 
			  secondary_color, 
			  tertiary_color, 
			  note_background_color, 
			  container, 
			  post,
			  display_type,
			  content,
			  annotations,
			  delete_url
			} = settings;

		/* Functions */

		/*
		alignAnnotations

		Positions annotations next to source text in the content
		*/

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

		/*
		Add Annotations

		In 'margin display' mode, prepends the string containing the html for the annotations 
		to the container chosen by the user.
		*/

		function addAnnotations( element, string ){
			element.prepend(string);
		}	

		/*
		findAnnotationFromHighlight

		Given a highlighted piece of text, retrieves the corresponding annotation
		
		@param jQuery Object 	tag 	the <span> containing the source text for the annotation we're trying to find
		*/
		
		function findAnnotationFromHighlight( tag ){

			let regex = /\d+/;
			let class_attr = $(tag).attr('class');
			let annotation_number = class_attr.match(regex);
			return '.annotation.annotation-'+annotation_number;

		}

		/*
		toggleColors

		Changes the color of an annotation when the user hovers over its source text.

		@param jQuery Object 	element 	the <span> containing the source text
		@param String 			background 	the color to change the annotations' background to on hover
		@param String 			text		the color to change the annotations' text to on hover
		@param String 			accent 		the color to change the annotations' border to on hover
		*/	

		const toggleColors = function ( element, background, text, accent) {
			
			let query = findAnnotationFromHighlight( element );
				
			$(query).css({
				'background':background,
				'color':text,
				'border-left':'3px solid '+accent
			});
		}

		
		function getAnnotations(){
				$.post(settings.ajaxURL,{
					_ajax_nonce:settings.security,
					action:'handle_annotations',
					post:settings.post
				},
				setupAnnotations
				);
		}
		
		const setupAnnotations = function( data ){
			addAnnotations( $(container), data );
			alignAnnotations();
			updateStorage( data );
		}

		const updateStorage = function( annotations ){
			localStorage.setItem( post, annotations );
		}

		const getStorage = function(){
			return localStorage.getItem( post );
		}

		/* Statements */

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
				$('#margin-notes-wrapper')/*.css( direction, offset)*/.removeClass('expand');

			} else {

				$('svg',this).children('.vertical').remove();

				offset = -1*width_value+width_unit;
				$('#margin-notes-wrapper')/*.css( direction, 0)*/.addClass('expand');

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

	/* Statements ONLY for margin display*/

	/* 
	When navigating using a link or the address bar, the server will provide a new html string of annotations.
	If using forward or back buttons, we have to load them from localStorage.
	*/	
	if ( display_type == 'margins' ){
		if ( window.performance && window.performance.navigation.type == window.performance.navigation.TYPE_BACK_FORWARD ){
			let data = getStorage();
			addAnnotations( $(container), data );
			alignAnnotations( data );
		} else {
			getAnnotations()
		}

	}

	/* Statements ONLY for tooltip display*/
		
	//else, i.e. if annotations should be displayed as tooltips
	if (display_type == 'tooltips' ) {

		//find line breaks so that tooltips can display in correct location on highlights that span multiple lines
		$( '.mn-highlight' ).each(function(){
		    let wrap, text, y, x, width, pwidth, parentY, parentX, ppx, ppy, height, $this, offset;
		    text = $(this).text();
		    //alert(text.length);
		    wrap = '';
		    for (let i=0; i < text.length; i++ ){
		      wrap += '<span class="mn-wrap">'+text.slice(i,i+1)+'</span>';
		    }

		    $(this).html(wrap);
		    parentY = $('.mn-wrap',$(this)).eq(0).offset().top;
		    let string = '<span class="mn-line" data-left="0">'
		    $('.mn-wrap').each(function(){
		      
		      let char = $(this).text();
		     
		      offset = $(this).offset().top;
		      
		      if ( offset > parentY ){
		         string+='</span><span class="mn-line" data-left="0">'+char;
		        parentY=offset;
		      } else{
		        string += char;
		      }
		    })
		   
		   $(this).html(string);
	 });

	
	// mousenter event for placing tooltip when mouse hovers over highlight

	 $('.mn-line').mouseenter( function(e){
	  let text, height, width, annoWidth, hiliteEdge, annoEdge, arrowTip, parent, parentClass, id, query_string, delete_link;

	  //add annotation to tooltip
	  parent = $(this).parent();
	  parentClass = parent.attr('class');
	  id = parentClass.match(/\d+/);
	  text = annotations[id].annotation;

	  $( '.annotation-tooltip p' ).text( text );

	  //update 'delete annotation' link query string with current annotation's info
	  query_string = '&action=delete_annotation&id-to-delete='+id+'&post='+post;
	  delete_link = delete_url + query_string;

	  $( '.mn-delete-annotation' ).attr('href', delete_link);
      
      let { left, top } = $(this).offset();
      height = $(this).height(); 
      width = $(this).width();
      bodyWidth = $('body').width();
      let mX = e.pageX, mY = e.pageY;
      top += height;
      annoWidth = $('.annotation-tooltip').width();
      

      //position tooltip so it does not overflow either side of the page
      hiliteEdge = left + width;
      annoEdge = mX + annoWidth;
      arrowTip = -25;
      /*
      mX = mX < 25 ? 25 : mX;
      mX = mX + annoWidth - 25 > bodyWidth ? bodyWidth - annoWidth + 25 : mX; 
      */
      let hilite_reach = left + width;//right-most point which hilite reaches across the page
      let anno_reach = mX + annoWidth -25 ;//right-most point which annotation reaches across page if positioned on mousenter pageX
      
      $('.annotation-tooltip').removeClass('no-display').offset({
        top: top,
        left: hilite_reach > anno_reach ? mX - 25 : hilite_reach - annoWidth < 0 ? 0 : hilite_reach - annoWidth
      })

     // position triangle 'pointer div' along top edge of tooltip  
      let tri_left;
      if ( hilite_reach > anno_reach ){
      	 //if not near page edge, position slightly offset from left end of annotation
      	 tri_left = 10; 
      } else if ( hilite_reach - annoWidth < 0 ){
      	//if tooltip forced all the way to left edge of page, left offset of triangle = left offset of mouse
      	tri_left = mX - 15 ;
      } else if ( mX + 15 > hilite_reach ){
      	//if tooltip all the way to edge of highlight, AND mouse position also all the way to edge of highlight, 
      	//make sure tri isn't sticking off right edge of tooltip. 30 is width of triangle
      	tri_left = annoWidth - 30;
      } else {
      	//else, place triangle at mouseenter
      	tri_left = annoWidth - ( hilite_reach - mX ) - 15;
      }
      
     $('.annotation-tooltip .tri').css({
      //left: hilite_reach > anno_reach ? 10 :  mX  + 15 < hilite_reach ? annoWidth - hilite_reach + mX  - 15 : annoWidth - 30 
      left: tri_left
      })
    });
	
	 //mouseleave event for when mouse leaves the highlighted section
	 $('.mn-line').on('mouseleave', function(e){
	      $('.annotation-tooltip').addClass('no-display');
	  });


	  $('.annotation-tooltip').mouseenter(function(){
	    $(this).removeClass('no-display');
	  })
	  
	  $('.annotation-tooltip').mouseleave(function(){
	    $(this).addClass('no-display');
	  })
	}
})