<?php
/**
 * Plugin Name: LJ Widget
 * Plugin URI: http://www.garymcgath.com/
 * Description: Lets you show your LiveJournal user info in a sidebar
 * Version: 0.1
 * Author: Gary McGath
 * Author URI: http://www.garymcgath.com/
 * License: MIT License
 */

define( WIDGET_ID, 'gm_lj_widget' );
define( WIDGET_NAME, 'LJ Widget' );

 // Prevent direct file access
if ( ! defined ( 'ABSPATH' ) ) {
	exit;
}

class GM_lj_widget extends WP_Widget {

	const FOAF_NS = 'http://xmlns.com/foaf/0.1/';
	const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
	const WIDGET_ID = 'gm_lj_widget';
	const WIDGET_NAME = 'LJ Widget';
	
	protected $user = false;

	protected $widget_slug = 'LJ User';
	
	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			self::WIDGET_ID, // Base ID
			__( self::WIDGET_NAME, 'text_domain' ), // Name
			array( 'description' => __( 'Widget for LiveJournal user', 'text_domain' ), ) // Args
		);
		add_action( 'wp_enqueue_scripts', array( $this, 'register_widget_styles' ) );
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// Check if there is a cached output
		$cache = wp_cache_get( $this->get_widget_slug(), 'widget' );
		if ( !is_array( $cache ) )
			$cache = array();
		if ( ! isset ( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;
		if ( isset ( $cache[ $args['widget_id'] ] ) )
			return print $cache[ $args['widget_id'] ];
		$user = $instance['user'];
		error_log ( "in widget. user = " . $user );
		
		// go on with your widget logic, put everything into a string and …
		extract( $args, EXTR_SKIP );
		$widget_string = $before_widget;
		
		// widget-specific functionality
		$widget_string .= '<h2 class="widget-title">LJ User</h2>';
		$widget_string .= '<ul class="widget_gm_lj_widget">';
		$widget_string .= $this->lj_info( $user );
		$widget_string .= '</ul>';
		
		// end widget-specific functionality
		
		ob_start();
		$widget_string .= ob_get_clean();
		$widget_string .= $after_widget;
		$cache[ $args['widget_id'] ] = $widget_string;
		wp_cache_set( $this->get_widget_slug(), $cache, 'widget' );
		print $widget_string;
	}


	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$user = ! empty( $instance['user'] ) ? $instance['user'] : 'madfilkentist';	// TODO temporary default
		?>
		<p>
		<label for="<?php echo $this->get_field_id( 'user' ); ?>"><?php _e( 'LJ user:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'user' ); ?>" 
			name="<?php echo $this->get_field_name( 'user' ); ?>" 
			type="text" value="<?php echo esc_attr( $user ); ?>">
		</p>
		<?php 
	}
	
	public function get_widget_slug() {
		return $this->widget_slug;
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['user'] = esc_attr( strip_tags( $new_instance['user'] ));
		return $instance;
	}

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function widget_textdomain() {
		load_plugin_textdomain( $this->get_widget_slug(), false, plugin_dir_path( __FILE__ ) . 'lang/' );
	}

	
	public function flush_widget_cache() {
		wp_cache_delete( $this->get_widget_slug(), 'widget' );
	}
	
	/**
	 * Registers and enqueues widget-specific styles.
	 */
	public function register_widget_styles() {
		wp_enqueue_style( $this->get_widget_slug().'-widget-styles', plugins_url( 'css/lj-widget.css', __FILE__ ) );
	}
	
	/**
	 *  Assemble the user's LiveJournal info.
	 *  Returns a sequence of li tags.
	 *  If there's a problem, returns the empty string or an error message.
	 */
	private function lj_info ( $user ) {
		$url = 'http://' . $user . '.livejournal.com/data/foaf';
		try {
			$xml = file_get_contents( $url );
			if ( !$xml ) {
				return '<li>No such user from $url</li>';
			}
			$dom = simplexml_load_string($xml);				
			if ( $dom !== false ) {
				$foafChildren = $dom->children( self::FOAF_NS );
				$person = $foafChildren->Person;
				$personChildren = $person->children( self::FOAF_NS );
				$name = $personChildren->name;
				if ( $name ) {
					$val .= '<li>Name: ' . $name . '</li>';
				}
				$nick = $personChildren->nick;
				if ( $nick ) {
					$openid = $personChildren->openid;
					if ( $openid ) {
						$atts = $openid->attributes( self::RDF_NS );
						$val .= '<li>Journal: <a href="';
						$val .= $atts['resource'];
						$val .= '" target="_blank">';
						$val .= $nick;
						$val .= '</a></li>';
					} else {
						$val .= '<li>Journal: ' . $nick . '</li>';
					}
				}
					
				return $val;
			} else 
				return '<li>Could not parse XML</li>';
			} catch ( Exception $ex ) {
				return '';
			}
	
	}

}


add_action( 'widgets_init', create_function( '', 'register_widget("GM_lj_widget");' ) );
