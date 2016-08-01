<?php
class AssetsManagerMetaBoxes {
	
    private static $meta_keys = array( 'expires', 'secure', 'order', 'enabled', 'base_date', 'ext', 'order' );
	
    public function __construct(){
        add_action( 'add_meta_boxes', array( $this,	'assets_manager_register_meta_box'	) ); # creates meta for uploading, managing assets
    }
    
	public function get_meta_keys(){
		return self::$meta_keys;
	}   
	
    public function assets_manager_register_meta_box() { # meta box on plupload page
		add_meta_box( 'upload_assets', __( 'Upload Assets', 'upload_assets_textdomain' ), array(
			$this,
			'assets_manager_upload_meta_box'
		), 'asset', 'normal' );
		add_meta_box( 'attached_assets', __( 'Attached Assets', 'attached_assets_textdomain' ), array(
			$this,
			'assets_manager_attached_meta_box'
		), 'asset', 'normal' );
	}


	public function assets_manager_upload_meta_box() {
		global $post;
		media_upload_form(); ?>
		<div id="filelist" class="assets"></div>
		<button id="upload_asset" class="button button-large hidden">Upload</button>
		<input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
		<input type="hidden" name="post_url" value="<?php echo $post->post_name; ?>">
		<?php
	}

	public function assets_manager_attached_meta_box() {
		global $post;
		$attachments = get_posts( array(
			'post_parent'    => $post->ID,
			'post_type'      => 'attachment',
			'order'          => 'ASC',
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'order',
			'posts_per_page' => - 1
		) ); ?>
		<div class="assets">
			<ul>
				<?php foreach ( $attachments as $i => $asset ) :

					// get stats
					global $wpdb;
					$aID        = $asset->ID;
					$table_name = $wpdb->prefix . "assets_log";
					$query      = $wpdb->prepare( "SELECT SUM(count) as hits FROM $table_name WHERE aID = %d;", $aID );
					$stats      = current( $wpdb->get_results( $query, ARRAY_A ) );

					// prepare meta vals
					$meta_vals = $this->get_asset_values( $asset->ID );
					$expires   = $meta_vals['expires'];
					$link      = $this->get_asset_link( $asset->ID ); ?>
					<li id="<?php echo $asset->ID; ?>" class="asset">
						<div class="niceName">
							<input type="text" disabled="disabled" value="<?php echo $asset->post_title; ?>"
							       class="assetVal">.<span class="fileExt"><?php echo $meta_vals['ext']; ?></span>
						</div>
						<hr>
						<div class="assetMeta">
							When should this file expire? <span class="expires"> <input type="number"
							                                                            disabled="disabled"
							                                                            value="<?php echo $expires[0]; ?>"
							                                                            class="<?php echo ( $expires[0] == 0 ) ? 'hidden' : ''; ?> timeLen assetVal">
								<select class="timeTerm assetVal" disabled="disabled">
									<option <?php echo ( strpos( $expires, 'day' ) !== false ) ? 'selected="selected"' : ''; ?>
										value="day">Day(s)
									</option>
									<option <?php echo ( strpos( $expires, 'week' ) !== false ) ? 'selected="selected"' : ''; ?>
										value="week">Week(s)
									</option>
									<option <?php echo ( strpos( $expires, 'month' ) !== false ) ? 'selected="selected"' : ''; ?>
										value="month">Month(s)
									</option>
									<option <?php echo ( strpos( $expires, 'year' ) !== false ) ? 'selected="selected"' : ''; ?>
										value="year">Year(s)
									</option>
									<option <?php echo ( strpos( $expires, 'never' ) !== false ) ? 'selected="selected"' : ''; ?>
										value="never">Never
									</option>
								</select>
							</span>
							<i<?php echo( ( AssetsManagerAssetCheck::has_expired( $asset->ID ) ) ? ' class="expired"' : '' ); ?>>(<?php echo AssetsManagerAssetCheck::get_expiry_date( $asset->ID ); ?>
								)</i><br>
							Secure this file? <input class="secureFile assetVal" type="checkbox"
							                         disabled="disabled" <?php echo ( 'true' === $meta_vals['secure'] ) ? 'checked="checked"' : ''; ?>
							<br>
							Enable this file? <input class="enableFile assetVal" type="checkbox"
							                         disabled="disabled" <?php echo ( 'true' === $meta_vals['enabled'] ) ? 'checked="checked"' : ''; ?>><br>
							Link: <input class="assetLink" readonly="readonly" type="text"
							             value="<?php echo ( 'publish' == $post->post_status ) ? $link : 'Please publish to activate links'; ?>">
							<input type="hidden" name="base_date" class="baseDate"
							       value="<?php echo $meta_vals['base_date']; ?>">
							<?php if ( 'publish' == $post->post_status ): ?>
								<a href="<?php echo $link; ?>" target="_BLANK">view</a>
							<?php endif; ?>
							<div class="assetHits">
								Hits: <?php echo is_null( $stats['hits'] ) ? '0' : $stats['hits']; ?></div>
						</div>
						<span class="edit corner" title="remove">edit</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
	
	public function get_asset_values( $aID ) {
		$meta_vals = array();
		foreach (  self::get_meta_keys()  as $key ) {
			$meta_vals[ $key ] = get_post_meta( $aID, $key, true );
		}
		return $meta_vals;
	}
	
	public function get_asset_link( $asset_id ) {
		$link = get_permalink( $asset_id );
		if ( 'attachment' == get_post_type( $asset_id ) && 'asset' == get_post_type( wp_get_post_parent_id( $asset_id ) ) ) {
			return strrev( implode( '.', explode( '-', strrev( $link ), 2 ) )  );
		}

		return $link;
	}
	
}