<?php
/*
 *Plugin Name: Margin Notes
 *Description: Allows subscribers to annotate articles on your site
 *Author:Peter Steele
 *Version:1.0.0
 *License:GPL2
 *License URI:https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Margin Notes is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Margin Notes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Margin Notes. If not, see https://www.gnu.org/licenses/old-licenses/gpl-2.0.html.

//Todo
add input to control whether a user's annotations display
potential settings to add :
	-highlight color
	-option for whether to display as tooltip over highlighted text or in margins
	--display in left margin
overlapping text?
*/

defined('ABSPATH')||exit;

register_activation_hook( __FILE__ , array( 'Margin_Notes', 'on_activation' ) );
register_deactivation_hook( __FILE__ , array( 'Margin_Notes', 'on_deactivation' ) );
register_uninstall_hook(__FILE__ , array( 'Margin_Notes', 'on_uninstall' ) );

class Margin_Notes {

	static $instance = false;

	public $annotations = array();

	private function __construct() {

		add_action( 'admin_post_annotation' , array( $this, 'get_form_data') );
		add_filter( 'the_content' , array( $this, 'filter_content') );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_front_end'));
		add_action( 'admin_init', array( $this, 'setup_admin_settings' ) );
		add_action( 'wp_ajax_handle_annotations', array( $this, 'handle_annotations') );	
		add_action( 'wp_ajax_nopriv_handle_annotations', array( $this, 'handle_annotations' ) );

	}

