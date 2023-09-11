<?php
/**
 * Plugin Name: WP publishareBot
 * Plugin URI: https://publishare.0x100.it
 * Description: Helping you to automatically share your Telegram channel posts on Wordpress
 * Version: 1.0.0
 * Author: Jacopo Pace
 * Author URI: https://jacopo.im
 * License: GPL v2
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) exit;

// Generate random API suffix
//delete_option( 'pushbot_api_suffix' );
if ( ! get_option( 'pushbot_api_suffix' ) ) {
	add_option( 'pushbot_api_suffix', bin2hex( random_bytes(3) ) );
}
// Constants
define( PUSHBOT_API, '/pushbot/v1' );
define( PUSHBOT_API_SUFFIX, '/' . get_option( 'pushbot_api_suffix' ) );
define( PUSHBOT_DOMAIN, 'publishare.0x100.it' );
define( PUSHBOT_SITE, 'https://' . PUSHBOT_DOMAIN );


/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */


/**
 * custom option and settings
 */
function pushbot_settings_init() {
	// Register a new setting for "pushbot" page.
	register_setting( 'pushbot', 'pushbot_options' );

	// Register a new section in the "pushbot" page.
	add_settings_section(
		'pushbot_section_developers',
		__( 'The Web Publisher', 'pushbot' ),
		'pushbot_section_developers_callback',
		'pushbot'
	);

	// Register a new field in the "pushbot_section_developers" section, inside the "pushbot" page.
	add_settings_field(
		'pushbot_field_endpoint', // As of WP 4.6 this value is used only internally.
		                          // Use $args' label_for to populate the id inside the callback.
		__( 'Endpoint url', 'pushbot' ),
		'pushbot_field_endpoint_cb',
		'pushbot',
		'pushbot_section_developers',
		[
			'class'     => 'pushbot_row'
		]
	);
}

/**
 * Register our pushbot_settings_init to the admin_init action hook.
 */
add_action( 'admin_init', 'pushbot_settings_init' );


/**
 * Custom option and settings:
 *  - callback functions
 */


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
function pushbot_section_developers_callback( $args ) { ?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php esc_html_e( 'Automagically share your Telegram channel posts on WP with', 'pushbot' ); ?>
		<a target="_blank" href="https://t.me/publishareBot">@publishareBot</a>
	</p>
<?php }

/**
 * Pushbot field callback function.
 *
 * WordPress has magic interaction with the following keys: label_for, class.
 * - the "label_for" key value is used for the "for" attribute of the <label>.
 * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
 * Note: you can add custom key value pairs to be used inside your callbacks.
 *
 * @param array $args
 */
function pushbot_field_endpoint_cb( $args ) {
	// $options = get_option( 'pushbot_options' ); // Actually unused options ?>
	<i style="display:inline-block;border:2px dashed #ccc;padding:4px 18px;margin-bottom:18px" >
		<?php echo get_site_url() . '/wp-json' . PUSHBOT_API . PUSHBOT_API_SUFFIX; ?>
	</i>
	<p class="description" style="margin-bottom:18px">
		<?php esc_html_e( 'Just copy the url above and paste it in the bot settings.', 'pushbot' ); ?>
		<br>
		<?php esc_html_e( 'To get more info, click on the', 'pushbot' ); ?>
		<a target="_blank" href="<?php echo PUSHBOT_SITE; ?>/faq.html"><?php esc_html_e( 'FAQ', 'pushbot' ); ?></a>
		<?php esc_html_e( 'page and read the question:', 'pushbot' ); ?><br>
		<i><b><?php esc_html_e( '"How can I link the bot with my website?"', 'pushbot' ); ?></b></i>
	</p>
	<p class="description">
		<?php esc_html_e( 'Official project website:', 'pushbot' ); ?>
		<a target="_blank" href="<?php echo PUSHBOT_SITE; ?>"><?php echo PUSHBOT_DOMAIN; ?></a>.
	</p>
<?php }

/**
 * Top level menu callback function
 */
function pushbot_options_page_html() {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) return; ?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php do_settings_sections( 'pushbot' ); ?>
	</div>
<?php }

/**
 * Add the top level menu page.
 */
function pushbot_menu_page() {
	add_menu_page( // Or "add_options_page"
		'WP publishareBot',
		'PublishareBot',
		'manage_options',
		'pushbot',
		'pushbot_options_page_html',
		'dashicons-megaphone'
	);
}

/**
 * Register our pushbot_menu_page to the admin_menu action hook.
 */
add_action( 'admin_menu', 'pushbot_menu_page' );


/**
 * Attach main photo to post
 */
function pushbot_attach_photo( $post_id, $base64photo ) {
	// Get the path to the upload directory.
	$wp_upload_dir = wp_upload_dir();
	$imagefile = 'pushbot-' . time() . '-' . uniqid() . '.jpg';
	$imagefile_path = $wp_upload_dir['path'] . '/' . $imagefile;
	$decoded = base64_decode( $base64photo );
	file_put_contents( $imagefile_path, $decoded );
	// Check the type of file. We'll use this as the 'post_mime_type'.
	$filetype = wp_check_filetype( $imagefile, null );
	// Prepare an array of post data for the attachment.
	$attachment = [
		'guid'           => $imagefile_path,
		'post_mime_type' => $filetype['type'],
		'post_title'     => preg_replace( '/\.[^.]+$/', '', $imagefile ),
		'post_content'   => '',
		'post_status'    => 'inherit'
	];
	// Insert the attachment.
	$attach_id = wp_insert_attachment( $attachment, $imagefile_path, $post_id );
	// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	// Generate the metadata for the attachment, and update the database record.
	$attach_data = wp_generate_attachment_metadata( $attach_id, $imagefile_path );
	wp_update_attachment_metadata( $attach_id, $attach_data );
	set_post_thumbnail( $post_id, $attach_id );
}


