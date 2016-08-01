<?php
/*
	Plugin Name: Assets Manager for WordPress
	Plugin URI: http://www.jackreichert.com/2014/01/12/introducing-assets-manager-for-wordpress/
	Description: Plugin creates an assets manager. Providing a self hosted file sharing platfrom.
	Version: 0.6.2
	Author: Jack Reichert
	Author URI: http://www.jackreichert.com
	License: GPL3
*/

$wp_assets_manager = new wp_assets_manager();

class wp_assets_manager {

	/* 
	 * Assets Manager class construct
	 */
	public function __construct() {

		$this->includes();

		new AssetsManagerPostType;
		$assets_manager_meta_boxes = new AssetsManagerMetaBoxes;
		new AssetsManagerScriptsStyles;
		$asset = new AssetsManagerAssetCheck;
		
		register_activation_hook(   __FILE__, array( $this, 'wp_assets_manager_activate' ) ); # plugin activation
		register_deactivation_hook( __FILE__, array( $this, 'wp_assets_manager_deactivate' ) ); # plugin deactivation

		add_action( 'wp',                   array( $this, 'check_url' ), 1 ); # serve the file
		add_action( 'wp_ajax_update_asset', array( $this, 'update_asset_action_func' ) ); # plupload ajax function_exists
		add_action( 'wp_ajax_trash_asset',  array( $this, 'trash_asset_action_func' ) ); # detach asset from post
		add_action( 'wp_ajax_order_assets', array( $this, 'order_assets_action_func' ) ); # detach asset from post		
		add_action( 'pre_asset_serve',      array( $asset, 'asset_check' ), 10, 1 ); # check asset criteria
		add_filter( 'the_content',          array( $this, 'single_asset_content' ) ); # list files on single
		add_action( 'save_post',            array( $this, 'save_assets' ) ); # makes sure post_name is the hash

	}

	public function includes() {
		include_once ( plugin_dir_path( __FILE__ ) . 'includes/post-types.php' );
		include_once ( plugin_dir_path( __FILE__ ) . 'includes/meta-boxes.php' );
		include_once ( plugin_dir_path( __FILE__ ) . 'includes/scripts-styles.php' );
		include_once ( plugin_dir_path( __FILE__ ) . 'includes/asset.php' );
	}

