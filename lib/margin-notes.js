jQuery(document).ready(function($){

	let { primary_color, secondary_color, tertiary_color, note_background_color } = colors;

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

	$('.highlight').on('mouseenter', function(){
		toggleColors( $(this) , tertiary_color, note_background_color, tertiary_color );
	})

	$('.highlight').on('mouseleave',function(){
		toggleColors( $(this), note_background_color, tertiary_color, primary_color );
	})
	
	$('.margin-notes-add').on('click',function(){

		if ( $('#margin-notes-form').hasClass('expand') ) {
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

			$(this).children('svg').append( el );
			$('#margin-notes-form').removeClass( 'expand' );

		} else {

			$('svg',this).children('.vertical').remove();

			$('#margin-notes-form').addClass('expand');

		}
	});

	function alignAnnotations(){

		if (! $(".highlight").length ) {
			return;
		}

		let currentOffset = 0, 
			contentContainer = $('.highlight:eq(0)').parents().eq(2),
			containerHeight = contentContainer.height(),
			topMargins = [];
			
		function findOffsetTop( element ){
			console.log(element.offset().top, element.parents().eq(1).offset().top);
			return element.offset().top - element.parents().eq(1).offset().top;
			//return element.offset().top;
			/*let annotationClass = element.attr('class').match(/annotation-\d/)[0];
			return document.getElementsByClassName( annotationClass )[1].offsetTop;*/
		}

		$('.highlight').each(function(){
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
			//console.log('containerHeight: '+containerHeight+" offset: "+offsetTop);
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
		$('.highlight').each(function(){
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

	

	alignAnnotations();
	
})



	

