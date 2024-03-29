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

	private $user;

	private $settings_defaults=array(
			'submit_button_color' => '#4d42cb',
			'note_background_color' => '#4d42cb',
			'note_text_color' => '#fff',
			'which_margin' => 'right',
			'width_value' => '25',
			'width_unit' => '%',
			'display_type' => 'margins',
			'container' => '.margin-notes-container',
			'hide_notes' => false,
			'form_theme' => 'light',
	);

	private function __construct() {

		//front-end
		add_filter( 'the_content' , array( $this, 'filter_content'), 10000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_front_end'));
		add_filter( 'post_class', array($this, 'add_container_class'), 50);
		//add_action( 'admin_bar_menu', array($this, 'output_new_annotation_button'), 50);
		
		//back-end
		add_action( 'init', array( $this, 'get_user' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_back_end' ) );
		add_action( 'admin_init', array( $this, 'setup_admin_settings' ) );
		//add_action( 'wp_ajax_handle_annotations', array( $this, 'handle_annotations') );
		add_action( 'admin_post_delete_annotation', array( $this , 'delete_annotation' ) );
		add_action( 'admin_post_annotation' , array( $this, 'get_form_data') );
		add_action( 'admin_head', array( $this, 'admin_style' ) );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'add_settings_link_to_plugins_table'));
	}

	public function get_user(){
		$this->user = wp_get_current_user()->ID;
	}

	/**
	* Creates an instance of the class, or returns the instance if 
	* it already exists.
	*
	* @since 1.0.0
	*/

	public static function get_instance(){
		
		if (!self::$instance){
			self::$instance = new self;
		}
		
		return self::$instance;
	}

	public function add_container_class( $classes ){
		$settings = get_option('margin_notes_display_options',array());
		$settings = wp_parse_args($settings, $this->settings_defaults );
		
		if ( '.margin-notes-container' === $settings['container']  ){
			$classes[]='margin-notes-container';
		}
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

		add_option( "margin_notes_annotations" , array() );
		add_option( "margin_notes_users_have_seen_hint", array());


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
		delete_option( 'margin_notes_annotations' );
		delete_option( 'margin_notes_display_options ');
		delete_option( 'margin_notes_users_have_seen_hint' );
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
/*
	public function add_new_icon(){
		return '
		<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 50 50">
			<circle cx="25" cy="25" r="25" fill="#000">
				
			</circle>
			<line class="vertical" x1="25" y1="15" x2="25" y2="35" stroke="#fff" stroke-width="5"  stroke-linecap="round"/>
			<line x1="15" y1="25" x2="35" y2="25" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
			<title>'.__( 'Margin Notes: Add Annotation', 'margin-notes' ).'</title>
		</svg>
		';
	}

	public function output_new_annotation_button( WP_Admin_Bar $admin_bar ){

		$admin_bar->add_menu(array(
			'id' => 'margin_notes_add_annotation',
			'title' =>  __('New Annotation', 'margin-notes')
			//'title' => '<span class="dashicons dashicons-insert" style="font-family: \'dashicons\'; margin-right: 3px;"></span>' . __('New Annotation', 'margin-notes')
			//'title' => '<button><span class="add-new-icon">' . $this-> add_new_icon() . '</span>' . __('New Annotation', 'margin-notes') . '</button>',
		));
		//return $admin_bar;
	} 
*/
	public function add_settings_link_to_plugins_table( $actions ){
		$url = esc_url( admin_url() . '/options-reading.php#margin_notes_display_settings' );
		$link = '<a href="' . $url .'">' . __('Settings', 'margin-notes') . '</a>';
		array_unshift($actions, $link);
		return $actions;
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
		$site_annotations = get_option( 'margin_notes_annotations', array());
		
		$user = wp_get_current_user()->ID;
		if ( 0 === $user || !array_key_exists($user, $site_annotations) ) return $content;
		
		$post = get_post()->post_name;
		
		if ( 
			! array_key_exists($post, $site_annotations[$user] ) || 
			! is_singular() || 
			! current_user_can( 'annotate' )
		){
			return $content;
		} 

		$annotations = $site_annotations[$user][$post];

		$settings = get_option('margin_notes_display_options', array());
		$settings = wp_parse_args($settings, $this->settings_defaults);

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

		$margin_display = isset($settings['display_type']) && $settings['display_type'] == 'margins';

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
			$content = $annotation_html . $content;
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
	/*
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
	*/
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
		$annotations = get_option('margin_notes_annotations');

		array_splice( $annotations[$user][$post], $id, 1);
		
		update_option( 'margin_notes_annotations', $annotations );
		
		$post_obj = get_posts(
			array('name'=>$post)
		)[0];
		
		$url = get_permalink( $post_obj );

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

		$settings = get_option( 'margin_notes_display_options', array() );

		$svg = '
		<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 50 50">
			<circle cx="25" cy="25" r="25" fill="#262626">
				
			</circle>
			<line class="vertical" x1="25" y1="15" x2="25" y2="35" stroke="#fff" stroke-width="5"  stroke-linecap="round"/>
			<line x1="15" y1="25" x2="35" y2="25" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
			<title>'.__( 'Margin Notes: Add Annotation', 'margin-notes' ).'</title>
		</svg>
		';

		$direction = isset($settings['which_margin']) && $settings['which_margin'] === 'left' ? 'left' : 'right';
		$theme_class = isset($settings['form_theme'])  && $settings['form_theme'] === 'dark' ? 'mn-dark-theme' : 'mn-light-theme';

		$users_who_have_seen_the_hint = get_option('margin_notes_users_have_seen_hint', array());
		$hint_visible = !in_array($this->user, $users_who_have_seen_the_hint);

		if ($hint_visible){
			$users_who_have_seen_the_hint[] = $this->user;
			update_option('margin_notes_users_have_seen_hint', $users_who_have_seen_the_hint);
		}

		$wrapper_class = 'margin-notes-wrapper margin-notes-wrapper-' . $direction ;

		$form_class = 'margin-notes-form margin-notes-form-' . $direction . ' ' . $theme_class;
		$placeholders = $this->annotation_field_placeholders();
		ob_start();
		?>
			<div id="margin-notes-add-button-assembly" class="margin-notes-add-<?php echo esc_attr($direction); ?>">
				<?php if ($hint_visible): ?>
				<div id="margin-notes-hint">
					<p><?php esc_html_e('Click here to add an annotation!') ?></p>
				</div>
				<?php endif; ?>
				<button id="margin-notes-add" class="margin-notes-add margin-notes-button">
					<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 50 50">
						<circle cx="25" cy="25" r="25" fill="#262626"></circle>
						<line class="vertical" x1="25" y1="15" x2="25" y2="35" stroke="#fff" stroke-width="5"  stroke-linecap="round"/>
						<line x1="15" y1="25" x2="35" y2="25" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
						<title><?php esc_html_e( 'Margin Notes: Add Annotation', 'margin-notes' ) ?></title>
					</svg>
				</button>
			</div>
		<?php
		$html = ob_get_contents();
		ob_end_clean();				
		$html .= sprintf( '<div id="margin-notes-wrapper" class="%s">', esc_attr( $wrapper_class ) );
		$html .= sprintf( 
					'<form id="margin-notes-form" class="%s" action="%s" method="post">', 
					esc_attr( $form_class ), 
					esc_url( admin_url('admin-post.php') ) 
				);
		$html .= sprintf( '<input type="text" name="post-name" id="post-name" readonly="readonly" value="%s">', esc_attr(get_post()->post_name) );
		$html .= '<label>'.__( 'Copy and paste source text for your annotation.', 'margin-notes' );
		$html .= sprintf('<input name="highlight" id="margin-notes-highlight-input" type="text" placeholder="%s"></label>', $placeholders[0]);
		$html .= '<p id="highlight-error"></p>';
		$html .= '<label>'.__( 'Create an annotation.', 'margin-notes' );
		$html .= sprintf( 
					'<textarea name="annotation" rows="10" id="annotation-input" placeholder="%s" type="text">',
				$placeholders[1],
		);
		$html .= '</textarea></label>';
		$html .= '<label>'.__('Delete all annotations on this page.', 'margin-notes' );
		$html .= '<input type="checkbox" id="deleteAll" value="delete" name="delete">';
		$html .= renderCheck();
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

	private function annotation_field_placeholders(){
		$highlight = array(
			esc_attr__('The letter from Jamaica', 'margin-notes'),
			esc_attr__('The secession of the plebes', 'margin-notes'),
			esc_attr__('The marvelous automobile', 'margin-notes'),
			esc_attr__('Le Marseillaise', 'margin-notes'),
		);

		$placeholders = array(
			esc_attr__( 'Written at the nadir of our hero\'s hopes...', 'margin-notes' ),
			esc_attr__('This was in fact the first time in recorded history that...', 'margin-notes' ),
			esc_attr__( '20 years later, when sliced bread was invented, many would say...', 'margin-notes' ),
			esc_attr__( 'In the annals of great patriotic songs...', 'margin-notes' ),
		);

		$random = rand(0,3);
		return array($highlight[$random], $placeholders[$random]);
	}

	/**
	* gets attributes and strings to populate the margin notes settings section in Settings -> Reading
	*
	* @since 1.0.0
	*/

	public function settings_parameters() {

		$sections = array(
			'display_settings' => array(
				'title' => __( 'Margin Notes Display Settings', 'margin-notes' ),
				'callback' => array($this, 'echo_display_section_instructions'),
				'page' => 'reading'
			),
			'color_settings' => array(
				'title' => __( 'Margin Notes Color Settings', 'margin-notes' ),
				'callback'=> array($this, 'echo_color_section_instructions'),
				'page' => 'reading'
			)
		);

		$fields = array(
			array(
				'name' => 'form_theme',
				'title' => __( 'Form Theme', 'margin-notes' ),
				'type' => 'radio_group',
				'section' => 'color_settings',
				'desc' => __( 'Color scheme for the "Add Annotation" form', 'margin-notes'),
				'values' => array( 'light', 'dark' )
			),
			array(
				'name' => 'submit_button_color',
				'title' => __( 'Form Submit Button Color', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __('Color for submit button for "Add Annotation" form.', 'margin-notes'),
			),
			array(
				'name' => 'note_background_color',
				'title' => __( 'Annotation Background Color', 'margin-notes' ),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __( 'Color for background of the notes.', 'margin-notes')
			),
			array(
				'name' => 'note_text_color',
				'title' => __( 'Annotation Text Color', 'margin-notes'),
				'type' => 'text_input',
				'section' => 'color_settings',
				'desc' => __( 'Color for the text of the notes.', 'margin-notes')
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
				"div.entry-content". Feel free to leave this field blank if you\'re not sure what to write.', 'margin-notes')
			),
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
				'desc' => __( 'The side of the page where annotations will appear and from which the "New Annotation" form will slide out.', 'margin-notes'),
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
		$value = esc_attr($setting);

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
		
		foreach ( ['submit_button_color', 'note_background_color', 'note_text_color'] as $field ){
			$input[$field] = sanitize_hex_color( $input[$field] );
		}

		$radios = array(
			array( 'display_type', 'margins', 'tooltips' ),
			array( 'which_margin', 'left', 'right' ),
			array( 'form_theme', 'light', 'dark'),
		);

		foreach ( $radios as $field ){
			$input[$field[0]] = $this->sanitize_radio( $input[$field[0]], $field[1], $field[2] );
		}
		
		$input['container'] 	 = sanitize_text_field( $input['container'] );
		if (0 == strlen($input['container'])){
			$input['container'] = $this->settings_defaults['container'];
		}

		$input['width_value']  = intval( $input['width_value'] );
		if (0 == $input['width_value']){
			$input['width_value'] = $this->settings_defaults['width_value'];
		}
		
		$width_units = array( 'px', '%',  'px');
		if ( ! in_array( $input['width_unit'], $width_units ) ){
			$input['width_unit'] = '%';
		}
		
		if ( !is_bool( $input['hide_notes'] ) ){
			$input['hide_notes'] = false;
		}

		return $input;
	}

	/**
	* sanitizes a radio input by insuring the returned value is one of the two provided.
	*
	* @since 1.0.0
	*/

	private function sanitize_radio( $input, $val1, $val2 ) {
		return $input == $val1 ? $val1 : $val2;
	}

	/**
	 * Outputs description for display settings section.
	 * 
	 * @since 1.0.0
	*/

	public function echo_display_section_instructions($args){
		?>
		<p id="<?php echo esc_attr($args['id']); ?>">
			<?php esc_html_e( 'Customize the UI for your sites\' annotations.', 'margin-notes' ) ?>
		</p>
		<?php
	}

	/**
	 * Outputs description for colors settings section.
	 * 
	 * @since 1.0.0
	*/

	public function echo_color_section_instructions(){
		 ?>
		<p><?php esc_html_e( 'You can specify as many as four theme colors. Please use hex format (ie., "#123456" or "#BBB").', 'margin-notes')?></p>
		<?php
	}

	/**
	* Registers margin notes settings and prints html for settings fields
	*
	* @since 1.0.0
	*/

	public function setup_admin_settings (){
		
		register_setting(
			'reading',
			'margin_notes_display_options',
			array(
				'type' => 'array',
				'description' => 'display options for margin notes',
				'sanitize_callback' => array( $this, 'sanitize_settings_fields' )
			)
		);

		$settings = get_option( 'margin_notes_display_options', array() );
		$display_type = isset($settings['display_type']) ? $settings['display_type'] : 'margins';

		['sections' => $sections, 'fields' => $fields ] = $this->settings_parameters();

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

			$setting = isset($settings[ $field['name'] ]) ? $settings[$field['name']] : '';

			add_settings_field( 
				'margin_notes_'.$field['name'],
				$field['title'],
				array( $this, $field['type'] ),
				'reading',
				'margin_notes_'.$field['section'],
				array(
					'name' => $field['name'],
					'description' => $field['desc'],
					'setting' => $setting,
					'values' => isset( $field['values'] ) ? $field['values'] : array(),
					'class' => 'margin_notes_' .$field['name'].' '.$display
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

		$annotations = get_option('margin_notes_annotations');
		$user = wp_get_current_user()->ID;
		
		if ( isset( $_POST['delete'] ) ){
			$annotations[$user][$post]=array();
			update_option( 'margin_notes_annotations' , $annotations );
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
			update_option( 'margin_notes_annotations' , $annotations );
		}
		
		wp_redirect( $url );
	}

	/**
	 * Increases or decreases the brightness of a color by a percentage of the current brightness.
	 *
	 * @param   string  $hexCode        Supported formats: `#FFF`, `#FFFFFF`, `FFF`, `FFFFFF`
	 * @param   float   $adjustPercent  A number between -1 and 1. E.g. 0.3 = 30% lighter; -0.4 = 40% darker.
	 *
	 * @return  string
	 *
	 * @author  maliayas (stack overflow username)
	 */
	public function adjust_brightness($hexCode, $adjustPercent) {
		$hexCode = ltrim($hexCode, '#');

		if (strlen($hexCode) == 3) {
				$hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
		}

		$hexCode = array_map('hexdec', str_split($hexCode, 2));

		foreach ($hexCode as & $color) {
				$adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
				$adjustAmount = ceil($adjustableLimit * $adjustPercent);

				$color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
		}

		return '#' . implode($hexCode);
	}

	/**
	* Creates the css for any plugin styles that depend on user settings
	*
	* @since 1.0.0
	*/

	public function build_inline_styles(){

		$options = get_option('margin_notes_display_options',array());
		$options = wp_parse_args( $options, $this->settings_defaults );
		$options = array_map('esc_attr', $options);
		
		[ 
			'submit_button_color' => $submit_button_color, 
			'note_background_color' => $note_background_color, 
			'note_text_color' => $note_text_color, 
			'display_type' => $display_type, 
			'which_margin' => $which_margin, 
			'width_value' => $width_value, 
			'width_unit' => $width_unit,
		] = $options;
	
		$other_margin = 'left' == $which_margin ? 'right' : 'left';
		
		$width = $width_value . $width_unit;
		$form_wrapper_offset = -1 * $width_value . $width_unit;

		if ( 'margins' === $display_type ){

			$annotation_style = "
			.annotation{
				border-$other_margin: 3px solid $note_text_color;
				color: $note_text_color;
				background: $note_background_color;
				width: $width!important;
			}		
		";

		} else { 
			$annotation_style = "
			.annotation-tooltip div.tip-content{
				background: $note_background_color;
			}

			.annotation-tooltip div.tip-content p{
				color: $note_text_color;
			}

			.tri{
				border-bottom: 10px solid $note_background_color;
			}
		";

		}

		$highlight_style = "
			.mn-highlight{
				color: $note_background_color;
			}
		"; 

		$delete_style = "
			.mn-delete-annotation{
				color: $note_text_color;
			}
		";
		
		$submit_button_dark = $this->adjust_brightness($submit_button_color, -.1);
		$submit_button_light = $this->adjust_brightness($submit_button_color, .1);
		
		//position form just off page on user's choice of left or right
		$form_style = "
		.margin-notes-wrapper{
			width: $width;
			$which_margin: $form_wrapper_offset;
		}

		.margin-notes-wrapper.expand{
			$which_margin: 0;
		}

		#margin-notes-highlight-input:focus,
		#margin-notes-form.margin-notes-form textarea#annotation-input:focus{
			border: 1px solid $submit_button_color;
		}

		#margin-notes-submit{
			background: linear-gradient(to top, $submit_button_dark, $submit_button_light);
		}
		
		#margin-notes-submit:hover,
		#margin-notes-submit:active{
			color: $submit_button_color;
			border: 2px solid $submit_button_color;
		}


		";

		return $annotation_style . $highlight_style . $delete_style . $form_style;
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
		wp_enqueue_style( 'dashicons' );

		$user_styles = $this->build_inline_styles();
		wp_add_inline_style( 'margin_notes_style', wp_kses( $user_styles, array("\'", '\"') ) );

		/*
		Get user, annotaions, and post objects, 
		avoiding anything undefined along the way.
		*/

		$user = wp_get_current_user()->ID;
		if ( 0 === $user ) return;

		$site_annotations = get_option('margin_notes_annotations', array());

		//if (! array_key_exists($user, $site_annotations)) return;

		$post_obj 	= get_post();
		$post 			= $post_obj->post_name;

		if ( isset($site_annotations[$user]) && isset($site_annotations[$user][$post])){
			$annotations = $site_annotations[$user][$post];
		} else{
			$annotations = array();
		}
		
		//enqueue script
		wp_enqueue_script('margin-notes',plugins_url('/lib/margin-notes.js',__FILE__),array('jquery') );
		$settings = get_option( 'margin_notes_display_options', array() );
		$settings = wp_parse_args( $settings, $this->settings_defaults );
		
		$note_background_color = esc_attr($settings['note_background_color']);
		$note_text_color = esc_attr($settings['note_text_color']);
		$width_value =esc_attr($settings['width_value']);
		$width_unit = esc_attr($settings['width_unit']);
		$container = esc_attr($settings['container']);
		$display_type = 'margins' == $settings['display_type'] ? 'margins' : 'tooltips';

		$nonce 			= wp_create_nonce( 'populate_annotations' );
		$content      	= apply_filters( 'the_content', get_the_content( null, false, $post_obj ) );
		$delete_url 	= wp_nonce_url( admin_url('admin-post.php'), 'delete-annotation', 'delete-annotation' );

		wp_localize_script('margin-notes', 'settings', array(
				'note_background_color' => $note_background_color,
				'note_text_color' => $note_text_color,
				'width_value' => $width_value,
				'width_unit' => $width_unit,
				'container' => $container,
				'ajaxURL' => admin_url('admin-ajax.php'),
				'security' => $nonce,
				'display_type' => $display_type,
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