	/* 
	 * Plugin activation
	 */
	public function wp_assets_manager_activate() {
		global $wpdb;
		$table_name = $wpdb->prefix . "assets_log";
		$sql        = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,			
			uID VARCHAR(7) NOT NULL DEFAULT 0,
			aID int(11) NOT NULL DEFAULT 0,
			count int(11) NOT NULL DEFAULT 0,
			UNIQUE KEY id (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		AssetsManagerPostType::create_uploaded_files();
		flush_rewrite_rules();
	}

	

	/* 
	 * Plugin deactivation
	 */

	public function wp_assets_manager_deactivate() {
		flush_rewrite_rules();
	}

	/* 
	 * When: Pre getting post headers
	 * What: Checks to see if file fits requirements, serves file
	 */
	public function check_url() {
		global $wpdb, $wp_query;

		// skip if not asset file
		if ( ! $wp_query->is_attachment || get_post_type( $wp_query->posts[0]->post_parent ) != 'asset' ) {
			return false;
		}


		// get attachment id
		$asset_id = $wp_query->posts[0]->ID;

		// checks to see if asset is active, tie in plugins can hook here
		do_action( 'pre_asset_serve', $asset_id );

		$this->log_asset( $asset_id );

		if ( headers_sent() ) {
			die( 'Headers Sent' );
		}

		$upload_dir = wp_upload_dir();
		$filepath = get_post_meta($asset_id, '_wp_attached_file', true);
        $path       = $upload_dir['basedir'] . '/' . $filepath;
        $filename   = end( explode( '/', $filepath ) );

		$ext = end( explode( '.', $filename ) );
		switch ( $ext ) {
			case 'xlsx':
				$mm_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
				break;
			case 'docx':
				$mm_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
				break;
			default:
				$mm_type = ( $wp_query->posts[0]->post_mime_type == '' ) ? 'text/plain' : $wp_query->posts[0]->post_mime_type;
		}

		header( "HTTP/1.1 200 OK" );
		header( "Pragma: public" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: private", false );
		header( "Content-Description: File Transfer" );
		header( "Content-Type: " . $mm_type );
		if ( strpos( $mm_type, 'msword' ) > 0 || strpos( $mm_type, 'ms-excel' ) || strpos( $mm_type, 'officedocument' ) ) {
			$cd = 'attachment';
		} else {
			$cd = 'inline';
		}
		header( 'Content-Disposition: ' . $cd . '; filename="' . $wp_query->posts[0]->post_title . "." . $ext . '"' );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: " . (string) ( filesize( $path ) ) );

		ob_clean();
		flush();
		readfile( $path );

		exit();

	}

	private function log_asset( $aID ) {

		global $wpdb;
		$uID        = get_current_user_id();
		$table_name = $wpdb->prefix . "assets_log";

		$query  = $wpdb->prepare( "SELECT count FROM $table_name WHERE aID = %d AND uID = %d;", $aID, $uID );
		$result = $wpdb->get_results( $query, ARRAY_A );

		if ( 0 == count( $result ) ) {
			$result = $wpdb->insert( $table_name,
				array( 'uID' => $uID, 'aID' => $aID, 'count' => 1 ),
				array( '%s', '%s', '%d' )
			);
		} else {
			$count = ( isset( $result[0]['count'] ) ) ? intval( $result[0]['count'] ) + 1 : 1;
			$wpdb->update(
				$table_name,
				array( 'count' => $count ),
				array( 'uID' => $uID, 'aID' => $aID ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}

	}

	
	
	private function get_asset_values( $aID ) {
		$meta_vals = array();
		foreach ( AssetsManagerMetaBoxes::get_meta_keys() as $key ) {
			$meta_vals[ $key ] = get_post_meta( $aID, $key, true );
		}

		return $meta_vals;
	}

	
	

	public function update_asset_action_func() {
		// checks nonce
		$nonce = $_POST['amNonce'];
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!' );
		}

		$post_vals    = $_POST['vals']['post'];
		$current_post = get_post( $post_vals['ID'] );
		$post_hash    = get_post_meta( $post_vals['ID'], 'hash', true );
		$post_hash    = ( ( '' !== $post_hash ) ? $post_hash : hash( 'CRC32', $current_post->ID . $post_vals['title'] ) );
		add_post_meta( $post_vals['ID'], 'hash', $post_hash, true );
		$update_post = array(
			'ID'          => $current_post->ID,
			'post_title'  => $post_vals['title'],
			'post_name'   => $post_hash,
			'post_status' => ( 'auto-draft' === $current_post->post_status ) ? 'draft' : $current_post->post_status
		);
		wp_update_post( $update_post );

		$asset_vals    = $_POST['vals']['asset'];
		$current_asset = get_post( $asset_vals['ID'] );
		$asset_hash    = get_post_meta( $current_asset->ID, 'hash', true );
		$asset_hash    = ( ( '' !== $asset_hash ) ? $asset_hash : hash( 'CRC32', $current_asset->ID . $current_asset->post_title ) . '.' . substr( strrchr( $current_asset->guid, '.' ), 1 ) );
		add_post_meta( $current_asset->ID, 'hash', $asset_hash, true );

		$path     = get_attached_file( $asset_vals['ID'] );
		$pathinfo = pathinfo( $path );
		$newfile  = $pathinfo['dirname'] . "/" . $asset_hash;
		rename( $path, $newfile );
		update_attached_file( $asset_vals['ID'], $newfile );

		$update_asset = array(
			'ID'         => $current_asset->ID,
			'post_title' => $asset_vals['name'],
			'post_name'  => $asset_hash
		);
		wp_update_post( $update_asset );

		$meta_vals = $this->update_asset_values( $asset_vals );

		$asset_vals['has_expired'] = AssetsManagerAssetCheck::has_expired( $current_asset->ID );
		$asset_vals['expiry_date'] = AssetsManagerAssetCheck::get_expiry_date( $current_asset->ID );

		$response = array(
			'post_vals'  => array(
				'post_name'   => $update_post['post_name'],
				'url'         => AssetsManagerMetaBoxes::get_asset_link( $current_asset->ID ),
				'post_status' => $update_post['post_status']
			),
			'asset_vals' => $asset_vals
		);

		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit();
	}

	private function update_asset_values( $asset_vals ) {
		$meta_vals = AssetsManagerMetaBoxes::get_asset_values( $asset_vals['ID'] );
		foreach ( AssetsManagerMetaBoxes::get_meta_keys() as $key ) {
			delete_post_meta( $asset_vals['ID'], $key );
			if ( isset( $asset_vals[ $key ] ) && $asset_vals[ $key ] != '' ) {
				$meta_vals[ $key ] = $asset_vals[ $key ];
			}
			add_post_meta( $asset_vals['ID'], $key, $meta_vals[ $key ], true );
		}

		return $meta_vals;
	}

	public function trash_asset_action_func() {
		// checks nonce
		$nonce = $_POST['amNonce'];
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!' );
		}

		$update_asset = array(
			'ID'          => $_POST['ID'],
			'post_parent' => 0
		);
		wp_update_post( $update_asset );

		header( 'Content-Type: application/json' );
		echo json_encode( 'success' );
		exit();
	}

	public function order_assets_action_func() {
		// checks nonce
		$nonce = $_POST['amNonce'];
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!' );
		}

		foreach ( $_POST['order'] as $order => $id ) {
			delete_post_meta( $id, 'order' );
			add_post_meta( $id, 'order', $order, true );
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $_POST['order'] );
		exit();
	}

	public function single_asset_content( $content ) {
		global $post;
		$asset_check = new AssetsManagerAssetCheck;
		if ( 'asset' === $post->post_type ) {
			$attachments = get_posts( array(
				'post_parent'    => $post->ID,
				'post_type'      => 'attachment',
				'meta_query'     => array(
					array(
						'key'     => 'enabled',
						'value'   => 'true',
						'compare' => 'IN'
					)
				),
				'order'          => 'ASC',
				'orderby'        => 'meta_value_num',
				'meta_key'       => 'order',
				'posts_per_page' => - 1
			) );

			$content .= '<hr><ul>';
			foreach ( $attachments as $i => $asset ) {
				if ( $asset_check->is_published( $asset->ID ) && $asset_check->is_enabled( $asset->ID ) && ! $asset_check->has_expired( $asset->ID ) && ! $asset_check->requires_login( $asset->ID ) ) {
					$content .= '<li><a href="' . AssetsManagerMetaBoxes::get_asset_link( $asset->ID ) . '" target="_BLANK">' . $asset->post_title . '</a> <i>(' . get_post_meta( $asset->ID, 'ext', true ) . ')</i></li>';
				}
			}
			$content .= '</ul>';
		}

		return $content;
	}
	
	
	public function save_assets( $post_id ) {
		if ( 'asset' !== get_post_type( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		$post_hash = ( ( '' !== $post->post_name ) ? $post->post_name : hash( 'CRC32', $post->ID . $post->post_title ) );
		$post_name = get_post_meta( $post_id, 'hash', true );
		if ( $post_name == '' ) {
			$post_name = $post_hash;
			add_post_meta( $post_id, 'hash', $post_hash, true );
		}

		if ( $post->post_name != $post_name ) {
			$update = array(
				'ID'        => $post_id,
				'post_name' => $post_name
			);
			wp_update_post( $update );
		}

	}

}
		