	public function get_instance(){
		
		if (!self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}

	public static function on_activation(){

		if ( !current_user_can( 'activate_plugins' ) ){
			return;
		}

		add_option( "annotations" , array() );
		self::add_reader_role();
		self::setup_admin_settings();

	}

	public static function on_deactivation(){

		if ( !current_user_can( 'activate_plugins' ) ){
			return;
		}

		self::remove_reader_role();
	}

	public static function on_uninstall(){
		delete_option( 'annotations' );
	}

	public function filter_content( $content ){

		$content = $this->print_annotation_form( $content );
		
		$content = $this->show_annotations( $content );

		return $content;

	}

	public function show_annotations( $content ) {
		
		$site_annotations = get_option('annotations');
		$user = wp_get_current_user()->ID;
		$post = get_post()->post_name;
		$annotations = $site_annotations[$user][$post];

		if ( ! $annotations || ! is_singular() || ! current_user_can( 'annotate' ) ){
			return $content;
		}

		$settings = get_option('margin_notes_display_options');

		/*
		annotations are returned from options api as an array ordered by when
		the user created them. For display purposes they 
		should be ordered according to where they appear in the content. 
		*/
		
		$sources = array_keys( $annotations );
		$annotations_by_index = array();
		foreach ( $sources as $source ){
			$index = strpos( $content, $source);
			if ( ! $index ){
				$index = 0;
			}
			//prevent overwriting any annotations in the unlikely event that 2 
			//of them start on exact same index in $content
			if ( isset( $annotations_by_index[$index] ) ){
				while ( isset( $annotations_by_index[$index] ) ){
					$index++;
				}
			}
			$annotations_by_index[$index]=array( $source => $annotations[$source] );
		} 
		
		ksort( $annotations_by_index );
		
		$sorted_annotations = array();
		foreach ($annotations_by_index as $index => $annotation) {
			$source = array_keys( $annotation )[0];
			$sorted_annotations[$source] = $annotation[$source];
		}
		
		//all sorted

		$annotation_style = 'border-left:3px solid '.$settings['primary_color'].';
								color:'.$settings['tertiary_color'].';
								background:'.$settings['note_background_color'].';
								width:'.$settings['width_value'].$settings['width_unit'];

		$highlight_style = 'color:'.$settings['primary_color'].';';
		
		$annotation_html = '';
		
		$note_num = 0;
		foreach ($sorted_annotations as $source => $text) {
			
			$note_num++;

			//border-left:3px solid '.$settings['primary_color'].';color:'.$settings['tertiary_color'].';background:'.$settings['note_background_color'].'					
			if ( ! $settings['hide_notes'] ){
				$annotation_html .= '<p class="annotation annotation-'.$note_num.'" style="'.$annotation_style.'">'.$note_num.'. '.esc_html( stripslashes( $text ) ).'</p>';
			}
			
			$tag = '<span class="highlight annotation-'.$note_num.'" style="'.$highlight_style.'" >'.$source.' <span class="sup">'.$note_num.'</span></span>';
			
			$source = '/'.$source.'/';
			$content=preg_replace( $source, $tag, $content, 1);

		}

		$annotation_html.=$content;

		return $annotation_html;
		
	}

	public function handle_annotations(){
		
		check_ajax_referer('populate_annotations' , 'security' ); 

		$primary_color = get_option('margin_notes_primary_color');
		$secondary_color = get_option('margin_notes_secondary_color');

		$response = json_encode( array(
			'primary_color' => $primary_color,
			'secondary_color' => $secondary_color
			) 
		);
		//$response = array('five'=>'hi from the back end');
 		//print_r($response);
		wp_send_json( $response );
 		//echo  $response ;
		wp_die();
	}

	public function print_annotation_form( $content ){

		if ( ! is_singular() ){
			return $content;
		}

		if ( ! current_user_can( 'annotate' ) ){
			return $content;
		} 

		$settings = get_option( 'margin_notes_display_options' );

		$svg = '
		<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 50 50">
			<circle cx="25" cy="25" r="25" fill="'.$settings['primary_color'].'">
				
			</circle>
			<line class="vertical" x1="25" y1="15" x2="25" y2="35" stroke="'.$settings['secondary_color'].'" stroke-width="5"  stroke-linecap="round"/>
			<line x1="15" y1="25" x2="35" y2="25" stroke="'.$settings['secondary_color'].'" stroke-width="5" stroke-linecap="round"/>
			<title>'.__('Margin Notes: Add Annotation').'</title>
		</svg>
		';

		$margin_notes_wrapper_style = 'width:'.esc_attr( $settings['width_value'] ).esc_attr( $settings['width_unit'] );
		
		$margin_notes_form_style = 'background:'.esc_attr( $settings['primary_color'] ).';
									width:100%;';

		$margin_notes_add_style = 'color:'.esc_attr( $settings['primary_color'] ).';
								   width:20px;height:20px';

		$html = '<div class="margin-notes-wrapper" style="'.$margin_notes_wrapper_style.'">';
		$html .= '<button class="margin-notes-add margin-notes-button" style="'.$margin_notes_add_style.'">';
		$html .= $svg.'</button>';
		$html .= '<form id="margin-notes-form" class="margin-notes-form" style="'.$margin_notes_form_style.'" action="'.esc_url(admin_url('admin-post.php')).'" method="post">';
		$html .= '<input type="text" name="post-name" id="post-name" readonly="readonly" value="'.esc_attr(get_post()->post_name).'">';
		$html .= '<label style="color:'.$settings['secondary_color'].'">'.__('Copy and paste source text for your annotation.');
		$html .= '<input name="highlight" id="highlight-input" type="text">';
		$html .= '</label>';
		$html .= '<label style="color:'.$settings['secondary_color'].'">'.__('Create an annotation.');
		$html .= '<textarea name="annotation" rows="10" id="annotation-input" placeholder="your thoughts ... " type="text">';
		$html .= '</textarea>';
		$html .= '</label>';
		$html .= '<input type="hidden" name="action" value="annotation">';
		$html .= wp_nonce_field('submit-annotation','thoughts-on-article');
		$html .= '<input type="submit" value="submit" id="margin-notes-submit" style="background:'.$settings['primary_color'].';color:'.$settings['secondary_color'].'" class="margin-notes-button">';
		$html .= '</form></div>';

		return $content.$html;

	}

	public static function add_reader_role(){

		add_role(
			'reader',
			'Reader',
			array('read'=>true)
		);

		$administrator = get_role('administrator');

		$administrator->add_cap('annotate');

		$reader = get_role('reader');

		$reader->add_cap('annotate');

	}

	public static function remove_reader_role(){

		if ( get_role( 'reader' ) ){
			remove_role( 'reader' );
		}

	}

	public static function setup_admin_settings (){

		register_setting(
			'discussion',
			'margin_notes_display_options',
			array(
				'type' => 'array',
				'description' => 'display options for margin notes'
			)
		);

		/*COLOR SETTINGS*/

		function echo_color_section_instructions(){
			echo '<p>You can specify as many as four theme colors. Any color format accepted.</p>';
		}

		add_settings_section('margin_notes_color_settings','Margin Notes Color Settings','echo_color_section_instructions','discussion');

		//Primary Color Setting

		function echo_primary_color_input(){
			
			$option = get_option('margin_notes_display_options',"");
			$value = isset( $option['primary_color'] ) ? $option['primary_color'] : '' ;

			$html = '<p>Background color for "create new" form and accent color for notes. Defaults to black.</p>';
			$html .= '<input type="text" id="margin_notes_primary_color" value="'.$value.'" name="margin_notes_display_options[primary_color]">';
			echo $html;
		}
/*
		register_setting(
			'discussion',
			'margin_notes_primary_color',
			array(
				'type'=>'string',
				'description'=>'primary color'
		));
*/
		add_settings_field('margin_notes_primary_color','Form Background Color','echo_primary_color_input','discussion','margin_notes_color_settings');

		//Secondary color setting
	
		function echo_secondary_color_input(){

			$option = get_option('margin_notes_display_options');
			$value = isset( $option['secondary_color'] ) ? $option['secondary_color'] : '' ;

			$html = '<p>Text color for "create new" form. Defaults to white.</p>';
			$html.= '<input type="text" id="margin_notes_secondary_color" value="'.$value.'" name="margin_notes_display_options[secondary_color]">';
			echo $html;
		}
		/*
		register_setting(
			'discussion',
			'margin_notes_secondary_color',
			array(
				'type'=>'string',
				'description'=>'secondary color'
		));
		*/
		add_settings_field('margin_notes_secondary_color','Form Text Color','echo_secondary_color_input','discussion','margin_notes_color_settings');

		//tertiary color setting

		function echo_tertiary_color_input(){

			$option = get_option('margin_notes_display_options');
			$value = isset( $option['tertiary_color'] ) ? $option['tertiary_color'] : '' ;

			$html = '<p>Color for the text of the notes themselves. Defaults to black.</p>';
			$html.= '<input type="text" id="margin_notes_tertiary_color" value="'.$value.'" name="margin_notes_display_options[tertiary_color]">';
			echo $html;

		}
		/*
		register_setting(
			'discussion',
			'margin_notes_tertiary_color',
			array(
				'type'=>'string',
				'description'=>'tertiary color'
		));
		*/
		add_settings_field('margin_notes_tertiary_color','Annotation Text Color','echo_tertiary_color_input','discussion','margin_notes_color_settings');

		//note background color setting

		function echo_note_background_color_input(){

			$option = get_option('margin_notes_display_options');
			$value = isset( $option['note_background_color'] ) ? $option['note_background_color'] : '' ;

			$html = '<p>Color for background of the notes. Defaults to white.</p>';
			$html .= '<input type="text" id="margin_notes_note_background_color" value="'.$value.'" name="margin_notes_display_options[note_background_color]">';
			echo $html;
		}	
		/*
		register_setting(
			'discussion',
			'margin_notes_note_background_color',
			array(
				'type'=>'string',
				'description'=>'note background color'
		));
		*/
		add_settings_field('margin_notes_note_background_color','Annotation Background Color','echo_note_background_color_input','discussion','margin_notes_color_settings');

		/*DISPLAY SETTINGS*/

		add_settings_section('margin_notes_display_settings','Margin Notes Display Settings','__return_true','discussion');

		

		//Width of annotations

		function echo_width_value (){

			$option = get_option("margin_notes_display_options");
			$value = isset( $option['width_value'] ) ? $option['width_value'] : '' ;
			
			$html = '<p>Specify a width your annotations should take up on large screens.</p>';
			$html .= '<input type="text" value="'.$value.'" id="margin_notes_width_value" name="margin_notes_display_options[width_value]">';
			echo $html;

		}

		add_settings_field('margin_notes_width_value','Width Value','echo_width_value','discussion','margin_notes_display_settings');

		function echo_width_unit (){

			$option = get_option("margin_notes_display_options");
			$value = isset( $option['width_unit'] ) ? $option['width_unit'] : '';

			$html = '<p>Specify a unit for the width of the annotations.</p><fieldset>';
			$html .= '<label for="margin_notes_width_unit_px">px</label>';
			$html .= '<input type="radio" name="margin_notes_display_options[width_unit]" value="px" id="margin_notes_width_unit_px" '.checked( 'px', $value, false ).' >';
			$html .= '<label for="margin_notes_width_unit_%">%</label>';
			$html .= '<input type="radio" name="margin_notes_display_options[width_unit]" value="%" id="margin_notes_width_unit_%" '.checked( '%', $value, false ).' >';
			$html .= '<label for="margin_notes_width_unit_vw">vw</label>';
			$html .= '<input type="radio" name="margin_notes_display_options[width_unit]" value="vw" id="margin_notes_width_unit_vw" '.checked( 'vw', $value, false ).' ></fieldset>';
			echo $html;
		}

		add_settings_field( 'margin_notes_width_unit' , "Width Unit" , 'echo_width_unit', 'discussion' , 'margin_notes_display_settings' );

		//Which side of the page should annotations display on.

		function echo_margin_select() {
			
			$option = get_option("margin_notes_display_options");
			
			if ( isset( $option['which_margin'] ) ){ $value = $option['which_margin']; } else { $value = '' ;}
			
			$html = 'The side of the page where annotations will appear.';
			$html .= '<fieldset>';
			$html .= '<label for="margin_notes_which_margin_left">Left</label>';
			$html .= '<input type="radio" value="left" name="margin_notes_display_options[which_margin]" id="margin_notes_which_margin_left" '.checked( 'left', $value, false ).'>';
			$html .= '<label for="margin_notes_which_margin_right">Right</label>';
			$html .= '<input type="radio" value="right" name="margin_notes_display_options[which_margin]" id="margin_notes_which_margin_right" '.checked( 'right', $value, false ).'></fieldset>';
			echo $html;

		}

		add_settings_field( 'margin_notes_which_margin' , 'Choose Margin', 'echo_margin_select', 'discussion','margin_notes_display_settings');

		function echo_hide_notes () {

			$option = get_option("margin_notes_display_options");
			$value = isset( $option['hide_notes'] ) ? $option['hide_notes'] : '';

			$html = '<p>Check to hide annotations on all pages that have them. 
			You will still be able to create new ones, but they won\'t show up while this box is checked.</p>';
			$html .= '<input type="checkbox" value="false" name="margin_notes_display_options[hide_notes]" id="margin_notes_hide_notes" '.checked( 'false', $value, false ).' >';
			echo $html;
		}

		add_settings_field( 'margin_notes_hide_notes' , 'Hide Notes' , 'echo_hide_notes' , 'discussion', 'margin_notes_display_settings' );
		
	}

	public function get_form_data(){

		if ( ! wp_verify_nonce( $_POST['thoughts-on-article'], 'submit-annotation') ){
			return;
		}
		
		if ( $_POST['annotation'] ) {
			$user = wp_get_current_user()->ID;
			$text = str_replace(
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
				htmlentities( sanitize_text_field( $_POST['highlight'] ) )
			);
			$annotation = sanitize_textarea_field( $_POST['annotation'] );
			$post = sanitize_text_field( $_POST['post-name'] );
			
			$annotations = get_option('annotations');

			//print_r($annotations);
			
			if ( ! $annotations[$user] ){
				$annotations[$user]=array(
					$post=>array(
						$text=>$annotation
					)
				);

				//print_r($annotations);
			} elseif ( ! $annotations[$user][$post] ){

				$annotations[$user][$post]=array(
					$text=>$annotation
				);

			} else {

				$annotations[$user][$post][$text]=$annotation;

				//print_r($annotations[$user][$post]);

			}

			update_option( 'annotations' , $annotations );
		
			//print_r($annotations);
		}

		//update_option( 'annotations' , array() );
		
		$url = get_home_url() . '/index.php/' . $post;

		wp_redirect( $url );
		
	}



	public function get_post_types(){



	}

	public function load_front_end (){

		$disableStyles = apply_filters( 'margin_notes_disable_styles', false );

		if ( $disableStyles ) {
			return;
		}

		if ( ! is_singular() ){
			return;
		}

		wp_enqueue_style( 'margin_notes_style', plugins_url( '/lib/margin-notes-style.css', __FILE__ ) );

		wp_enqueue_script('margin-notes',plugins_url('/lib/margin-notes.js',__FILE__),array('jquery') );

		$settings = get_option( 'margin_notes_display_options' );

		wp_localize_script('margin-notes', 'colors', array(
				'primary_color' => $settings['primary_color'],
				'secondary_color' => $settings['secondary_color'],
				'note_background_color' => $settings['note_background_color'],
				'tertiary_color' => $settings['tertiary_color'],
			)
		);

		wp_enqueue_script('ajax-script', plugins_url('/lib/annotation-ajax.js', __FILE__), array('jquery') );

		$nonce = wp_create_nonce( 'populate_annotations' );

		wp_localize_script( 'ajax-script' , 'ajax_obj' , array(
				'ajaxURL' => admin_url('admin-ajax.php'),
				'security' => $nonce,
			)
		);

	}

}

$Margin_Notes = Margin_Notes::get_instance();

?>