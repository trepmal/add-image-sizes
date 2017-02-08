<?php
/**
 * Plugin Name: Add Image Size
 * Plugin URI: https://github.com/trepmal/add-image-sizes
 * Description: GUI for add_image_size()
 * Version:
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: add-image-size
 * DomainPath:
 * Network:
 *
 * @package Add_Image_Size
 */

$add_image_size = new Add_Image_Size();

/**
 * Fake namespace wrapper
 */
class Add_Image_Size {

	/**
	 * Save page name
	 *
	 * @var $page_name
	 */
	var $page_name;

	/**
	 * Get hooked in
	 */
	function __construct() {
		add_action( 'admin_init',          array( $this, 'register_options' ) );
		add_action( 'admin_menu',          array( $this, 'menu' ) );
		add_filter( 'contextual_help',     array( $this, 'contextual_help' ), 10, 3 );
		add_action( 'wp_ajax_get_new_row', array( $this, 'get_new_row_cb' ) );

		$sizes = get_option( 'ais-images', false );
		if ( $sizes ) {
			foreach ( $sizes as $image_deets ) {
				add_image_size( $image_deets['name'], $image_deets['width'], $image_deets['height'], $image_deets['crop'] );
			}
		}
	}

	/**
	 * Register option
	 */
	function register_options() {
		register_setting( 'add-image-size-group', 'ais-images', array( $this, 'sanitize' ) );

		add_settings_section( 'ais-section', __( 'Image Size Properties', 'add-image-size' ), '__return_empty_string', $this->page_name );
		add_settings_field( 'ais-image-row', __( 'Images:', 'add-image-size' ), array( $this, 'field' ), $this->page_name, 'ais-section', get_option( 'ais-images', false ) );
	}

	/**
	 * Sanitize option on save
	 *
	 * @param array $input Unsanitized option value.
	 * @return array Sanitized option value.
	 */
	function sanitize( $input ) {
		$newinput = array();
		foreach ( $input as $k => $image_deets ) {

			$image_deets = array_map( 'trim', $image_deets );
			$name   = $image_deets['name'];
			$width  = $image_deets['width'];
			$height = $image_deets['height'];
			$crop   = $image_deets['crop'];

			if ( empty( $name ) || empty( $width ) || empty( $height ) ) {
				unset( $input[ $k ] );
				continue;
			}

			$name   = sanitize_title( $name );
			$width  = (int) $width;
			$height = (int) $height;
			$crop   = (bool) $crop;

			if ( $width < 1 || $height < 1 ) {
				unset( $input[ $k ] );
				continue;
			}

			unset( $input[ $k ] );
			$newinput[ $name ] = compact( 'name', 'width', 'height', 'crop' );
		}

		return $newinput;
	}

	/**
	 * Output HTML field
	 *
	 * @param array $args Option value.
	 */
	function field( $args ) {

		if ( false != $args ) {
			foreach ( $args as $id => $params ) {
				$this->fields_tmpl( $params, $id );
			}
		}
		// empty field set for new.
		$this->fields_tmpl();
	}

	/**
	 * Output field group
	 *
	 * @param array  $values Values for image size.
	 * @param string $key_id Field row id.
	 */
	function fields_tmpl( $values = array(), $key_id = 'blah' ) {
		$defaults = array( 'name' => '', 'width' => null, 'height' => null, 'crop' => false );
		$values = wp_parse_args( $values, $defaults );
		$cb_crop = checked( $values['crop'], true, false );
		$html = '<p class="ais-field-row">
				<input type="text" name="ais-images[%1$s][name]" value="%2$s" placeholder="name" />
				<input type="text" name="ais-images[%1$s][width]" value="%3$s" placeholder="width" />
				<input type="text" name="ais-images[%1$s][height]" value="%4$s" placeholder="height" />
				<input type="hidden" name="ais-images[%1$s][crop]" value="0" />
				<label> <input type="checkbox" name="ais-images[%1$s][crop]" value="1" %5$s /> Crop</label>
				</p>';

		printf( wp_kses( $html ), esc_attr( $key_id ), esc_attr( $values['name'] ), esc_attr( $values['width'] ), esc_attr( $values['height'] ), $cb_crop );
	}

	/**
	 * Set up admin page
	 */
	function menu() {
		$this->page_name = add_options_page( __( 'Image Sizes', 'add-image-size' ), __( 'Image Sizes', 'add-image-size' ), 'edit_posts', __CLASS__, array( $this, 'page' ) );
	}

	/**
	 * Output admin page
	 */
	function page() {
		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		?><div class="wrap">
		<h2><?php esc_html_e( 'Image Sizes', 'add-image-size' ); ?></h2>
		<p><?php esc_html_e( 'Create additional named image sizes for easy use', 'add-image-size' ); ?></p>
		<form method="post" action="options.php">
		<?php
			settings_fields( 'add-image-size-group' );
			do_settings_sections( $this->page_name );
			echo '<p>';
			submit_button( __( 'Save', 'add-image-size' ), 'primary', 'ais-submit', false );
			echo ' ';
			submit_button( __( 'Add', 'add-image-size' ), 'small', 'ais-add', false );
			echo '</p>';
		?>
		</form>
		</div><?php
	}

	/**
	 * Output scripts for admin page
	 */
	function admin_footer() {
		?><script>
		jQuery(document).ready(function($){

			$('.wrap').on( 'click', '#ais-add', function(ev) {
				ev.preventDefault();
				console.log( 'yep' );
				$.post( ajaxurl, {
					action: 'get_new_row',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ais-new-row' ) ); ?>'
				}, function( resp ) {
					if ( '-1' == resp ) {
						alert( '<?php echo esc_js( 'Not Allowed', 'add-image-size' ); ?>' );
					}
					else {
						$('.ais-field-row:last-child').after( resp );
					}
				});
			});

		});
		</script><?php
	}

	/**
	 * Admin contextual help
	 *
	 * @param string $old Old help text.
	 * @param string $id Screen ID.
	 * @param object $object Current WP_Screen instance.
	 */
	function contextual_help( $old, $id, $object ) {
		if ( $id != $this->page_name ) {
			return $old;
		}
		$help_text = '';
		$help_text .= '<p>' . __( 'Names must be unique', 'add-image-size' ) . '</p>';
		$help_text .= '<p>' . __( 'To change built-in image sizes, visit the <a href="http://local.dev/nnkpr/wp-admin/options-media.php">Media Settings</a> page.', 'add-image-size' ) . '</p>';
		$help_text .= '<p>' . __( 'Changing width/height will not apply retroactively. Use a plugin like <a href="http://wordpress.org/plugins/regenerate-thumbnails/">Regenerate Thumbnails</a> to do just that.', 'add-image-size' ) . '</p>';
		$object->add_help_tab( array(
			'id'      => 'ais-help',
			'title'   => __( 'Overview', 'add-image-size' ),
			'content' => $help_text,
		) );
	}

	/**
	 * Ajax callback
	 */
	function get_new_row_cb() {
		if ( check_ajax_referer( 'ais-new-row', 'nonce' ) ) {
			die( $this->fields_tmpl( array(), uniqid() ) );
		}
	}

}
