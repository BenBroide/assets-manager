<?php
class AssetsManagerPostType {
    
    public function __construct(){
    	add_action( 'init', array( $this, 'create_uploaded_files' ) ); # creates custom post type `assets`
    }
    
    public function create_uploaded_files() { # creates custom post type `uploaded_files`
		register_post_type( 'asset',
			array(
				'labels'              => array(
					'name'          => __( 'Assets Manager' ),
					'singular_name' => __( 'Assets Set' )
				),
				'public'              => true,
				'menu_position'       => 10,
				'exclude_from_search' => true,
				'show_in_menu'        => true,
				'supports'            => array( 'title', 'thumbnail' ),
				'taxonomies'          => array( 'category', 'post_tag' )
			)
		);
	}
}