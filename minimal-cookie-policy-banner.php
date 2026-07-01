<?php
/**
 * Plugin Name:       Minimal Cookie Policy Banner
 * Description:        A lightweight cookie policy banner with a dismiss button. Includes an admin page to enable/disable the banner and customize the message, button text, cookie policy link, dismissal duration, and all colors.
 * Version:           1.1.0
 * Author:            Nehmedia
 * License:           GPL-2.0-or-later
 * Text Domain:       minimal-cookie-policy-banner
 *
 * @package MinimalCookiePolicyBanner
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Automatic updates from GitHub via Plugin Update Checker (Yahnis Elsts).
 *
 * Updates are driven by GitHub Releases. To ship a new version:
 *   1. Bump the "Version:" header above.
 *   2. Commit and push to the "main" branch.
 *   3. Publish a GitHub Release whose tag matches the new version
 *      (e.g. version 1.2.0 -> tag "v1.2.0" or "1.2.0").
 * Sites running the plugin will then see the update in wp-admin.
 * ---------------------------------------------------------------------- */

require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

$mcpb_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/rreasoner/minimal-cookie-policy-banner/',
	__FILE__,
	'minimal-cookie-policy-banner'
);

// Look for the latest GitHub Release (falling back to the highest tag) on "main".
$mcpb_update_checker->setBranch( 'main' );

/**
 * Default settings.
 *
 * @return array
 */
function mcpb_get_defaults() {
	return array(
		'enabled'     => 1,
		'message'     => 'This website uses cookies to ensure you get the best experience on our website.',
		'button'      => 'OK',
		'policy_url'  => '',
		'expiry_days' => 30,
		'banner_bg'   => '#000000',
		'banner_fg'   => '#ffffff',
		'button_bg'   => '#ffffff',
		'button_fg'   => '#000000',
	);
}

/**
 * Saved settings merged with defaults.
 *
 * @return array
 */
function mcpb_get_options() {
	return wp_parse_args( get_option( 'mcpb_options', array() ), mcpb_get_defaults() );
}

/**
 * HTML tags allowed inside the banner message (so a link to a policy page is possible).
 *
 * @return array
 */
function mcpb_allowed_message_html() {
	return array(
		'a'      => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
		'span'   => array(),
	);
}

/* -------------------------------------------------------------------------
 * Admin: menu, settings registration, color picker, settings page.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'mcpb_add_admin_menu' );
/**
 * Add the top-level "Cookie Policy" admin menu item.
 */
function mcpb_add_admin_menu() {
	add_menu_page(
		'Cookie Policy',             // Page title.
		'Cookie Policy',             // Menu label.
		'manage_options',            // Capability.
		'cookie-policy',             // Slug.
		'mcpb_render_settings_page', // Render callback.
		'dashicons-shield-alt',      // Icon.
		80                           // Position (near Settings).
	);
}

add_action( 'admin_init', 'mcpb_register_settings' );
/**
 * Register the single options array with a sanitize callback.
 */
function mcpb_register_settings() {
	register_setting(
		'mcpb_options_group',
		'mcpb_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mcpb_sanitize_options',
			'default'           => mcpb_get_defaults(),
		)
	);
}

/**
 * Sanitize all settings before they are saved.
 *
 * @param array $input Raw submitted values.
 * @return array
 */
function mcpb_sanitize_options( $input ) {
	$defaults = mcpb_get_defaults();
	$out      = array();

	$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

	$out['message'] = isset( $input['message'] )
		? wp_kses( $input['message'], mcpb_allowed_message_html() )
		: $defaults['message'];

	$out['button'] = isset( $input['button'] ) ? sanitize_text_field( $input['button'] ) : '';
	if ( '' === $out['button'] ) {
		$out['button'] = $defaults['button'];
	}

	$out['policy_url'] = isset( $input['policy_url'] ) ? esc_url_raw( trim( $input['policy_url'] ) ) : '';

	$out['expiry_days'] = isset( $input['expiry_days'] ) ? absint( $input['expiry_days'] ) : 0;
	if ( $out['expiry_days'] < 1 ) {
		$out['expiry_days'] = $defaults['expiry_days'];
	}

	foreach ( array( 'banner_bg', 'banner_fg', 'button_bg', 'button_fg' ) as $key ) {
		$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
		$out[ $key ] = $color ? $color : $defaults[ $key ];
	}

	return $out;
}

add_action( 'admin_enqueue_scripts', 'mcpb_admin_assets' );
/**
 * Load the built-in WordPress color picker on this plugin's settings page only.
 *
 * @param string $hook Current admin page hook suffix.
 */
function mcpb_admin_assets( $hook ) {
	if ( 'toplevel_page_cookie-policy' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		'jQuery(function($){ $(".mcpb-color-field").wpColorPicker(); });'
	);
}

/**
 * Render the "Cookie Policy" settings page.
 */
