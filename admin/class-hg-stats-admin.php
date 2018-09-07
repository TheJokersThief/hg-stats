<?php

use Jenssegers\Blade\Blade;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://iamevan.me
 * @since      1.0.0
 *
 * @package    Hg_Stats
 * @subpackage Hg_Stats/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Hg_Stats
 * @subpackage Hg_Stats/admin
 * @author     Evan Smith <evan.smith@hostedgraphite.com>
 */
class Hg_Stats_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $hg_stats    The ID of this plugin.
	 */
	private $hg_stats;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $hg_stats       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $hg_stats, $version ) {

		$this->hg_stats = $hg_stats;
		$this->version = $version;
		$this->blade = new Blade(  __DIR__ . '/views',  __DIR__ . '/cache');

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hg_Stats_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hg_Stats_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->hg_stats, plugin_dir_url( __FILE__ ) . 'css/hg-stats-css-grid.css', array(), 2, 'all' );
		wp_enqueue_style( $this->hg_stats, plugin_dir_url( __FILE__ ) . 'css/hg-stats-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hg_Stats_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hg_Stats_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->hg_stats, plugin_dir_url( __FILE__ ) . 'js/hg-stats-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function add_settings_page(){
		add_options_page(
			'HG Stats Settings',
			'HG Stats Settings',
			'manage_options',
			'hg_stats',
			array(
				$this,
				'settings_page'
			)
		);
	}

	public function settings_page(){
		$current_options = $this->handle_form_save();
		echo $this->blade->make('settings', $current_options);
	}

	private function handle_form_save(){
		$options = [
			'hg_stats_api_key'
		];

		$return_options = [];
		if( isset($_POST['form_submitted']) ){
			if (!current_user_can('manage_options')) {
		        wp_die('Unauthorized user');
		    }

		    foreach( $options as $option ){
			    if (isset($_POST[$option])) {
			        update_option($option, $_POST[$option]);
			        $return_options[$option] = $_POST[$option];
			    }
		    }
		}

		foreach( $options as $option ){
			if( !in_array($option, $return_options) ){
				$return_options[$option] = get_option($option, '');
			}
		}

		return $return_options;
	}

}
