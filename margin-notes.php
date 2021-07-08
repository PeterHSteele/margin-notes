<?php
/**
 * Plugin Name: Margin Notes
 * Plugin URI: https://github.com/peterhsteele/margin-notes
 * Description: Allows subscribers to annotate articles on your site
 * Author:Peter Steele
 * Author URI: https://github.com/peterhsteele
 * Version:1.0.0
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       margin-notes
 * Domain Path:       /languages
 *
 * Margin Notes is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * Margin Notes is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License (https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) 
 * for more details.
 *
 *
*/

defined('ABSPATH')||exit;

register_activation_hook( __FILE__ , array( 'Margin_Notes', 'on_activation' ) );
register_deactivation_hook( __FILE__ , array( 'Margin_Notes', 'on_deactivation' ) );
register_uninstall_hook(__FILE__ , array( 'Margin_Notes', 'on_uninstall' ) );

if (! class_exists('Margin_Notes') ){
class Margin_Notes {
	//property to hold singleton instance
	static $instance = false;

	private function __construct() {

		//front-end
		add_filter( 'the_content' , array( $this, 'filter_content'), 10000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_front_end'));
		add_filter( 'post_class', array($this, 'add_container_class'), 50);
		
		//back-end
		add_action( 'admin_enqueue_scripts', array( $this, 'load_back_end' ) );
		add_action( 'admin_init', array( $this, 'setup_admin_settings' ) );
		add_action( 'wp_ajax_handle_annotations', array( $this, 'handle_annotations') );
		add_action( 'admin_post_delete_annotation', array( $this , 'delete_annotation' ) );
		add_action( 'admin_post_annotation' , array( $this, 'get_form_data') );
		add_action( 'admin_head', array( $this, 'admin_style' ) );

	}

	/**
	* Creates an instance of the class, or returns the instance if 
	* it already exists.
	*
	* @since 1.0.0
	*
	*/

	public static function get_instance(){
		
		if (!self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}

	public function add_container_class( $classes ){
		$classes[]='margin-notes-container';
		return $classes;
	}

	/**
	* executes activation tasks, including creating two options and adding 
	* the annotation capability to subscriber and admin roles
	*
	* @since 1.0.0
	*/

	public static function on_activation(){

		if ( !current_user_can( 'activate_plugins' ) ){
			return;
		}

		add_option( 'margin_notes_html_string', '' );
		add_option( "annotations" , array() );


		self::add_annotation_cap();

		//self::setup_admin_settings();
	}

	/**
	* executes deactivation tasks
	*
	* @since 1.0.0
	*/

	public static function on_deactivation(){

		if ( !current_user_can( 'activate_plugins' ) ){
			return;
		}

		self::remove_annotation_cap();
	}

	/**
	* Runs on uninstall - cleans up wp_options.
	*
	* @since 1.0.0 
	*/

	public static function on_uninstall(){
		delete_option( 'annotations' );
		delete_option( 'margin_notes_html_string' );
		delete_option( 'margin_notes_display_options ');
	}

	/**
	* load plugin text domain
	*
	* @since 1.0.0
	*/

	private function load_textdomain(){
		load_plugin_textdomain( 'margin-notes', false, plugins_url( 'languages' , __FILE__ ) );
	}

	/**
	* Runs content through the two main filtering functions:
	* $this -> print_annotation_form
	* $this -> show annotations
	*
	* @since 1.0.0
	* @param string 	$content  	content to filter
	*/

	/**
	* Add custom 'annotate' capability to subscriber and administrator roles
	* 
	* @since 1.0.0
	*/

	public static function add_annotation_cap(){
		foreach( [ 'subscriber', 'administrator' ] as $role_name ) {
			$role = get_role( $role_name );
			$role->add_cap( 'annotate', true );
		}
	}

	/**
	* Remove custom 'annotate' capability from subscriber and administrator roles
	* 
	* @since 1.0.0
	*/

	public static function remove_annotation_cap(){
		foreach( [ 'subscriber', 'administrator' ] as $role_name ) {
			$role = get_role( $role_name );
			$role->remove_cap( 'annotate' );
		}
	}

	public function filter_content( $content ){

		if ( in_the_loop() && is_main_query() && is_singular() ){
		
			$content = $this->print_annotation_form( $content );
			
			$content = $this->show_annotations( $content );

			return $content;
		} else {
			
			return $content;
		}

	}

	/**
	* Puts together html for both the tooltip and margin annotations displays
	*
	* The general order of operations is:
	* 	* retrieve the annotations from the database, 
	* 	* order them according to when they appear in the content,
	*   * put together a delete link based on the annotation's id
	*	* wrap the annotation's source text in a <span> for styling 
	*	* create html for the tooltip (tooltip display) 
	* 	* create a html string of <div> annotations to be ajaxed to front end once page is loaded (margin display)
	*
	* @since 1.0.0
	* @param string 	$content 	html for page/post
	*/

	public function show_annotations( $content ) {
		$site_annotations = get_option( 'annotations' );
		$html_string = get_option( 'margin_notes_html_string' );
		
		$user = wp_get_current_user()->ID;
		if ( 0 === $user ) return $content;
		
		$post = get_post()->post_name;
		if (!array_key_exists($post, $site_annotations[$user] )) return $content;
		
		$annotations = $site_annotations[$user][$post];
		
		if ( ! $annotations || ! is_singular() || ! current_user_can( 'annotate' ) ){
			update_option( 'margin_notes_html_string', '');
			return $content;
		}

		$settings = get_option('margin_notes_display_options');

		/*
		annotations are returned from options api as an array ordered by when
		the user created them. For display purposes they 
		should be ordered according to where they appear in the content,
		so this next section re-orders them. 
		*/

		function get_source( $arr ){
			return $arr['source'];
		}
		
		$sources = array_map( 'get_source', $annotations );
		$annotations_by_index = array();
		foreach ( $annotations as $id => $annotation ){
			$index = strpos( $content, $annotation['source']);
			if ( ! $index ){
				$index = 0;
			}
			//prevent overwriting any annotations in case 2 
			//of them start on same index in $content
			if ( isset( $annotations_by_index[$index] ) ){
				while ( isset( $annotations_by_index[$index] ) ){
					$index++;
					$annotation['source'] = substr( $annotation['source'], 1);
				}
			}
		
			$annotations_by_index[$index] = array( 
				'source' => $annotation['source'],
				'annotation' => $annotation['annotation'], 
				'id' => $id
			);
		} 
		
		ksort( $annotations_by_index );
		//all sorted

		/*
		the function adds <spans> for the highlights one at a time at specific indexes,
		and those indexes will change as length of $content string 
		changes. $cumulative_tag_length variable is to keep track 
		of how many characters we are adding. 
		*/
		$cumulative_tag_length = 0;
			
		$annotation_html = '';
		$note_num = 0;
		$margin_display = $settings['display_type'] === 'margins';
		

		while ( current( $annotations_by_index ) ) {
			$current = current( $annotations_by_index );
			$source = $current['source'];
			$note_num++;
			
			$delete_url_query_string = sprintf('&action=delete_annotation&id-to-delete=%s&post=%s', $current['id'], $post ); 
			$delete_url_base = wp_nonce_url( admin_url('admin-post.php'), 'delete-annotation' , 'delete-annotation' );
			$delete_url = $delete_url_base . $delete_url_query_string;
			$delete_button = sprintf( '<a class="%s" href="%s">%s</a>', 'mn-delete-annotation', esc_url( $delete_url ), esc_html__( 'delete', 'margin-notes') );

			ob_start();
			?>
			<button aria-hidden class="annotation-slideout-control" type="button">
				<span class="annotation-number"><?php echo $note_num ?></span>
			</button>
			<?php
			$slideout_button = ob_get_contents();
			ob_end_clean();
						
			if ( ! $settings['hide_notes'] && $margin_display ){
				$which_margin= 'left' === $settings['which_margin'] ? 'left' : 'right';
				
				$annotation_html .= sprintf( 
					'<div class="annotation annotation-%d annotation-%s">%s<p>%d. %s</p>%s</div>', 
					esc_attr( $current['id'] ), 
					$which_margin,
					$slideout_button,
					$note_num, 
					esc_html( stripslashes( $current['annotation'] ) ),  
					$delete_button 
				);

			}

			/*
				if highlights overlap, only one tooltip should display at a time.
				this section shortens the first highlight text so it ends immediately 
				before the next begins - mouse never hovers over 2 highlights at once.
				*/
				$text_to_wrap = $source;
				$index = key($annotations_by_index) + $cumulative_tag_length; 
				next( $annotations_by_index );
				$next_index = key( $annotations_by_index ) ? key($annotations_by_index) + $cumulative_tag_length : null;
				prev( $annotations_by_index );
				
				if ( $next_index ){
					$diff = $next_index - $index;
					$source_length = strlen( $source );
					
					if ( $source_length > $diff ){
						$text_to_wrap =  substr( $source, 0 , $diff );
					} 
				} 
			
			
			if ( $margin_display ){
				$tag = sprintf( 
					'<span class="mn-highlight annotation-%d" >%s<span class="sup">%d</span></span>', 
					esc_attr( $current['id'] ), 
					esc_html( $text_to_wrap ), 
					$note_num
				); 
			} else {
				$tag = sprintf( 
					'<span class="mn-highlight annotation-%d">%s</span>', 
					esc_attr( $current['id'] ),
					esc_html( $text_to_wrap )
				);
			}

			//everything in content up unitl the new highlight <span>
			$content_before = substr( $content, 0 , $index);
			//the index at which text that will be wrapped in <span> ends
			$ending_index = $index + strlen( $text_to_wrap );
			//everything in content following end of the new highlight <span>
			$content_after = substr( $content, $ending_index );
			//add length of tag chararcters so they can be accounted for when locating next index
			$cumulative_tag_length += strlen( $tag ) -  strlen( $text_to_wrap );
			//insert the new <span> in place of old string.
			$content = $content_before . $tag . $content_after;

			//$content = preg_replace( $text_to_wrap, $tag, $content, 1);

			next( $annotations_by_index );
		
		}
		
		if ( $margin_display ){
			update_option( 'margin_notes_html_string', $annotation_html );
		} else {
			$content .= '<div id="annotation-tooltip" class="annotation-tooltip no-display"><div class="spacer"><div class="tip-content">';
			$content .=	'<p class="annotation-body"></p>';
			$content .= sprintf( '<a class="%s" href="#">%s</a>', 'mn-delete-annotation', __( 'delete' , 'margin-notes' ) );
			$content .= '</div><div id="mn-tri" class="tri"></div></div></div>';
		}
		
		return $content;

	}

	/**
	* Handles ajax request from front end for annotations to display.
	*
	* @since 1.0.0
	*/

	public function handle_annotations(){
		check_ajax_referer('populate_annotations' , 'security' );

		if ( ! current_user_can ( 'annotate' ) ){
			wp_die();
		}

		$post = $_POST['post'];

		$annotation_html = get_option('margin_notes_html_string');

		$response =  $annotation_html;
		
		wp_send_json( $response );
 	
		wp_die();
	}

	/**
	* Handles a request to delete a single annotation. 
	*
	* Verifies the nonce, gets the user object, and makes sure they have the 
	* 'annotate' capability. If so, deletes the annotation specified in request.
	*
	* @since 1.0.0
	*/

	public function delete_annotation(){

		$req = isset( $_GET['delete-annotation'] ) ? $_GET : $_POST;
		
		if ( ! wp_verify_nonce( $req['delete-annotation'], 'delete-annotation') || ! current_user_can( 'annotate' ) ){
			wp_die();
		}

		$id = $req['id-to-delete'];
	
		$post  = $req['post'];
		
		$user = wp_get_current_user()->ID;
		$annotations = get_option('annotations');

		array_splice( $annotations[$user][$post], $id, 1);
		
		update_option( 'annotations', $annotations );
		update_option( 'margin_notes_html_string', '');

		$url = get_home_url() . '/index.php/' . $post;

		wp_redirect( $url );

	}

	/**
	* Prints the form with which users can submit new annotations
	*	
	* @since 1.0.0
	* @param string 	$content 	the post/page content.
	*/
	
	public function print_annotation_form( $content ){

		if ( ! is_singular() ){
			return $content;
		}

		if ( ! current_user_can( 'annotate' ) ){
			return $content;
		} 

		include_once 'lib/check_icon.php';

		$settings = get_option( 'margin_notes_display_options' );

		$svg = '
		<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 50 50">
			<circle cx="25" cy="25" r="25" fill="'.$settings['primary_color'].'">
				
			</circle>
			<line class="vertical" x1="25" y1="15" x2="25" y2="35" stroke="'.$settings['secondary_color'].'" stroke-width="5"  stroke-linecap="round"/>
			<line x1="15" y1="25" x2="35" y2="25" stroke="'.$settings['secondary_color'].'" stroke-width="5" stroke-linecap="round"/>
			<title>'.__( 'Margin Notes: Add Annotation', 'margin-notes' ).'</title>
		</svg>
		';

		$direction = $settings['which_margin'] === 'left' ? 'left' : 'right';

		$wrapper_class = 'margin-notes-wrapper margin-notes-wrapper-' . $direction ;

		$form_class = 'margin-notes-form margin-notes-form-' . $direction ;
		
		$html = '<button id="margin-notes-add" class="margin-notes-add margin-notes-button margin-notes-add-' . $direction . '">';
		$html .= $svg.'</button>';					
		$html .= sprintf( '<div id="margin-notes-wrapper" class="%s">', esc_attr( $wrapper_class ) );
		$html .= sprintf( 
					'<form id="margin-notes-form" class="%s" action="%s" method="post">', 
					esc_attr( $form_class ), 
					esc_url( admin_url('admin-post.php') ) 
				);
		$html .= sprintf( '<input type="text" name="post-name" id="post-name" readonly="readonly" value="%s">', esc_attr(get_post()->post_name) );
		$html .= '<label>'.__( 'Copy and paste source text for your annotation.', 'margin-notes' );
		$html .= '<input name="highlight" id="highlight-input" type="text"></label>';
		$html .= '<p id="highlight-error"></p>';
		$html .= '<label>'.__( 'Create an annotation.', 'margin-notes' );
		$html .= sprintf( 
					'<textarea name="annotation" rows="10" id="annotation-input" placeholder="%s ..." type="text">',
					__( 'your thoughts' , 'margin-notes' )
				);
		$html .= '</textarea></label>';
		$html .= '<label>'.__('Delete all annotations on this page.', 'margin-notes' );
		$html .= '<input type="checkbox" id="deleteAll" value="delete" name="delete">';
		$html .= sprintf( 
					/*'<div class="surrogate-checkbox colored-border">*/'%s',/*</div>',*/
					renderCheck( $settings['primary_color'], $settings['secondary_color'] )
				);
		$html .= '</label>';
		$html .= '<input type="hidden" name="action" value="annotation">';
		$html .= wp_nonce_field('submit-annotation','thoughts-on-article');
		$html .= sprintf( 
			'<input type="submit" value="%s" id="margin-notes-submit" class="margin-notes-button colored-border">', 
			__( 'Submit' , 'margin-notes' )
			);
		$html .= '</form></div>';

		return $content.$html;

	}

	/**
	* gets attributes and strings to populate the margin notes settings section in Settings -> Discussion
	*
	* @since 1.0.0
	*/

	public function settings_parameters() {

		$sections = array(
			'display_settings' => array(
				'title' => __( 'Margin Notes Display Settings', 'margin-notes' ),
				'callback' => '__return_true',
				'page' => 'discussion'
			),
			'color_settings' => array(
				'title' => __( 'Margin Notes Color Settings', 'margin-notes' ),
				'callback'=> 'echo_color_section_instructions',
				'page' => 'discussion'
			)
		);

		$fields = array(
			array(
				'name' => 'primary_color',
				'title' => __( 'Form Background Color', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __('Background color for "create new" form. Defaults to white.', 'margin-notes'),
			),
			array(
				'name' => 'secondary_color',
				'title' => __( 'Form Text Color', 'margin-notes'),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __('Text color for "Create New" form. Defaults to white', 'margin-notes'),
			),
			array(
				'name' => 'tertiary_color',
				'title' => __( 'Annotation Text Color', 'margin-notes'),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __( 'Color for the text of the notes themselves. Defaults to black.', 'margin-notes')
			),
			array(
				'name' => 'note_background_color',
				'title' => __( 'Annotation Background Color', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __( 'Color for background of the notes. Defaults to white.', 'margin-notes')
			),
			array(
				'name' => 'highlight_text_color',
				'title' => __('Highlight Text Color', 'margin-notes'),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __( 'Text color for annotation\'s source text.', 'margin-notes')
			),
			array(
				'name' => 'display_type',
				'title' => __( 'Display Type', 'margin-notes' ),
				'type' => 'radio_group',
				'section' => 'display_settings',
				'desc' => __( 'Annotations can be displayed either as tooltips or as notes in the margins', 'margin-notes'),
				'values' => array( 'margins' , 'tooltips' )
			),
			array(
				'name' => 'container',
				'title' => __( 'Container', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'display_settings',
				'desc' => __( 'Optional. A css selector for an html element in your theme to be used as the container for the annotations. 
				The best choice is usually the wrapper for the main content of an individual post, which varies by theme but is often named 
				"div.entry-content" or something similar. Feel free to leave this field blank if you\'re not sure what to write. Defaults to 
				the element targeted by the `post_class` function.', 'margin-notes')
			),
			/*array(
				'name' => 'container_type',
				'title' => __( 'Container Attribute Type', 'margin-notes' ),
				'type' => 'radio_group',
				'section' => 'display_settings',
				'desc' => __( 'The name listed above is an:', 'margin-notes'),
				'values' => array( 'id', 'class' )
			),*/
			array(
				'name' => 'width_value',
				'title' => __( 'Width Value', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'display_settings',
				'desc' => __( 'Specify a width your annotations should take up on large screens.' , 'margin-notes')
			),
			array(
				'name' => 'width_unit',
				'title' => __( 'Width Unit', 'margin-notes' ),
				'type' => 'radio_group',
				'section' => 'display_settings',
				'desc' => __( 'Specify a unit for the width of the annotations.', 'margin-notes'),
				'values' => array( 'px', '%' , 'vw' )
			),
			array(
				'name' => 'which_margin',
				'title' => __( 'Choose Margin', 'margin-notes' ),
				'type' => 'radio_group',
				'section' => 'display_settings',
				'desc' => __( 'The side of the page where annotations will appear and from which the form will slide out.', 'margin-notes'),
				'values' => array( 'left', 'right' ),
			),
			array(
				'name' => 'hide_notes',
				'title' => __( 'Hide Notes', 'margin-notes' ),
				'type' => 'checkbox',
				'section' => 'display_settings',
				'desc' => __( 'Hide annotations on all pages that have them', 'margin-notes'),
				'values' => array('true'),
			),
			/*array(
				'name' => 
				'title' =>
				'type' =>
				'section' => 'display_settings',
				'desc' => __( , 'margin-notes')
			),*/
		);

		return apply_filters( 'margin_notes_settings_parameters', 
			array( 
				'sections' => $sections,
				'fields' => $fields,
			) 
		);

	}

	/**
	* Wraps helper text for settings form in a <p>
	*
	* @since 1.0.0
	*/

	public function get_description( $text ){
		return '<p>'.$text.'</p>';
	}

	/**
	* Renders a text input for the margin notes settings section
	*
	* @since 1.0.0
	* @param array 		$args 	an array of attributes (name, value, id) for the input
	*							Note: these values are hard-coded so they aren't escaped
	*/

	public function text_input( $args ) {
		$setting = $args['setting'];
		$name=$args['name'];
		$description = $args['description'];

		$html = '';
		$value = isset( $setting ) ? $setting : '' ;

		$html .= $this->get_description( $description );
		$html .= '<input type="text" id="margin_notes_'.$name.'" value="'.$value.'" name="margin_notes_display_options['.$name.']"  >';
		echo $html;
	}

	/**
	* Renders a radio group for the margin notes settings section.
	*
	*
	* @since 1.0.0
	* @param array 		$args 	an array of attributes (name, value, id) for the input
	*							Note: these values are hard-coded so they aren't escaped
	*/

	public function radio_group( $args ){
		[
			'setting' => $setting,
			'name' => $name,
			'description' => $description,
			'values' => $values,
		] = $args;
		
		$html = '';
		$value = isset( $setting ) ? $setting : '' ;

		$html .= $this->get_description( $description );

		foreach ($values as $val ){
	
			$html .= '<label>';
			$html .= '<input type="radio" value="'.$val.'" name="margin_notes_display_options['.$name.']" id="margin_notes_'.$name.'_'.$val.'" '.checked($val, $value, false).' >';
			$html .= '<span>' . $val . ' </span></label>';	
		}

		echo $html;
	}

	/**
	* Renders a checkbox for the margin-notes settings section 
	*
	* @since 1.0.0
	* @param array 		$args 	an array of attributes (name, value, id) for the input
	*							Note: these values are hard-coded so they aren't escaped
	*/

	public function checkbox ( $args ){
		$setting = $args['setting'];
		$name=$args['name'];
		$description = $args['description'];
		$val = $args['values'][0];
		

		$html = '';
		$value = isset( $setting ) ? $setting : '' ;

		$html .= $this->get_description( $description );
		$html .= '<input type="checkbox" value="'.$val.'" name="margin_notes_display_options['.$name.']" id="margin_notes_'.$name.'_'.$value.'" '.checked($value, $val, false). ' >';

		echo $html;
	}

	/**
	* Callback function to validate user input into the settings fields.
	*
	* Ensures colors are valid hexes and radio groups correspond to one of their allowed values
	* 
	* @since 1.0.0
	* @param array 	$input 	the settings array
	*/

	public function sanitize_settings_fields( $input ){
		
		foreach ( ['primary_color', 'secondary_color', 'tertiary_color', 'note_background_color'] as $field ){
			$input[$field] = sanitize_hex_color( $input[$field] );
		}

		$radios = array(
			array( 'container_type', 'id', 'class'  ),
			array( 'display_type', 'margins', 'tooltips' ),
			array( 'which_margin', 'left', 'right' )
		);

		foreach ( $radios as $field ){
			$input[$field[0]] = $this->sanitize_radio( $input[$field[0]], $field[1], $field[2] );
		}

		$input['container'] 	 = sanitize_text_field( $input['container'] );
		$input['width_value'] 	 = intval( $input['width_value'] );
		
		$width_units = array( 'px', '%',  'px');
		if ( ! in_array( $input['width_unit'], $width_units ) ){
			$input['width_unit'] = 'px';
		}
		
		if ( ! is_bool( $input['hide_notes'] ) ){
			$input['hide_notes'] = false;
		}

		return $input;
	}

	/**
	* sanitizes a radio input by insuring the returned value is one of the two provided.
	*
	* @since 1.0.0
	*/

	public function sanitize_radio( $input, $val1, $val2 ) {
		return $input == $val1 ? $val1 : $val2;
	}

	/**
	* Sanitizes user input from color fields in the the settings section.
	*
	* @since 1.0.0
	*/
	/*
	private function sanitize_color_field( $color ){

		if ( strlen( $color ) > 7 ){
			$color = substr( $color, 0, 7 );
		}

		$first = substr( $color, 0, 1);
		$color = substr( $color, 1, 6 );
		
		if ( $first != '#' || preg_match( '/[a-zA-Z\d]+/', $color ) === false ){
			$color = '';
		} else {
			$color = '#' . $color;
		}
		
		return $color;
	}
  */
	/**
	* Registers margin notes settings and prints html for settings fields
	*
	* @since 1.0.0
	*/

	public function setup_admin_settings (){
		
		register_setting(
			'discussion',
			'margin_notes_display_options',
			array(
				'type' => 'array',
				'description' => 'display options for margin notes',
				'sanitize_callback' => array( $this, 'sanitize_settings_fields' )
			)
		);

		$settings = get_option( 'margin_notes_display_options' );
		$display_type = $settings['display_type'];

		['sections' => $sections, 'fields' => $fields ] = $this->settings_parameters();
		
		if ( ! function_exists( 'echo_color_section_instructions' ) ){
			function echo_color_section_instructions(){
				echo '<p>' . __( 'You can specify as many as four theme colors. Please use hex format (ie., "#123456" or "#BBB").', 'margin-notes') . '</p>';
			}
		}

		foreach ( $sections as $section => $args ) {
			add_settings_section( 'margin_notes_' . $section, $args['title'], $args['callback'], $args['page']  );
		}

		foreach ( $fields as $field ){

			//conditionally hide certain fields only needed for margin display type
			$display = '';
			if ( $display_type === 'tooltips' && $field['section'] === 'display_settings' ){
				if ( $field['name'] != 'display_type' && $field['name'] != 'hide_notes' && $field['name'] != 'which_margin' ){
					$display='no-display';
				}
			}

			add_settings_field( 
				'margin_notes_'.$field['name'],
				$field['title'],
				array( $this, $field['type'] ),
				'discussion',
				'margin_notes_'.$field['section'],
				array(
					'name' => $field['name'],
					'description' => $field['desc'],
					'setting' => $settings[ $field['name'] ],
					'values' => isset( $field['values'] ) ? $field['values'] : array(),
					'class' => $field['name'].' '.$display
				)
			);
		}
	}

	/**
	* converts any non texturized quotations in POST body
	*
	* @since 1.0.0
	* @param string 	$str 	the haystack in which to find quotes
	*/

	public function replace_quotes( $str ){

		return str_replace(
			array(
				'&rsquo;',
				'&lsquo;',
				'&ldquo;',
				'&rdquo;',
			),array(
				'&#8217;',
				'&#8216;',
				'&#8220;',
				'&#8221;'
			), 
			htmlentities( sanitize_text_field( $str ) )
		);
	}

	/**
	* Handles the POST request when the create annotation form is submitted
	*
	* checks the nonce, checks the user's 'annotate' capability, sanitizes the input and adds it to the database.
	* If 'delete all' box is checked, removes all annotations from current post.
	*
	* @since 1.0.0
	*/

	public function get_form_data(){
		
		if ( ! wp_verify_nonce( $_POST['thoughts-on-article'], 'submit-annotation') || ! current_user_can('annotate' ) ){
			return;
		}

		$post = sanitize_text_field( $_POST['post-name'] );
		$post_obj = get_posts(array(
			'name'=>$post,
			'numberposts' => 1,
		))[0];
		$url = get_permalink( $post_obj );

		$annotations = get_option('annotations');
		$user = wp_get_current_user()->ID;
		
		if ( isset( $_POST['delete'] ) ){
			$annotations[$user][$post]=array();
			update_option( 'annotations' , $annotations );
			update_option( 'margin_notes_html_string', '' );
			wp_redirect( $url );
		}

		else if ( $_POST['annotation'] ) {
			
			$text = $this->replace_quotes( $_POST['highlight'] );
			$annotation = sanitize_textarea_field( $_POST['annotation'] );
			
			if ( ! $annotations[$user] ){
				$annotations[$user]=array(
					$post=>array(
						0 => array(
								'source' => $text,
								'annotation' => $annotation
						)
					)
				);

			} elseif ( ! $annotations[$user][$post] ){

				$annotations[$user][$post]=array(
					0 => array(
							'source' => $text,
							'annotation' => $annotation
						)
				);

			} else {
				function find_last($str){
					$words = explode(' ',$str);
					$length = count( $words );
					return $words[$length-1];
				}
				function get_source($arr){
					return $arr['source'];
				}

				$id = count( $annotations[$user][$post] );
				$annotations[$user][$post][$id] = array( 
					'source' => $text, 
					'annotation' => $annotation 
				);
			}
			update_option( 'annotations' , $annotations );
		}
		
		wp_redirect( $url );
	}

	/**
	* Creates the css for any plugin styles that depend on user settings
	*
	* @since 1.0.0
	*/

	public function build_inline_styles(){

		$options = get_option('margin_notes_display_options');

		$primary = esc_attr($options['primary_color']);
		$secondary = esc_attr($options['secondary_color']);
		$tertiary = esc_attr($options['tertiary_color']);
		$note_background = esc_attr($options['note_background_color']);
		$highlight_text_color = esc_attr($options['highlight_text_color']);
		$display_type = $options['display_type'];
		$which_margin = esc_attr($options['which_margin']);
		$width_value = esc_attr($options['width_value']);
		$width_unit = esc_attr($options['width_unit']);
		$other_margin = 'left' == $which_margin ? 'right' : 'left';

		$width = $width_value . $width_unit;
		$form_wrapper_offset = -1 * $width_value . $width_unit;

		if ( 'margins' === $display_type ){
			$annotation_style = "
			.annotation{
				border-$other_margin: 3px solid $tertiary;
				color: $tertiary;
				background: $note_background;
				width: $width!important;
			}		
		";

		} else{
			$annotation_style = "
			.annotation-tooltip div.tip-content{
				background: $note_background;
			}

			.annotation-tooltip div.tip-content p{
				color: $tertiary;
			}

			.tri{
				border-bottom: 10px solid $note_background;
			}
		";

		}

		$add_button_style = "
			#margin-notes-add svg circle{
				fill: $secondary;
			}

			#margin-notes-add svg line{
				stroke: $primary;
			}
		";

		$highlight_style = "
			.mn-highlight{
				color: $highlight_text_color ;
			}
		"; 

		$delete_style = "
			.mn-delete-annotation{
				color: $primary ;
			}

			.mn-delete-annotation:hover{
				color: $tertiary;
			}
		";

		//position form just off page on user's choice of left or right
		$form_wrap_style = '';/*"
			#margin-notes-wrapper{
				width: {$width};
				{$which_margin}: {$form_wrapper_offset};
			";*/

		$form_style = "
			#margin-notes-form{
				background:$primary;
			}

			#margin-notes-form label{
				color:$secondary;
			}

			#margin-notes-form input[type=checkbox]:checked + svg line{
				stroke:$primary;
			}

			#margin-notes-form input[type=checkbox]:checked + svg rect{
				fill: $secondary;
			}

			#highlight-error{
				color:$secondary;
			}
		";

		$form_submit_style = "
			#margin-notes-submit{
				background:$primary;
				color:$secondary;
				border:2px solid $secondary;
			}
		";

		$heading_style = "";
		
		if ( $display_type === 'margins' ){
			$heading_style .= "h1,h2,h3,h4,h5,h6{
				clear:none!important;
			}";
		}

		return $annotation_style . $highlight_style . $delete_style . $form_wrap_style . $form_style . $form_submit_style . $heading_style . $add_button_style;
		
	}

	/**
	* Enqueues styles and scripts for plugin front end. Also localizes javascript file 
	* with needed values
	*
	* @since 1.0.0
	*/

	public function load_front_end (){

		$disableStyles = apply_filters( 'margin_notes_disable_styles', false );

		if ( $disableStyles ) {
			return;
		}

		if ( ! is_singular() ){
			return;
		}

		//enqueue styles
		wp_enqueue_style( 'margin_notes_style', plugins_url( '/lib/margin-notes-style.css', __FILE__ ) );

		$user_styles = $this->build_inline_styles();
		wp_add_inline_style( 'margin_notes_style', wp_kses( $user_styles, array("\'", '\"') ) );

		/*
		Get user, annotaions, and post objects, 
		avoiding anything undefined along the way.
		*/

		$user = wp_get_current_user()->ID;
		if ( 0 === $user) return;

		$site_annotations 	= get_option( 'annotations' );

		if (! array_key_exists($user, $site_annotations)) return;

		$post_obj 	= get_post();
		$post 			= $post_obj->post_name;

		if (! array_key_exists( $post, $site_annotations[$user] )) return;
		$annotations = $site_annotations[$user][$post];
		
		//enqueue script
		wp_enqueue_script('margin-notes',plugins_url('/lib/margin-notes.js',__FILE__),array('jquery') );
		$settings = get_option( 'margin_notes_display_options' );
		$container = !empty( $settings['container']) ?  $settings['container'] : '.margin-notes-container';

		$nonce 			= wp_create_nonce( 'populate_annotations' );
		$content      	= apply_filters( 'the_content', get_the_content( null, false, $post_obj ) );
		$delete_url 	= wp_nonce_url( admin_url('admin-post.php'), 'delete-annotation', 'delete-annotation' );

		wp_localize_script('margin-notes', 'settings', array(
				'primary_color' => $settings['primary_color'],
				'secondary_color' => $settings['secondary_color'],
				'note_background_color' => $settings['note_background_color'],
				'tertiary_color' => $settings['tertiary_color'],
				'width_value' => $settings['width_value'],
				'width_unit' => $settings['width_unit'],
				'direction' => $settings['which_margin'],
				'container' => $container,
				'ajaxURL' => admin_url('admin-ajax.php'),
				'security' => $nonce,
				'display_type' => $settings['display_type'],
				'post' => $post,
				'content' => $content,
				'annotations' => $annotations,
				'delete_url' => $delete_url
			)
		);
	}

	/**
	* Enqueue styles and scripts for admin section
	*
	* @since 1.0.0
	*/

	public function load_back_end(){

		wp_register_script( 'admin-script', plugins_url( '/lib/margin-notes-admin.js', __FILE__ ), array('jquery'), '1.0.0' );
		wp_enqueue_script( 'admin-script' );

	}

	/**
	* adds minor css for admin area using admin_head
	*
	* @since 1.0.0
	*
	*/

	public function admin_style(){
		?>
			<style>
				.no-display{
					display:none;
				}
			</style>
		<?php
	}

}
}

$Margin_Notes = Margin_Notes::get_instance();

?>