<?php
/*
Plugin Name: Woocommerce HashTags
Description: Import images from instagram by hashtag, to your woocommerce product page. it be fully auto or with shortcode. the disaply include integrated light box and everything is responsive!.
Plugin URI: http://wpdevplus.com
Author: Yehuda Hassine
Author URI: http://wpdevplus.com
Version: 1.0
License: GPL2
Text Domain: wooinstags
*/

$wootags = new wootags;

class wootags {
	function __construct() {
		add_filter( 'woocommerce_get_settings_general', array( $this, 'add_clientid_to_settings') );

		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tabs') );
		add_action( 'woocommerce_product_data_panels', array( $this, 'display_instagram_product_data' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_instagram_panel_meta') );

		add_filter( 'the_content', array( $this, 'show_instagram_grid' ) );
		add_shortcode( 'wooinstags', array( $this, 'shortcode_instagram_grid' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'wp_head', array( $this, 'head_script' ) );
	}

	function load_scripts() {
		wp_enqueue_style( 'wooinstags-magnific-popup-style', plugins_url( 'css/magnific-popup.css', __FILE__ ) );
		wp_enqueue_style( 'wooinstags-main-style', plugins_url( 'css/main.css', __FILE__ ) );

		wp_enqueue_script( 'imagesloaded-script', plugins_url( 'js/jquery.imagesloaded.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'magnific-popup-script', plugins_url( 'js/jquery.magnific-popup.min.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'wookmark-script', plugins_url( 'js/jquery.wookmark.min.js', __FILE__ ), array( 'jquery' ) );
	}

	function head_script() { ?>
		<script>
			jQuery(document).ready(function($) {
		      // Prepare layout options.
		      var options = {
		      	align: 'center',
		        autoResize: true, // This will auto-update the layout when the browser window is resized.
		        container: $('#wooinstags_grid'), // Optional, used for some extra CSS styling
		        direction: 'right',
		        itemWidth: 210 // Optional, the width of a grid item
		      };

		      // Get a reference to your grid items.
		      var handler = $('#tiles li');

		      // Init lightbox
		      $('#tiles').magnificPopup({
		        delegate: 'li:not(.inactive) a',
		        type: 'image',
		        gallery: {
		          enabled: true
		        }
		      });

		      // Call the layout function after all images have loaded
		      $('#tiles').imagesLoaded(function() {
		        handler.wookmark(options);
		      });
			});
		</script>
	<?php
	}

	function add_clientid_to_settings( $settings ) {

		$settings[] = array( 
			'title' => __( 'Woocommerce Instagram Hashtag Images', 'wootags' ), 
			'type' => 'title', 
			'desc' => __( 'Please enter your client id in the next field.<br>Get it by create an app <a href="http://instagram.com/developer/clients/manage/">Here</a>.', 'wootags' ), 
			'id' => 'wootags_options' 
			);

		$settings[] = array(
				'title'    => __( 'Enter your instagram client id', 'wootags' ),
				'id'       => 'wooinstags_client_id',
				'type'     => 'text'
			);

		$settings[] = array( 'type' => 'sectionend', 'id' => 'wootags_options' );

		return $settings;
	}

	function product_data_tabs( $tabs ) {
		$tabs['instagram'] = array(
							'label'  => __( 'Instagram', 'wootags' ),
							'target' => 'instagram_product_data',
							'class'  => array(),
						);

		return $tabs;
	}

	function display_instagram_product_data() {
		echo '<div id="instagram_product_data" class="panel woocommerce_options_panel">';
		woocommerce_wp_text_input( array( 'id' => '_hashtag', 'label' => __( 'Instagram Hashtag', 'wootags' ), 'desc_tip' => 'true', 'description' => __( 'Enter the hashtag you want to fetch from instagram, without the hash (#).', 'wootags' ) ) );

		echo '</div>';
	}

	function save_instagram_panel_meta( $post_id ) {
		if ( isset( $_POST['_hashtag'] ) ) {
			update_post_meta( $post_id, '_hashtag', wc_clean( $_POST['_hashtag'] ) );
		}				
	}


	function show_instagram_grid( $content ) {
		if ( is_singular( 'product' ) ) {
			$post_id = get_the_ID();
		    $tag = get_post_meta( $post_id, '_hashtag', true );

		    if ( $tag ) {
			    $client_id = get_option( 'wooinstags_client_id' );
			    $url = 'https://api.instagram.com/v1/tags/' . $tag . '/media/recent?client_id=' . $client_id;

			    $response = wp_remote_get( $url );
			    if ( is_wp_error( $response ) ) {
   					$error = $result->get_error_message();
   					return $content . "<br>" . $error;
   				}

			    $inst_stream = wp_remote_retrieve_body( $response );
			    $results = json_decode($inst_stream, true);

			    if ( isset( $results['meta']['error_message'] ) ) {
			    	return $content . "<br>" . $results['meta']['error_message'];
			    }

			    $content .= '<div id="wooinstags_grid">
			                 <ul id="tiles">';
			    foreach($results['data'] as $item){
			        $image_link = $item['images']['low_resolution']['url'];
			        $content .= '<li><a href="' . $image_link . '"><img src="' . $image_link . '" /></a></li>';
			    }	
			    $content .= '</ul>
			                </div>';
		    }
		}

		return $content;
	}


	function shortcode_instagram_grid( $atts, $content ) {
		if ( is_singular( 'product' ) ) {
			$post_id = get_the_ID();

			$atts = shortcode_atts( array(
				'hashtag' => 'landscape',
				'count' => 12
			), $atts, 'wooinstags' );

		    $tag = $atts['hashtag'];
		    $count = $atts['count'];

		    $client_id = get_option( 'wooinstags_client_id' );
		    $url = "https://api.instagram.com/v1/tags/$tag/media/recent?count=$count&client_id=$client_id";

		    $response = wp_remote_get( $url );

		    if ( is_wp_error( $response ) ) {
					$error = $result->get_error_message();
					return $content . "<br>" . $error;
				}

		    $inst_stream = wp_remote_retrieve_body( $response );
		    $results = json_decode($inst_stream, true);

		    if ( isset( $results['meta']['error_message'] ) ) {
		    	return $content . "<br>" . $results['meta']['error_message'];
		    }

		    $content .= '<div id="wooinstags_grid">
		                 <ul id="tiles">';
		    foreach($results['data'] as $item){
		        $image_link = $item['images']['low_resolution']['url'];
		        $content .= '<li><a href="' . $image_link . '"><img src="' . $image_link . '" /></a></li>';
		    }	
		    $content .= '</ul>
		                </div>';

		}

		return $content;
	}	
}
