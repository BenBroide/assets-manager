<?php
class AssetsManagerScriptsStyles {
    
    public function __construct(){
   		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) ); # load admin js
    }
    
    public function load_admin_scripts() {
		global $post;

		if ( is_admin() && is_object( $post ) && 'asset' === $post->post_type ) {

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'plupload-all', array( 'jquery' ) );
			wp_enqueue_script( 'wp_assets', plugin_dir_url( __FILE__ ) . '../js/wp-assets-manager.js', array('jquery','jquery-ui-sortable','plupload-all'), 201510 );

			wp_enqueue_style( 'wp_assets_admin', plugin_dir_url( __FILE__ ) . '../css/wp-assets-manager.css' );

			wp_localize_script( 'wp_assets', 'AM_Ajax', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'amNonce' => wp_create_nonce( 'update-amNonce' )
				)
			);
		}
	}
}