<?php
/**
 * Privacy-policy guidance for optional external location services.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add suggested text to WordPress' Privacy Policy Guide.
 */
function aomark_listings_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Aomark Listings can use external map tiles and address search when a site administrator configures or displays listing locations. Review the providers selected by the site owner and adapt this text to the site’s actual setup.', 'aomark-listings' ) . '</p>';
	$content .= '<p>' . wp_kses_post( __( '<strong>Listing data:</strong> Titles, descriptions, images, contact details, structured fields, addresses, and coordinates entered by site editors are stored in this WordPress site as posts, terms, attachments, and post metadata.', 'aomark-listings' ) ) . '</p>';
	$content .= '<p>' . wp_kses_post( __( '<strong>Address search:</strong> By default, address text is sent from this site’s server to the Photon service operated by Komoot only after an authorized editor chooses the explicit Photon search action. The search text and normal server request information are processed under the provider’s policies. Responses may be cached temporarily by WordPress to reduce repeated requests.', 'aomark-listings' ) ) . '</p>';
	$content .= '<p>' . wp_kses_post( __( '<strong>Map tiles:</strong> In the listing editor, map tiles are requested only after an authorized editor clicks the map-load button. On the frontend, a visitor’s browser requests map tiles when a page displays a map configured by the site owner. The selected tile provider can receive normal web request information such as the person’s IP address, user agent, requested tile coordinates, and referrer according to its policy.', 'aomark-listings' ) ) . '</p>';
	$content .= '<p>' . wp_kses_post( __( 'Service information: <a href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a>, <a href="https://operations.osmfoundation.org/policies/tiles/" target="_blank" rel="noopener noreferrer">OpenStreetMap tile usage policy</a>, and <a href="https://osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener noreferrer">OpenStreetMap Foundation privacy policy</a>.', 'aomark-listings' ) ) . '</p>';

	wp_add_privacy_policy_content( 'Aomark Listings', wp_kses_post( wpautop( $content, false ) ) );
}
add_action( 'admin_init', 'aomark_listings_add_privacy_policy_content' );
