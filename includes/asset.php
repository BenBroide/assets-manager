<?php
class AssetsManagerAssetCheck {

    public function asset_check( $asset_id ){
        
        if ( ! $this->is_published( $asset_id ) ) {
			echo 'This file has expired.';
			exit();
		}

		if ( ! $this->is_enabled( $asset_id ) ) {
			echo 'This file has expired.';
			exit();
		}

		if ( $this->has_expired( $asset_id ) ) {
			echo 'This file has expired.';
			exit();
		}

		if ( $this->requires_login( $asset_id ) ) {
			wp_redirect( wp_login_url( get_permalink( $asset_id ) ) );
			exit();
		}
    }
    
	public function is_published( $asset_id ) {
		$asset    = get_post( $asset_id );
		$assetset = get_post( $asset->post_parent );

		return ( 'publish' === $assetset->post_status );
	}

	public function is_enabled( $asset_id ) {
		return ( 'true' === get_post_meta( $asset_id, 'enabled', true ) );
	}
	
	public function has_expired( $asset_id ) {
		$expires = get_post_meta( $asset_id, 'expires', true );
		// it never expires
		if ( 'never' === $expires ) {
			return false;
		}

		// now is before the expiration date
		$date = date_create( get_post_meta( $asset_id, 'base_date', true ) );
		date_add( $date, date_interval_create_from_date_string( $expires ) );
		if ( date_format( $date, 'U' ) < date( 'U' ) ) {
			return true;
		}

		return false;
	}

	public function requires_login( $asset_id ) {
		return ( 'true' === get_post_meta( $asset_id, 'secure', true ) && ! is_user_logged_in() );
	}

    public function get_expiry_date( $asset_id, $format = 'Y-m-d' ) {
		$expires = get_post_meta( $asset_id, 'expires', true );

		if ( 'never' === $expires ) {
			return $expires;
		}

		$date = date_create( get_post_meta( $asset_id, 'base_date', true ) );
		date_add( $date, date_interval_create_from_date_string( $expires ) );

		return date_format( $date, $format );
	}

}