/**
 * Endpoint callback functions
 */
// GET
function pushbot_endpoint_get_cb( WP_REST_Request $request ) {
	return new WP_Error(
		'bad_request',
		'',
		['status' => 400]
	);
}

// POST
function pushbot_endpoint_post_cb( WP_REST_Request $request ) {
	$payload = $request->get_json_params();

	// Do nothing if no messageId
	if ( ! $payload['messageId'] ) {
		return new WP_Error(
			'no_message_id',
			'Missing "messageId"',
			['status' => 400]
		);
	}

	// Do nothing if no text
	if ( ! $payload['text'] ) {
		return new WP_Error(
			'no_text',
			'Missing "text"',
			['status' => 400]
		);
	}

	// Publish post
	$post_id = wp_insert_post( [
		'post_title' => $payload['title'] ? trim( esc_html( $payload['title'] ) ) : '',
		'post_content' => esc_html( $payload['text'] ),
		'post_status' => 'publish',
	] );

	// Add message ID
	add_post_meta( $post_id, 'tg_msg_id', $payload['messageId'] );

	// Upload image too, if any
	if ( $payload['base64Photo'] ) {
		pushbot_attach_photo( $post_id, $payload['base64Photo'] );
	}
}

// PUT
function pushbot_endpoint_put_cb( WP_REST_Request $request ) {
	$payload = $request->get_json_params();

	// Do nothing if no messageId
	if ( ! $payload['messageId'] ) {
		return new WP_Error(
			'no_message_id',
			'Missing "messageId"',
			['status' => 400]
		);
	}

	// Do nothing if no text
	if ( ! $payload['text'] || ! $payload['base64Photo'] ) {
		return new WP_Error(
			'no_text_nor_image',
			'Missing "text" nor "image"',
			['status' => 400]
		);
	}

	// Find post
	$wp_query = new WP_Query( [
		'post_type'    => 'post',
		'meta_compare' => '=',
		'meta_key'     => 'tg_msg_id',
		'meta_value'   => $payload['messageId'],
	] );

	// Checks
	if ( empty( $wp_query->posts ) ) {
		return new WP_Error( 'not_found', '', ['status' => 404] );
	}

	// Update post
	$post_id = wp_update_post( [
		'ID'           => $wp_query->posts[0]->ID,
		'post_title'   => $payload['title'] ? trim( esc_html( $payload['title'] ) ) : '',
		'post_content' => esc_html( $payload['text'] ),
		'post_status'  => 'publish',
	] );

	// Upload image too, if any
	if ( $payload['base64Photo'] ) {
		pushbot_attach_photo( $post_id, $payload['base64Photo'] );
	}
}

// DELETE
function pushbot_endpoint_delete_cb( WP_REST_Request $request ) {
	$payload = $request->get_json_params();

	// Request checks
	if ( ! $payload['ids'] ) {
		return new WP_Error(
			'no_ids',
			'Missing "ids"',
			['status' => 400]
		);
	}
	if ( ! is_array( $payload['ids'] ) ) {
		return new WP_Error(
			'no_ids_array',
			'Missing "ids" array',
			['status' => 400]
		);
	}

	// Iterate over ID's
	foreach ( $payload['ids'] as $id ) {
		// Find post
		$wp_query = new WP_Query( [
			'post_type'    => 'post',
			'meta_compare' => '=',
			'meta_key'     => 'tg_msg_id',
			'meta_value'   => $id,
		] );

		// Checks
		if ( ! isset( $wp_query->posts ) ) continue;
		if ( empty( $wp_query->posts ) ) continue;
		if ( ! isset( $wp_query->posts[0]->ID ) ) continue;

		// Delete post
		wp_delete_post( $wp_query->posts[0]->ID );
	}

	// Response
	$response = new WP_REST_Response();
	$response->set_status( 200 );

	return $response;
}


/**
 * Register our pushbot_endpoint_cb
 */
add_action( 'rest_api_init', function( $data ) {
	register_rest_route( PUSHBOT_API, PUSHBOT_API_SUFFIX, [
		'methods' => 'GET',
		'callback' => 'pushbot_endpoint_get_cb'
	] );

	register_rest_route( PUSHBOT_API, PUSHBOT_API_SUFFIX, [
		'methods' => 'POST',
		'callback' => 'pushbot_endpoint_post_cb'
	] );

	register_rest_route( PUSHBOT_API, PUSHBOT_API_SUFFIX, [
		'methods' => 'PUT',
		'callback' => 'pushbot_endpoint_put_cb'
	] );

	register_rest_route( PUSHBOT_API, PUSHBOT_API_SUFFIX, [
		'methods' => 'DELETE',
		'callback' => 'pushbot_endpoint_delete_cb'
	] );
} );
