<?php
/*
 * Plugin Name: Add Image Size
 * Plugin URI: GUI for add_image_size()
 * Description:
 * Version:
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: add-image-size
 * DomainPath:
 * Network:
 */

$add_image_size = new Add_Image_Size();

class Add_Image_Size {

	var $page_name;
	var $td = 'add-image-size'; // text domain

	function __construct() {
		add_action( 'admin_init', array( &$this, 'register_options' ) );
		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_action( 'wp_ajax_get_uniqid', array( &$this, 'get_uniqid_cb' ) );

		$sizes = get_option( 'ais-images', false );
		if ( $sizes ) foreach ( $sizes as $image_deets ) {
			add_image_size( $image_deets['name'], $image_deets['width'], $image_deets['height'], $image_deets['crop'] );
		}
	}

	function register_options() {
		register_setting( 'add-image-size', 'ais-images', array( &$this, 'sanitize' ) );

		add_settings_section( 'ais-section', __( 'Image Size Properties', $this->td ), function() { echo ''; }, $this->page_name );
		add_settings_field( 'ais-image-row', __( 'Images:', $this->td ), array( &$this, 'field' ), $this->page_name, 'ais-section', get_option( 'ais-images', false ) );
	}

	function sanitize( $input ) {
		$newinput = array();
		foreach( $input as $k => $image_deets ) {

			extract( array_map( 'trim', $image_deets ) );

			if ( empty( $name ) || empty( $width ) || empty( $height ) ) {
				unset( $input[ $k ] ); continue;
			}

			$name = sanitize_title( $name );
			$width = (int) $width;
			$height = (int) $height;
			$crop = (bool) $crop;

			if ( $width < 1 || $height < 1 ) {
				unset( $input[ $k ] ); continue;
			}

			unset( $input[ $k ] );
			$newinput[ $name ] = compact( 'name', 'width', 'height', 'crop' );
		}

		return $newinput;
	}

	function field( $args ) {
		// saved fields
		if ( $args != false ) foreach( $args as $id => $params ) {
			echo $this->__fields( $params, $id );
		}
		// empty set for new
		echo $this->__fields();
	}

	function __fields( $values=array(), $key_id='blah' ) {
		$defaults = array( 'name' => '', 'width' => null, 'height' => null, 'crop' => false );
		$values = wp_parse_args( $values, $defaults );
		$cb_crop = checked( $values['crop'], true, false );
		$html = "<p class='ais-field-row'>".
				"<input type='text' name='ais-images[{$key_id}][name]' value='{$values['name']}' placeholder='name' />".
				"<input type='text' name='ais-images[{$key_id}][width]' value='{$values['width']}' placeholder='width' />".
				"<input type='text' name='ais-images[{$key_id}][height]' value='{$values['height']}' placeholder='height' />".
				"<input type='hidden' name='ais-images[{$key_id}][crop]' value='0' />".
				"<label> <input type='checkbox' name='ais-images[{$key_id}][crop]' value='1' $cb_crop /> Crop</label>".
				"</p>";
		return $html;
	}

	function menu() {
		$this->page_name = add_options_page( __( 'Image Sizes', $this->td ), __( 'Image Sizes', $this->td ), 'edit_posts', __CLASS__, array( &$this, 'page' ) );
	}

	function page() {
		add_action( 'admin_footer', array( &$this, 'admin_footer' ) );
		?><div class="wrap">
		<h2><?php _e( 'Image Sizes', $this->td ); ?></h2>
		<p><?php _e( 'Create additional named image sizes for easy use', $this->td ); ?></p>
		<form method="post" action="options.php">
		<?php
			settings_fields( 'add-image-size' );
			do_settings_sections( $this->page_name );
			echo '<p>';
			submit_button( __( 'Save', $this->td ), 'primary', 'ais-submit', false );
			echo ' ';
			submit_button( __( 'Add', $this->td ), 'small', 'ais-add', false );
			echo '</p>';

		?>
		</form>
		</div><?php
	}

	function admin_footer() {
		?><script>
		jQuery(document).ready(function($){

			$('.wrap').on( 'click', '#ais-add', function(ev) {
				ev.preventDefault();
				console.log( 'yep' );
				$.post( ajaxurl, {
					action: 'get_uniqid',
					nonce: '<?php echo wp_create_nonce('ais-new-row'); ?>'
				}, function( resp ) {
					if ( resp == '-1' )
						alert( '<?php _e( 'Not Allowed' ); ?>' );
					else
						$('.ais-field-row:last-child').after( resp );
				});
			});

		});
		</script><?php
	}

	function get_uniqid_cb() {
		if ( check_ajax_referer( 'ais-new-row', 'nonce' ) )
			die( $this->__fields( array(), uniqid() ) );
	}

}

//eof