function mcpb_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = mcpb_get_options();
	$d = mcpb_get_defaults();
	?>
	<div class="wrap">
		<h1>Cookie Policy</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'mcpb_options_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enable Banner</th>
					<td>
						<label for="mcpb_enabled">
							<input type="checkbox" id="mcpb_enabled" name="mcpb_options[enabled]" value="1" <?php checked( 1, (int) $o['enabled'] ); ?>>
							Show the cookie policy banner on the front end.
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_message">Banner Message</label></th>
					<td>
						<textarea id="mcpb_message" name="mcpb_options[message]" rows="3" class="large-text"><?php echo esc_textarea( $o['message'] ); ?></textarea>
						<p class="description">Text shown in the banner. Basic HTML (links, bold, italics) is allowed. If a Cookie Policy URL is set below, a &ldquo;Cookie Policy&rdquo; link is appended automatically.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_button">Button Text</label></th>
					<td><input type="text" id="mcpb_button" name="mcpb_options[button]" value="<?php echo esc_attr( $o['button'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_policy_url">Cookie Policy URL</label></th>
					<td>
						<input type="url" id="mcpb_policy_url" name="mcpb_options[policy_url]" value="<?php echo esc_attr( $o['policy_url'] ); ?>" class="regular-text" placeholder="/cookie-policy/">
						<p class="description">Optional. When set, a &ldquo;Cookie Policy&rdquo; link to this page is appended to the banner message.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_expiry_days">Cookie Expiration (days)</label></th>
					<td>
						<input type="number" id="mcpb_expiry_days" name="mcpb_options[expiry_days]" value="<?php echo esc_attr( $o['expiry_days'] ); ?>" class="small-text" min="1" step="1">
						<p class="description">How long the banner stays dismissed after a visitor accepts. Defaults to <?php echo (int) $d['expiry_days']; ?> days if left blank.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_banner_bg">Banner Background Color</label></th>
					<td><input type="text" class="mcpb-color-field" id="mcpb_banner_bg" name="mcpb_options[banner_bg]" value="<?php echo esc_attr( $o['banner_bg'] ); ?>" data-default-color="<?php echo esc_attr( $d['banner_bg'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_banner_fg">Banner Text Color</label></th>
					<td><input type="text" class="mcpb-color-field" id="mcpb_banner_fg" name="mcpb_options[banner_fg]" value="<?php echo esc_attr( $o['banner_fg'] ); ?>" data-default-color="<?php echo esc_attr( $d['banner_fg'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_button_bg">Button Background Color</label></th>
					<td><input type="text" class="mcpb-color-field" id="mcpb_button_bg" name="mcpb_options[button_bg]" value="<?php echo esc_attr( $o['button_bg'] ); ?>" data-default-color="<?php echo esc_attr( $d['button_bg'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_button_fg">Button Text Color</label></th>
					<td><input type="text" class="mcpb-color-field" id="mcpb_button_fg" name="mcpb_options[button_fg]" value="<?php echo esc_attr( $o['button_fg'] ); ?>" data-default-color="<?php echo esc_attr( $d['button_fg'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Front end: styles, banner markup, dismiss + 30-day cookie logic.
 * ---------------------------------------------------------------------- */

add_action( 'wp_head', 'mcpb_print_styles' );
/**
 * Print the banner styles using the configured colors.
 */
function mcpb_print_styles() {
	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) ) {
		return;
	}
	?>
	<style id="mcpb-styles">
		#mcpb-banner{position:fixed;left:0;right:0;bottom:0;z-index:99999;display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap;box-sizing:border-box;padding:16px 20px;font-size:14px;line-height:1.5;background:<?php echo esc_html( $o['banner_bg'] ); ?>;color:<?php echo esc_html( $o['banner_fg'] ); ?>;box-shadow:0 -2px 12px rgba(0,0,0,.18)}
		#mcpb-banner .mcpb-message{margin:0}
		#mcpb-banner .mcpb-message a{color:inherit;text-decoration:underline}
		#mcpb-banner .mcpb-dismiss{flex-shrink:0;border:0;cursor:pointer;border-radius:4px;padding:9px 22px;font-size:14px;font-weight:600;line-height:1;background:<?php echo esc_html( $o['button_bg'] ); ?>;color:<?php echo esc_html( $o['button_fg'] ); ?>}
		#mcpb-banner .mcpb-dismiss:hover{opacity:.88}
		@media (max-width:600px){#mcpb-banner{flex-direction:column;text-align:center}}
	</style>
	<?php
}

add_action( 'wp_footer', 'mcpb_render_banner' );
/**
 * Output the banner markup and the dismiss/cookie script.
 */
function mcpb_render_banner() {
	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) ) {
		return;
	}

	// Append a "Cookie Policy" link to the message when a URL is configured.
	$message = $o['message'];
	if ( ! empty( $o['policy_url'] ) ) {
		$message .= ' <a href="' . esc_url( $o['policy_url'] ) . '">Cookie Policy</a>';
	}

	$expiry_days = max( 1, (int) $o['expiry_days'] );
	?>
	<div id="mcpb-banner" role="region" aria-label="Cookie notice">
		<p class="mcpb-message"><?php echo wp_kses( $message, mcpb_allowed_message_html() ); ?></p>
		<button type="button" id="mcpb-dismiss" class="mcpb-dismiss"><?php echo esc_html( $o['button'] ); ?></button>
	</div>
	<script>
	(function () {
		var banner = document.getElementById('mcpb-banner');
		if (!banner) { return; }

		var expiryDays = <?php echo (int) $expiry_days; ?>;

		function getCookie(name) {
			var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
			return match ? decodeURIComponent(match[1]) : null;
		}

		// Already dismissed: hide right away. This script runs synchronously
		// immediately after the markup, so returning visitors see no flash.
		if (getCookie('mcpb_dismissed') === '1') {
			banner.style.display = 'none';
			return;
		}

		var btn = document.getElementById('mcpb-dismiss');
		if (btn) {
			btn.addEventListener('click', function () {
				var expires = new Date();
				expires.setTime(expires.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
				document.cookie = 'mcpb_dismissed=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
				banner.style.display = 'none';
			});
		}
	})();
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * Remove stored option on uninstall.
 * ---------------------------------------------------------------------- */

register_uninstall_hook( __FILE__, 'mcpb_uninstall' );
/**
 * Delete plugin data when the plugin is deleted.
 */
function mcpb_uninstall() {
	delete_option( 'mcpb_options' );
}
