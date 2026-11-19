<?php
/**
 * Plugin Name:       Minimal Cookie Policy Banner
 * Description:       A lightweight cookie consent banner with Accept / Decline / Manage preferences, four cookie categories, Global Privacy Control support, California "Your Privacy Choices" opt-out link with the official opt-out icon, Google Consent Mode v2 signals and a consent_update dataLayer event. Tracking is on by default (opt-out). Hardened against CSS/JS optimizers such as WP Rocket.
 * Version:           1.3.0
 * Author:            Nehmedia
 * License:           GPL-2.0-or-later
 * Text Domain:       minimal-cookie-policy-banner
 *
 * Changelog:
 * 1.3.0 - Replaced the OK-only notice with Accept / Decline / Manage preferences.
 *         Added four categories (Essential, Analytics, Advertising, Functional),
 *         a preferences dialog, a floating cookie-settings icon and a
 *         "Your Privacy Choices" footer link. Tracking stays on by default and
 *         categories are only switched off when the visitor declines them.
 *         Honors Global Privacy Control (Advertising off). Sends Google Consent
 *         Mode v2 default/update commands and pushes a "consent_update"
 *         dataLayer event. Choices are stored in the "mcpb_consent" cookie.
 *         The plugin never loads or blocks Google Tag Manager.
 *         California (CCPA regulations, 11 CCR 7015, 7025, 7026): the official
 *         opt-out icon sits next to the "Your Privacy Choices" link, the dialog
 *         describes the right to opt out of the sale or sharing of personal
 *         information, and "Opt-Out Request Honored" is shown whenever
 *         Advertising is off by choice or by a Global Privacy Control signal.
 *         The old "Button Text" and "Cookie Expiration" settings are replaced by
 *         the three button labels and "Remember Choice For" (default 395 days).
 * 1.2.0 - Critical styles moved to inline style attributes so the banner keeps
 *         its fixed position and colors even when WP Rocket "Remove Unused CSS"
 *         or "Optimize CSS Delivery" strips <style> blocks. Added WP Rocket
 *         safelist + delay-JS exclusion filters. No settings changes required.
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

/* -------------------------------------------------------------------------
 * Consent model.
 *
 * - Opt-out: every category is on until the visitor switches it off.
 * - The choice lives in one first-party cookie (MCPB_CONSENT_COOKIE):
 *       essential:1|analytics:1|advertising:0|functional:1|choice:1
 *   1 = on, 0 = off. choice:1 = the visitor made a choice in the banner or the
 *   preferences dialog; choice:0 = written automatically because the browser
 *   sends a Global Privacy Control signal. No cookie = no choice yet = all on.
 * - Global Privacy Control always switches Advertising off.
 * - California: switching Advertising off is the visitor's request to opt out
 *   of the sale or sharing of personal information for this browser. The
 *   dialog says so in those words, and the site shows "Opt-Out Request
 *   Honored" while that opt-out is in effect.
 * - All decisions happen in the browser, so the page HTML is identical for
 *   every visitor and stays safe to page-cache.
 * ---------------------------------------------------------------------- */

if ( ! defined( 'MCPB_CONSENT_COOKIE' ) ) {
	define( 'MCPB_CONSENT_COOKIE', 'mcpb_consent' );
}

/**
 * Default settings.
 *
 * @return array
 */
function mcpb_get_defaults() {
	return array(
		'enabled'          => 1,
		'message'          => 'We use cookies and similar technologies to run this site, understand how it is used, and share information with advertising partners. You can accept, decline, or manage your choices.',
		'accept_text'      => 'Accept',
		'decline_text'     => 'Decline',
		'manage_text'      => 'Manage preferences',
		'policy_url'       => '',
		'consent_days'     => 395,
		'rights_text'      => 'Under California privacy law you can opt out of the sale or sharing of your personal information. To opt out on this browser, switch Advertising off and save, or choose Decline all. We also honor Global Privacy Control signals automatically.',
		'rights_url'       => '',
		'show_icon'        => 1,
		'icon_position'    => 'left',
		'footer_link'      => 1,
		'footer_link_text' => 'Your Privacy Choices',
		'banner_bg'        => '#000000',
		'banner_fg'        => '#ffffff',
		'button_bg'        => '#ffffff',
		'button_fg'        => '#000000',
	);
}

/**
 * Saved settings merged with defaults.
 *
 * Settings saved by 1.2.0 and earlier simply pick up the defaults for the keys
 * added in 1.3.0; the retired "button" and "expiry_days" keys are ignored.
 *
 * @return array
 */
function mcpb_get_options() {
	return wp_parse_args( get_option( 'mcpb_options', array() ), mcpb_get_defaults() );
}

/**
 * The four consent categories.
 *
 * The keys are fixed because the consent cookie, the dataLayer event and the
 * Consent Mode mapping all depend on them. Labels and descriptions can be
 * changed with the "mcpb_categories" filter.
 *
 * @return array
 */
function mcpb_get_categories() {
	$categories = array(
		'essential'   => array(
			'label'       => 'Essential',
			'description' => 'Needed for the site to work: security, page navigation, and remembering your privacy choices. Always on.',
		),
		'analytics'   => array(
			'label'       => 'Analytics',
			'description' => 'Help us understand how visitors use the site so we can improve it.',
		),
		'advertising' => array(
			'label'       => 'Advertising',
			'description' => 'Sharing information about your visit with advertising partners to measure our ads and show you more relevant ones. This is the sale or sharing of personal information that you can opt out of.',
		),
		'functional'  => array(
			'label'       => 'Functional',
			'description' => 'Remember your settings and power extras such as embedded video, maps, and chat.',
		),
	);

	$filtered = apply_filters( 'mcpb_categories', $categories );
	if ( is_array( $filtered ) ) {
		foreach ( $categories as $key => $category ) {
			foreach ( array( 'label', 'description' ) as $field ) {
				if ( isset( $filtered[ $key ][ $field ] ) && is_string( $filtered[ $key ][ $field ] ) ) {
					$categories[ $key ][ $field ] = $filtered[ $key ][ $field ];
				}
			}
		}
	}

	return $categories;
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
 * California opt-out icon.
 *
 * The CCPA regulations (11 CCR 7015(b)) require the official opt-out icon next
 * to a link titled "Your Privacy Choices". assets/privacyoptions.svg is the
 * unmodified file published by the California Attorney General at
 * https://oag.ca.gov/privacy/ccpa/icons-download . It is loaded as an image so
 * its internal styles cannot leak into the page.
 * ---------------------------------------------------------------------- */

/**
 * URL of the opt-out icon, or an empty string when the file is missing.
 *
 * Use the "mcpb_optout_icon_url" filter to point at another official variant,
 * for example when the blue icon does not stand out on the site's footer.
 *
 * @return string
 */
function mcpb_optout_icon_url() {
	$url = file_exists( plugin_dir_path( __FILE__ ) . 'assets/privacyoptions.svg' )
		? plugins_url( 'assets/privacyoptions.svg', __FILE__ )
		: '';

	return (string) apply_filters( 'mcpb_optout_icon_url', $url );
}

/**
 * The opt-out icon as an <img> tag, sized to follow the link text.
 *
 * @return string
 */
function mcpb_optout_icon_html() {
	$url = mcpb_optout_icon_url();
	if ( '' === $url ) {
		return '';
	}

	// Alt text recommended by the Attorney General for this icon.
	return sprintf(
		'<img class="mcpb-optout-icon skip-lazy" src="%1$s" alt="%2$s" width="30" height="14" decoding="async" data-no-lazy="1" style="display:inline-block;width:auto;height:1em;min-height:14px;max-width:none;margin:0 0 0 .45em;padding:0;border:0;border-radius:0;box-shadow:none;background:none;vertical-align:-0.15em">',
		esc_url( $url ),
		esc_attr( 'California Consumer Privacy Act (CCPA) Opt-Out Icon' )
	);
}

/* -------------------------------------------------------------------------
 * Inline style builders.
 *
 * All layout- and color-critical CSS lives in element style attributes.
 * Optimizers (WP Rocket Remove Unused CSS, Optimize CSS Delivery, Autoptimize,
 * etc.) rewrite <style> tags and stylesheets but never touch inline style
 * attributes, so the banner, the preferences dialog and the settings icon
 * always render in place with the configured colors regardless of those
 * settings. The same styles also shield the buttons from theme button CSS.
 * ---------------------------------------------------------------------- */

/**
 * Return a hex color, or the fallback when the value is not one.
 *
 * (sanitize_hex_color() is not loaded on the front end in older WordPress.)
 *
 * @param string $color    Value to check.
 * @param string $fallback Hex color to use instead.
 * @return string
 */
function mcpb_safe_hex( $color, $fallback ) {
	return ( is_string( $color ) && preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color ) ) ? $color : $fallback;
}

/**
 * Convert a hex color to an rgba() string.
 *
 * @param string $hex   Hex color (#rgb or #rrggbb).
 * @param float  $alpha Opacity between 0 and 1.
 * @return string
 */
function mcpb_hex_to_rgba( $hex, $alpha ) {
	$hex   = ltrim( mcpb_safe_hex( $hex, '#808080' ), '#' );
	$alpha = number_format( (float) $alpha, 2, '.', '' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return sprintf(
		'rgba(%d,%d,%d,%s)',
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
		$alpha
	);
}

/**
 * Inline style for the banner container.
 *
 * The banner starts hidden; the front-end script shows it only for visitors
 * who have not made a choice yet, so returning visitors never see a flash.
 *
 * @param array $o Options.
 * @return string
 */
function mcpb_banner_style( $o ) {
	return sprintf(
		'display:none;position:fixed;left:0;right:0;bottom:0;z-index:99999;align-items:center;justify-content:center;gap:12px 20px;flex-wrap:wrap;box-sizing:border-box;padding:16px 20px;margin:0;font-size:14px;line-height:1.5;text-align:left;background:%1$s;color:%2$s;box-shadow:0 -2px 12px rgba(0,0,0,.18)',
		$o['banner_bg'],
		$o['banner_fg']
	);
}

/**
 * Inline style for a button.
 *
 * Accept and Decline share the "primary" style on purpose, so declining is
 * exactly as easy to spot as accepting.
 *
 * @param array  $o       Options.
 * @param string $variant "primary" (filled) or "secondary" (outlined).
 * @return string
 */
function mcpb_button_style( $o, $variant = 'primary' ) {
	$base = 'flex:0 0 auto;-webkit-appearance:none;appearance:none;box-sizing:border-box;width:auto;min-width:0;min-height:0;margin:0;cursor:pointer;border-radius:4px;padding:10px 20px;font-family:inherit;font-size:14px;font-style:normal;font-weight:600;line-height:1.2;letter-spacing:normal;text-transform:none;text-decoration:none;text-shadow:none;box-shadow:none;';

	if ( 'secondary' === $variant ) {
		return $base . sprintf(
			'border:1px solid %1$s;background:transparent;color:%1$s',
			$o['banner_fg']
		);
	}

	return $base . sprintf(
		'border:1px solid %1$s;background:%1$s;color:%2$s',
		$o['button_bg'],
		$o['button_fg']
	);
}

/**
 * Inline style for a category on/off switch (the "off" look; the front-end
 * script repaints it to match the visitor's current choice).
 *
 * @param array $o Options.
 * @return string
 */
function mcpb_switch_style( $o ) {
	return sprintf(
		'position:relative;flex:0 0 auto;-webkit-appearance:none;appearance:none;box-sizing:border-box;width:46px;min-width:46px;height:26px;min-height:0;margin:2px 0 0;padding:0;border:2px solid %1$s;border-radius:13px;background:transparent;cursor:pointer;box-shadow:none',
		$o['banner_fg']
	);
}

/**
 * Inline style for the knob inside a switch.
 *
 * @param array $o Options.
 * @return string
 */
function mcpb_switch_knob_style( $o ) {
	return sprintf(
		'position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%%;background:%1$s;pointer-events:none;transition:transform .15s',
		$o['banner_fg']
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
	$input    = is_array( $input ) ? $input : array();
	$out      = array();

	$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

	$out['message'] = isset( $input['message'] )
		? wp_kses( $input['message'], mcpb_allowed_message_html() )
		: $defaults['message'];

	// Text fields fall back to their default when left blank.
	foreach ( array( 'accept_text', 'decline_text', 'manage_text', 'footer_link_text' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		if ( '' === $out[ $key ] ) {
			$out[ $key ] = $defaults[ $key ];
		}
	}

	$out['policy_url'] = isset( $input['policy_url'] ) ? esc_url_raw( trim( $input['policy_url'] ) ) : '';
	$out['rights_url'] = isset( $input['rights_url'] ) ? esc_url_raw( trim( $input['rights_url'] ) ) : '';

	// The description of the right to opt out is required, so blank falls back to the default.
	$out['rights_text'] = isset( $input['rights_text'] ) ? trim( wp_kses( $input['rights_text'], mcpb_allowed_message_html() ) ) : '';
	if ( '' === $out['rights_text'] ) {
		$out['rights_text'] = $defaults['rights_text'];
	}

	$out['consent_days'] = isset( $input['consent_days'] ) ? absint( $input['consent_days'] ) : 0;
	if ( $out['consent_days'] < 1 ) {
		$out['consent_days'] = $defaults['consent_days'];
	}

	$out['show_icon']     = empty( $input['show_icon'] ) ? 0 : 1;
	$out['icon_position'] = ( isset( $input['icon_position'] ) && 'right' === $input['icon_position'] ) ? 'right' : 'left';
	$out['footer_link']   = empty( $input['footer_link'] ) ? 0 : 1;

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
							Show the cookie banner and send consent signals on the front end.
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
					<th scope="row"><label for="mcpb_accept_text">Accept Button Text</label></th>
					<td>
						<input type="text" id="mcpb_accept_text" name="mcpb_options[accept_text]" value="<?php echo esc_attr( $o['accept_text'] ); ?>" class="regular-text">
						<p class="description">Keeps every category on.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_decline_text">Decline Button Text</label></th>
					<td>
						<input type="text" id="mcpb_decline_text" name="mcpb_options[decline_text]" value="<?php echo esc_attr( $o['decline_text'] ); ?>" class="regular-text">
						<p class="description">Turns Analytics, Advertising and Functional off. Essential always stays on.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_manage_text">Manage Preferences Button Text</label></th>
					<td>
						<input type="text" id="mcpb_manage_text" name="mcpb_options[manage_text]" value="<?php echo esc_attr( $o['manage_text'] ); ?>" class="regular-text">
						<p class="description">Opens the preferences dialog, where each category can be switched on or off.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_policy_url">Cookie Policy URL</label></th>
					<td>
						<input type="url" id="mcpb_policy_url" name="mcpb_options[policy_url]" value="<?php echo esc_attr( $o['policy_url'] ); ?>" class="regular-text" placeholder="/cookie-policy/">
						<p class="description">Optional. When set, a &ldquo;Cookie Policy&rdquo; link to this page is appended to the banner message and shown in the preferences dialog.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_consent_days">Remember Choice For (days)</label></th>
					<td>
						<input type="number" id="mcpb_consent_days" name="mcpb_options[consent_days]" value="<?php echo esc_attr( $o['consent_days'] ); ?>" class="small-text" min="1" step="1">
						<p class="description">How long a visitor&rsquo;s choice is kept. When it runs out the banner comes back and every category is on again, so a short value quietly undoes a Decline. California requires waiting at least 12 months before asking someone who opted out to opt back in, so keep this above 365. Defaults to <?php echo (int) $d['consent_days']; ?> days if left blank; most browsers cap cookies at about 400 days.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_rights_text">Privacy Rights Text</label></th>
					<td>
						<textarea id="mcpb_rights_text" name="mcpb_options[rights_text]" rows="3" class="large-text"><?php echo esc_textarea( $o['rights_text'] ); ?></textarea>
						<p class="description">Shown in the preferences dialog under the heading &ldquo;Do Not Sell or Share My Personal Information&rdquo;. California requires the page behind a &ldquo;Your Privacy Choices&rdquo; link to describe the right to opt out of the sale or sharing of personal information, so leaving this blank restores the default. Basic HTML (links, bold, italics) is allowed.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_rights_url">Privacy Requests URL</label></th>
					<td>
						<input type="url" id="mcpb_rights_url" name="mcpb_options[rights_url]" value="<?php echo esc_attr( $o['rights_url'] ); ?>" class="regular-text" placeholder="/privacy-requests/">
						<p class="description">Optional. A page or form for requests this dialog cannot handle: limiting the use of sensitive personal information, or an opt-out that covers a customer account rather than one browser. When set, the dialog links to it.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Cookie Settings Icon</th>
					<td>
						<label for="mcpb_show_icon">
							<input type="checkbox" id="mcpb_show_icon" name="mcpb_options[show_icon]" value="1" <?php checked( 1, (int) $o['show_icon'] ); ?>>
							Show a floating cookie-settings button once the visitor has made a choice, so they can reopen their preferences.
						</label>
						<p>
							<label for="mcpb_icon_position">Position</label>
							<select id="mcpb_icon_position" name="mcpb_options[icon_position]">
								<option value="left" <?php selected( 'left', $o['icon_position'] ); ?>>Bottom left</option>
								<option value="right" <?php selected( 'right', $o['icon_position'] ); ?>>Bottom right</option>
							</select>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Footer Link</th>
					<td>
						<label for="mcpb_footer_link">
							<input type="checkbox" id="mcpb_footer_link" name="mcpb_options[footer_link]" value="1" <?php checked( 1, (int) $o['footer_link'] ); ?>>
							Add a link at the very bottom of every page that reopens the preferences dialog.
						</label>
						<p>
							<label for="mcpb_footer_link_text">Link text</label>
							<input type="text" id="mcpb_footer_link_text" name="mcpb_options[footer_link_text]" value="<?php echo esc_attr( $o['footer_link_text'] ); ?>" class="regular-text">
						</p>
						<p class="description">To place the link yourself instead (footer menu, widget or theme), turn this off and either add a menu Custom Link with the URL <code>#privacy-choices</code> or use the shortcode <code>[mcpb_privacy_choices]</code>. California expects the link to look like the other links around it, so the footer menu is the better home if the theme has one. Keep the wording as &ldquo;Your Privacy Choices&rdquo; or &ldquo;Your California Privacy Choices&rdquo;; the official opt-out icon is added next to it wherever the link appears.</p>
						<?php if ( '' === mcpb_optout_icon_url() ) : ?>
							<div class="notice notice-warning inline"><p><strong>Opt-out icon missing.</strong> California requires the official opt-out icon next to this link. Restore <code>assets/privacyoptions.svg</code> in the plugin folder (download it from <a href="https://oag.ca.gov/privacy/ccpa/icons-download" target="_blank" rel="noopener">oag.ca.gov/privacy/ccpa/icons-download</a>).</p></div>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mcpb_banner_bg">Banner Background Color</label></th>
					<td>
						<input type="text" class="mcpb-color-field" id="mcpb_banner_bg" name="mcpb_options[banner_bg]" value="<?php echo esc_attr( $o['banner_bg'] ); ?>" data-default-color="<?php echo esc_attr( $d['banner_bg'] ); ?>">
						<p class="description">The banner colors are also used for the preferences dialog, the settings icon and the footer link.</p>
					</td>
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

		<h2>Tag Manager Reference</h2>
		<p>Every category is on until a visitor turns it off. This plugin never loads or blocks Google Tag Manager; it only reports the visitor&rsquo;s choice, and tags are controlled inside GTM.</p>
		<table class="widefat striped" style="max-width:960px">
			<tbody>
				<tr>
					<th scope="row" style="width:220px">Consent cookie name</th>
					<td><code><?php echo esc_html( MCPB_CONSENT_COOKIE ); ?></code></td>
				</tr>
				<tr>
					<th scope="row">Cookie value</th>
					<td>
						<code>essential:1|analytics:1|advertising:0|functional:1|choice:1</code><br>
						<code>1</code> = on, <code>0</code> = off. <code>choice:1</code> means the visitor chose in the banner or dialog; <code>choice:0</code> means the cookie was written automatically for a Global Privacy Control signal. No cookie means no choice yet, so every category is on.
					</td>
				</tr>
				<tr>
					<th scope="row">dataLayer event</th>
					<td>
						<code>consent_update</code>, pushed on every page load and again each time the visitor makes a choice, with:<br>
						<code>consent_essential</code>, <code>consent_analytics</code>, <code>consent_advertising</code>, <code>consent_functional</code> = <code>granted</code> or <code>denied</code><br>
						<code>consent_gpc</code> = <code>true</code> when a Global Privacy Control signal is present<br>
						<code>consent_answered</code> = <code>true</code> once the visitor has made a choice<br>
						<code>consent_action</code> = <code>load</code>, <code>accept</code>, <code>decline</code> or <code>save</code>
					</td>
				</tr>
				<tr>
					<th scope="row">Google Consent Mode v2</th>
					<td>
						Analytics &rarr; <code>analytics_storage</code><br>
						Advertising &rarr; <code>ad_storage</code>, <code>ad_user_data</code>, <code>ad_personalization</code><br>
						Functional &rarr; <code>functionality_storage</code>, <code>personalization_storage</code><br>
						Essential &rarr; <code>security_storage</code> (always granted)<br>
						The default is <code>granted</code> for everything, except the Advertising signals when Global Privacy Control is present. A saved choice is sent as an <code>update</code> on each page load, and a new one as soon as it is made.
					</td>
				</tr>
				<tr>
					<th scope="row">Global Privacy Control</th>
					<td>When the browser sends the signal, Advertising is switched off and locked off in the preferences dialog.</td>
				</tr>
				<tr>
					<th scope="row">California (CCPA)</th>
					<td>
						Advertising off is the visitor&rsquo;s opt-out of the sale or sharing of personal information for that browser, whether it comes from Decline, the Advertising switch or a Global Privacy Control signal. In GTM, treat <code>consent_advertising</code> = <code>denied</code> (or <code>advertising:0</code> in the cookie) as &ldquo;do not sell or share&rdquo; for every tag that passes data to an advertising partner, not only Google&rsquo;s.<br>
						While the opt-out is in effect the site shows &ldquo;Opt-Out Request Honored&rdquo; in the footer link bar and in the dialog, and briefly after the choice is made.
					</td>
				</tr>
				<tr>
					<th scope="row">Reopening preferences</th>
					<td>Any link to <code>#privacy-choices</code>, any element with the class <code>mcpb-open-preferences</code>, the shortcode <code>[mcpb_privacy_choices]</code>, or <code>mcpbConsent.open()</code> in JavaScript.</td>
				</tr>
				<tr>
					<th scope="row">Script order</th>
					<td>The consent commands are printed at the very top of <code>wp_head</code> so they reach the dataLayer ahead of the GTM snippet. If a caching or optimization plugin delays or combines inline JavaScript, exclude the scripts containing <code>mcpb_consent</code> and <code>mcpb-banner</code> (WP Rocket is handled automatically).</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Front end, part 1: consent bootstrap.
 *
 * Printed at the very top of <head> so the Consent Mode commands are in the
 * dataLayer before the Google Tag Manager snippet. It reads the stored choice
 * and the Global Privacy Control signal, tells Google's tags what is allowed,
 * pushes the "consent_update" dataLayer event and exposes window.mcpbConsent
 * for the banner script further down the page.
 *
 * GTM itself is never loaded, delayed or blocked by this plugin.
 * ---------------------------------------------------------------------- */

add_action( 'wp_head', 'mcpb_print_consent_bootstrap', -1000 );
/**
 * Print the consent bootstrap script (once per page).
 */
function mcpb_print_consent_bootstrap() {
	static $printed = false;
	if ( $printed ) {
		return;
	}

	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) ) {
		return;
	}
	$printed = true;

	$consent_days = max( 1, (int) $o['consent_days'] );
	?>
	<script id="mcpb-consent-bootstrap" data-cfasync="false" data-no-optimize="1" data-no-defer="1">
	(function (w, d) {
		'use strict';
		if (w.mcpbConsent) { return; }

		var COOKIE = <?php echo wp_json_encode( MCPB_CONSENT_COOKIE ); ?>;
		var DAYS = <?php echo (int) $consent_days; ?>;
		var SAVE_URL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php', 'relative' ) ); ?>;
		var OPTIONAL = ['analytics', 'advertising', 'functional'];
		var i;

		w.dataLayer = w.dataLayer || [];

		// Global Privacy Control: the browser asks not to have data sold or shared.
		var signal = w.navigator ? w.navigator.globalPrivacyControl : false;
		var gpc = signal === true || signal === 1 || signal === '1';

		// Opt-out model: everything is on until the visitor turns it off.
		var state = { essential: true, analytics: true, advertising: true, functional: true };
		var answered = false;

		// gtag() commands must reach the dataLayer as an arguments object.
		function command() { return arguments; }
		function word(on) { return on ? 'granted' : 'denied'; }

		function readCookie() {
			var match = d.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
			if (!match) { return null; }
			var raw = match[1];
			try { raw = decodeURIComponent(raw); } catch (e) {}
			var out = {};
			var parts = raw.split('|');
			var pair;
			for (var p = 0; p < parts.length; p++) {
				pair = parts[p].split(':');
				if (pair.length === 2) { out[pair[0]] = pair[1]; }
			}
			for (var c = 0; c < OPTIONAL.length; c++) {
				if (out[OPTIONAL[c]] !== '0' && out[OPTIONAL[c]] !== '1') { return null; }
			}
			return out;
		}

		function writeCookie() {
			var value = 'essential:1'
				+ '|analytics:' + (state.analytics ? 1 : 0)
				+ '|advertising:' + (state.advertising ? 1 : 0)
				+ '|functional:' + (state.functional ? 1 : 0)
				+ '|choice:' + (answered ? 1 : 0);
			var expires = new Date();
			expires.setTime(expires.getTime() + DAYS * 24 * 60 * 60 * 1000);
			d.cookie = COOKIE + '=' + value + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');

			// Safari and Brave expire cookies written by JavaScript after 7 days,
			// which would quietly undo a Decline. Ask the server to set the same
			// cookie in a response header so it lasts the full period. If the
			// request fails, the cookie written above still applies.
			if (answered && SAVE_URL && w.fetch) {
				try {
					w.fetch(SAVE_URL, {
						method: 'POST',
						credentials: 'same-origin',
						keepalive: true,
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
							'X-MCPB-Consent': '1'
						},
						body: 'action=mcpb_save_consent&consent=' + encodeURIComponent(value)
					})['catch'](function () {});
				} catch (e) {}
			}
		}

		// Google Consent Mode v2 signals for a set of category choices.
		function consentMode(s) {
			return {
				ad_storage: word(s.advertising),
				ad_user_data: word(s.advertising),
				ad_personalization: word(s.advertising),
				analytics_storage: word(s.analytics),
				functionality_storage: word(s.functional),
				personalization_storage: word(s.functional),
				security_storage: 'granted'
			};
		}

		function consentEvent(action) {
			return {
				event: 'consent_update',
				consent_essential: 'granted',
				consent_analytics: word(state.analytics),
				consent_advertising: word(state.advertising),
				consent_functional: word(state.functional),
				consent_gpc: gpc,
				consent_answered: answered,
				consent_action: action
			};
		}

		function snapshot() {
			return {
				essential: true,
				analytics: state.analytics,
				advertising: state.advertising,
				functional: state.functional,
				gpc: gpc,
				answered: answered
			};
		}

		// The 1.2.0 notice stored "mcpb_dismissed". It carried no choice, so it
		// is removed and those visitors are shown the new banner.
		if (d.cookie.indexOf('mcpb_dismissed=') !== -1) {
			d.cookie = 'mcpb_dismissed=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
		}

		var stored = readCookie();
		if (stored) {
			for (i = 0; i < OPTIONAL.length; i++) {
				state[OPTIONAL[i]] = stored[OPTIONAL[i]] === '1';
			}
			answered = stored.choice === '1';
		}
		if (gpc) {
			state.advertising = false;
			// Keep the cookie truthful for anything that reads it directly.
			if (!stored || stored.advertising === '1') { writeCookie(); }
		}

		// Page-load messages: the default, the saved choice (if any), then the event.
		var first = [command('consent', 'default', consentMode({ analytics: true, advertising: !gpc, functional: true }))];
		if (answered) {
			first.push(command('consent', 'update', consentMode(state)));
		}
		first.push(consentEvent('load'));

		// If the GTM snippet sits above this script, its "gtm.js" message is
		// already queued and GTM would fire page-load tags before reading the
		// commands above. While the container has not loaded yet the dataLayer
		// is still a plain array, so the messages can go in front of it.
		var dl = w.dataLayer;
		if (dl.length && !w.google_tag_manager && dl.push === Array.prototype.push) {
			Array.prototype.unshift.apply(dl, first);
		} else {
			for (i = 0; i < first.length; i++) { dl.push(first[i]); }
		}

		w.mcpbConsent = {
			cookieName: COOKIE,
			get: snapshot,
			set: function (choices, action) {
				choices = choices || {};
				state.analytics = !!choices.analytics;
				state.advertising = !gpc && !!choices.advertising;
				state.functional = !!choices.functional;
				answered = true;
				writeCookie();
				w.dataLayer = w.dataLayer || [];
				w.dataLayer.push(command('consent', 'update', consentMode(state)));
				w.dataLayer.push(consentEvent(action || 'save'));
				try { d.dispatchEvent(new CustomEvent('mcpb:consent', { detail: snapshot() })); } catch (e) {}
			},
			// Replaced by the banner script once the preferences dialog exists.
			open: function () {}
		};
	})(window, document);
	</script>
	<?php
}

add_action( 'wp_ajax_mcpb_save_consent', 'mcpb_ajax_save_consent' );
add_action( 'wp_ajax_nopriv_mcpb_save_consent', 'mcpb_ajax_save_consent' );
/**
 * Re-issue the consent cookie from the server.
 *
 * The browser has already written the cookie; this sets the identical value in
 * a Set-Cookie response header because Safari and Brave cap JavaScript-written
 * cookies at 7 days, which would silently undo a visitor's Decline.
 *
 * There is no nonce: pages are cached, the request changes nothing on the
 * server, and the only effect is a cookie in the caller's own browser. The
 * value must match the exact cookie format, and the custom request header
 * cannot be sent by a cross-site form or without a CORS preflight.
 */
function mcpb_ajax_save_consent() {
	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) || empty( $_SERVER['HTTP_X_MCPB_CONSENT'] ) ) {
		wp_send_json_error( null, 400 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See the docblock; validated against a strict pattern below.
	$value = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : '';
	if ( ! is_string( $value ) || ! preg_match( '/^essential:1\|analytics:[01]\|advertising:[01]\|functional:[01]\|choice:1$/D', $value ) ) {
		wp_send_json_error( null, 400 );
	}

	// Global Privacy Control also arrives as a request header.
	if ( isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === $_SERVER['HTTP_SEC_GPC'] ) {
		$value = str_replace( 'advertising:1', 'advertising:0', $value );
	}

	$max_age = DAY_IN_SECONDS * max( 1, (int) $o['consent_days'] );

	// Written by hand so the value stays byte-for-byte what the browser wrote
	// (setcookie() would URL-encode the separators). The pattern above makes
	// the value safe to place in a header.
	header(
		sprintf(
			'Set-Cookie: %1$s=%2$s; Expires=%3$s GMT; Max-Age=%4$d; Path=/; SameSite=Lax%5$s',
			MCPB_CONSENT_COOKIE,
			$value,
			gmdate( 'D, d M Y H:i:s', time() + $max_age ),
			$max_age,
			is_ssl() ? '; Secure' : ''
		),
		false
	);

	wp_send_json_success();
}

/* -------------------------------------------------------------------------
 * Front end, part 2: enhancement styles, banner, preferences dialog,
 * cookie-settings icon, footer link and the script that drives them.
 * ---------------------------------------------------------------------- */

add_action( 'wp_head', 'mcpb_print_styles' );
/**
 * Print non-critical enhancement styles only (hover, keyboard focus, mobile).
 *
 * All critical styling is applied inline on the elements in mcpb_render_banner(),
 * so everything still renders correctly if an optimizer strips this <style> block.
 */
function mcpb_print_styles() {
	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) ) {
		return;
	}
	$fg = mcpb_safe_hex( $o['banner_fg'], '#ffffff' );
	?>
	<style id="mcpb-styles">
		#mcpb-banner button:hover,#mcpb-modal button:hover,#mcpb-icon:hover{opacity:.88}
		#mcpb-banner button:focus-visible,#mcpb-banner a:focus-visible,#mcpb-modal button:focus-visible,#mcpb-modal a:focus-visible,#mcpb-footer-link a:focus-visible{outline:2px solid <?php echo $fg; // phpcs:ignore WordPress.Security.EscapeOutput -- Hex color validated above. ?>;outline-offset:2px}
		#mcpb-icon:focus-visible{outline:2px solid <?php echo $fg; // phpcs:ignore WordPress.Security.EscapeOutput -- Hex color validated above. ?>;outline-offset:-6px}
		#mcpb-modal-panel:focus{outline:0}
		@media (max-width:600px){#mcpb-banner{text-align:center!important}#mcpb-modal-panel{padding:20px!important}}
		@media (prefers-reduced-motion:reduce){.mcpb-switch,.mcpb-switch span{transition:none!important}}
	</style>
	<?php
}

add_shortcode( 'mcpb_privacy_choices', 'mcpb_privacy_choices_shortcode' );
/**
 * [mcpb_privacy_choices] - a link that reopens the preferences dialog, with
 * the California opt-out icon next to it.
 *
 * Use [mcpb_privacy_choices text="Your California Privacy Choices"] to change
 * the wording.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function mcpb_privacy_choices_shortcode( $atts ) {
	$o    = mcpb_get_options();
	$atts = shortcode_atts( array( 'text' => $o['footer_link_text'] ), $atts, 'mcpb_privacy_choices' );

	return sprintf(
		'<a href="#privacy-choices" class="mcpb-open-preferences">%1$s%2$s</a>',
		esc_html( $atts['text'] ),
		mcpb_optout_icon_html()
	);
}

add_action( 'wp_footer', 'mcpb_render_banner' );
/**
 * Output the banner, preferences dialog, settings icon, footer link and script.
 */
function mcpb_render_banner() {
	$o = mcpb_get_options();
	if ( empty( $o['enabled'] ) ) {
		return;
	}

	// Safety net for themes that never call wp_head(): without the bootstrap
	// there is no consent state for the banner script to work with.
	mcpb_print_consent_bootstrap();

	// Append a "Cookie Policy" link to the message when a URL is configured.
	$message = $o['message'];
	if ( ! empty( $o['policy_url'] ) ) {
		$message .= ' <a href="' . esc_url( $o['policy_url'] ) . '">Cookie Policy</a>';
	}

	// Force links to match the surrounding color inline, so they look right
	// even if the stylesheet is stripped by a CSS optimizer.
	$link_style  = '<a style="color:inherit;text-decoration:underline" ';
	$message     = preg_replace( '/<a\s/i', $link_style, wp_kses( $message, mcpb_allowed_message_html() ) );
	$rights_text = preg_replace( '/<a\s/i', $link_style, wp_kses( $o['rights_text'], mcpb_allowed_message_html() ) );

	$categories   = mcpb_get_categories();
	$optout_icon  = mcpb_optout_icon_html();
	$primary      = mcpb_button_style( $o, 'primary' );
	$secondary    = mcpb_button_style( $o, 'secondary' );
	$divider      = mcpb_hex_to_rgba( $o['banner_fg'], 0.25 );
	$icon_side    = ( 'right' === $o['icon_position'] ) ? 'right' : 'left';
	$text_base    = 'margin:0;padding:0;color:inherit;font-size:inherit;line-height:inherit';
	$heading_base = 'margin:0;padding:0;border:0;font-family:inherit;font-weight:700;letter-spacing:normal;text-transform:none;color:inherit;background:none';

	// "Opt-Out Request Honored" is the wording the CCPA regulations give for
	// confirming that an opt-out of sale/sharing has been processed.
	$honored = 'Opt-Out Request Honored';
	?>
	<div id="mcpb-banner" role="region" aria-label="Cookie notice" style="<?php echo esc_attr( mcpb_banner_style( $o ) ); ?>">
		<div class="mcpb-message" style="margin:0;padding:0;flex:1 1 320px;max-width:760px;color:inherit;font-size:inherit;line-height:inherit">
			<p style="<?php echo esc_attr( $text_base ); ?>"><?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput -- Sanitized above with wp_kses. ?></p>
			<p id="mcpb-banner-gpc" style="<?php echo esc_attr( 'display:none;' . $text_base . ';margin:6px 0 0' ); ?>"><strong style="font-weight:700;color:inherit"><?php echo esc_html( $honored ); ?>.</strong> Your browser&rsquo;s Global Privacy Control signal has switched Advertising off.</p>
		</div>
		<div class="mcpb-actions" style="display:flex;flex:0 1 auto;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;margin:0;padding:0">
			<button type="button" id="mcpb-accept" class="mcpb-accept" style="<?php echo esc_attr( $primary ); ?>"><?php echo esc_html( $o['accept_text'] ); ?></button>
			<button type="button" id="mcpb-decline" class="mcpb-decline" style="<?php echo esc_attr( $primary ); ?>"><?php echo esc_html( $o['decline_text'] ); ?></button>
			<button type="button" id="mcpb-manage" class="mcpb-manage" aria-haspopup="dialog" style="<?php echo esc_attr( $secondary ); ?>"><?php echo esc_html( $o['manage_text'] ); ?></button>
		</div>
	</div>

	<div id="mcpb-modal" style="display:none;position:fixed;top:0;right:0;bottom:0;left:0;z-index:100000;align-items:center;justify-content:center;box-sizing:border-box;margin:0;padding:16px;background:rgba(0,0,0,.6)">
		<div id="mcpb-modal-panel" role="dialog" aria-modal="true" aria-labelledby="mcpb-modal-title" aria-describedby="mcpb-modal-intro" tabindex="-1" style="<?php echo esc_attr( sprintf( 'box-sizing:border-box;width:100%%;max-width:580px;max-height:100%%;overflow:auto;overscroll-behavior:contain;margin:0;padding:24px;border-radius:8px;font-size:14px;line-height:1.5;text-align:left;background:%1$s;color:%2$s;box-shadow:0 10px 40px rgba(0,0,0,.4)', $o['banner_bg'], $o['banner_fg'] ) ); ?>">
			<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0 0 8px;padding:0">
				<div id="mcpb-modal-title" role="heading" aria-level="2" style="<?php echo esc_attr( $heading_base . ';font-size:20px;line-height:1.3' ); ?>">Your Privacy Choices</div>
				<button type="button" id="mcpb-modal-close" aria-label="Close" style="<?php echo esc_attr( sprintf( 'flex:0 0 auto;-webkit-appearance:none;appearance:none;box-sizing:border-box;width:32px;min-width:0;height:32px;min-height:0;margin:-4px -6px 0 0;padding:0;border:0;border-radius:4px;background:transparent;box-shadow:none;cursor:pointer;font-family:inherit;font-size:26px;font-weight:400;line-height:1;color:%s', $o['banner_fg'] ) ); ?>"><span aria-hidden="true">&times;</span></button>
			</div>
			<p id="mcpb-modal-intro" style="<?php echo esc_attr( $text_base . ';margin:0 0 16px' ); ?>">Choose how this site may use cookies and similar technologies, and whether information about your visit may be sold or shared for advertising. Everything is on unless you switch it off, and you can change your choices at any time.<?php if ( ! empty( $o['policy_url'] ) ) : ?> <a href="<?php echo esc_url( $o['policy_url'] ); ?>" style="color:inherit;text-decoration:underline">Cookie Policy</a><?php endif; ?></p>

			<?php // California: the page behind "Your Privacy Choices" must describe the right to opt out of sale/sharing (11 CCR 7015(c), 7026(a)(4)). ?>
			<div id="mcpb-rights" style="<?php echo esc_attr( sprintf( 'box-sizing:border-box;margin:0 0 16px;padding:12px 14px;border:1px solid %s;border-radius:4px;color:inherit;font-size:13px;line-height:1.5', $divider ) ); ?>">
				<div role="heading" aria-level="3" style="<?php echo esc_attr( $heading_base . ';font-size:14px;line-height:1.4' ); ?>">Do Not Sell or Share My Personal Information</div>
				<p style="margin:4px 0 0;padding:0;color:inherit;font-size:inherit;line-height:inherit"><?php echo $rights_text; // phpcs:ignore WordPress.Security.EscapeOutput -- Sanitized above with wp_kses. ?></p>
				<?php if ( ! empty( $o['rights_url'] ) ) : ?>
					<p style="margin:6px 0 0;padding:0;color:inherit;font-size:inherit;line-height:inherit">To limit the use of sensitive personal information, or to make a request that covers more than this browser, use our <a id="mcpb-rights-link" href="<?php echo esc_url( $o['rights_url'] ); ?>" style="color:inherit;text-decoration:underline">privacy requests page</a>.</p>
				<?php endif; ?>
			</div>

			<?php foreach ( $categories as $key => $category ) : ?>
				<div class="mcpb-category" style="<?php echo esc_attr( sprintf( 'display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0;padding:14px 0;border-top:1px solid %s', $divider ) ); ?>">
					<div style="flex:1 1 auto;min-width:0;margin:0;padding:0">
						<div id="<?php echo esc_attr( 'mcpb-label-' . $key ); ?>" role="heading" aria-level="3" style="<?php echo esc_attr( $heading_base . ';font-size:15px;line-height:1.4' ); ?>"><?php echo esc_html( $category['label'] ); ?></div>
						<p id="<?php echo esc_attr( 'mcpb-desc-' . $key ); ?>" style="margin:2px 0 0;padding:0;color:inherit;font-size:13px;line-height:1.5"><?php echo esc_html( $category['description'] ); ?></p>
						<?php if ( 'advertising' === $key ) : ?>
							<p id="mcpb-optout-status" style="display:none;margin:6px 0 0;padding:0;color:inherit;font-size:13px;line-height:1.5"><strong style="font-weight:700;color:inherit"><?php echo esc_html( $honored ); ?>.</strong><span id="mcpb-optout-gpc" style="display:none"> Your browser&rsquo;s Global Privacy Control signal keeps Advertising off.</span></p>
						<?php endif; ?>
					</div>
					<div style="display:flex;flex:0 0 auto;flex-direction:column;align-items:flex-end;gap:4px;width:84px;margin:0;padding:0">
						<button type="button" role="switch" class="mcpb-switch" data-mcpb-category="<?php echo esc_attr( $key ); ?>" aria-checked="<?php echo 'essential' === $key ? 'true' : 'false'; ?>" aria-labelledby="<?php echo esc_attr( 'mcpb-label-' . $key ); ?>" aria-describedby="<?php echo esc_attr( 'mcpb-desc-' . $key . ' mcpb-state-' . $key ); ?>"<?php echo 'essential' === $key ? ' aria-disabled="true"' : ''; ?> style="<?php echo esc_attr( mcpb_switch_style( $o ) ); ?>"><span style="<?php echo esc_attr( mcpb_switch_knob_style( $o ) ); ?>"></span></button>
						<?php // Words beside every switch, because position and color alone do not say what was chosen. ?>
						<span id="<?php echo esc_attr( 'mcpb-state-' . $key ); ?>" class="mcpb-state" style="display:block;margin:0;padding:0;color:inherit;font-size:12px;line-height:1.3;text-align:right"></span>
					</div>
				</div>
			<?php endforeach; ?>
			<div style="<?php echo esc_attr( sprintf( 'display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:10px;margin:0;padding:18px 0 0;border-top:1px solid %s', $divider ) ); ?>">
				<button type="button" id="mcpb-modal-decline" style="<?php echo esc_attr( $secondary ); ?>">Decline all</button>
				<button type="button" id="mcpb-modal-accept" style="<?php echo esc_attr( $secondary ); ?>">Accept all</button>
				<button type="button" id="mcpb-modal-save" style="<?php echo esc_attr( $primary ); ?>">Save preferences</button>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $o['show_icon'] ) ) : ?>
		<button type="button" id="mcpb-icon" class="mcpb-open-preferences" aria-label="Cookie settings" title="Cookie settings" aria-haspopup="dialog" style="<?php echo esc_attr( sprintf( 'display:none;position:fixed;bottom:16px;%1$s:16px;z-index:99998;align-items:center;justify-content:center;-webkit-appearance:none;appearance:none;box-sizing:border-box;width:44px;min-width:0;height:44px;min-height:0;margin:0;padding:0;border:1px solid %2$s;border-radius:50%%;cursor:pointer;background:%3$s;color:%4$s;box-shadow:0 2px 10px rgba(0,0,0,.25)', $icon_side, mcpb_hex_to_rgba( $o['banner_fg'], 0.35 ), $o['banner_bg'], $o['banner_fg'] ) ); ?>">
			<svg aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd" style="display:block;pointer-events:none"><path d="M21.95 11A10 10 0 1 1 13 2.05a4 4 0 0 0 3.5 5.45 4 4 0 0 0 5.45 3.5zM7.2 9.6a1.4 1.4 0 1 0 2.8 0a1.4 1.4 0 1 0-2.8 0zM10.2 15.6a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0-3 0zM14.8 12.2a1.1 1.1 0 1 0 2.2 0a1.1 1.1 0 1 0-2.2 0zM6.4 14a1 1 0 1 0 2 0a1 1 0 1 0-2 0z"/></svg>
		</button>
	<?php endif; ?>

	<?php // Short confirmation shown right after an opt-out, and once per visit for Global Privacy Control. It sits above the settings icon so it never covers the footer link. ?>
	<div id="mcpb-toast" role="status" aria-live="polite" style="<?php echo esc_attr( sprintf( 'display:none;position:fixed;%1$s:16px;bottom:72px;z-index:99998;box-sizing:border-box;max-width:calc(100%% - 32px);margin:0;padding:10px 16px;border:1px solid %2$s;border-radius:4px;font-size:14px;font-weight:600;line-height:1.4;text-align:left;background:%3$s;color:%4$s;box-shadow:0 2px 12px rgba(0,0,0,.25)', $icon_side, mcpb_hex_to_rgba( $o['banner_fg'], 0.35 ), $o['banner_bg'], $o['banner_fg'] ) ); ?>"></div>

	<?php if ( ! empty( $o['footer_link'] ) ) : ?>
		<?php // Side padding keeps the link clear of the floating settings icon on narrow screens. ?>
		<div id="mcpb-footer-link" style="<?php echo esc_attr( sprintf( 'box-sizing:border-box;margin:0;padding:12px %1$s;text-align:center;font-size:13px;line-height:1.5;background:%2$s;color:%3$s', empty( $o['show_icon'] ) ? '20px' : '68px', $o['banner_bg'], $o['banner_fg'] ) ); ?>">
			<a href="#privacy-choices" class="mcpb-open-preferences" style="display:inline-block;margin:0 7px;color:inherit;text-decoration:underline"><?php echo esc_html( $o['footer_link_text'] ); ?><?php echo $optout_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- Built and escaped in mcpb_optout_icon_html(). ?></a>
			<span id="mcpb-footer-status" style="display:none;margin:0 7px;padding:0;color:inherit;font-weight:700;white-space:nowrap"><?php echo esc_html( $honored ); ?></span>
		</div>
	<?php endif; ?>

	<?php // Extra room at the end of the page while the banner is up, so the fixed banner never hides the site footer or the privacy link. ?>
	<div id="mcpb-spacer" aria-hidden="true" style="height:0;margin:0;padding:0;border:0"></div>

	<script id="mcpb-banner-script" data-cfasync="false" data-no-optimize="1" data-no-defer="1">
	(function (w, d) {
		'use strict';
		var api = w.mcpbConsent;
		var banner = d.getElementById('mcpb-banner');
		var modal = d.getElementById('mcpb-modal');
		var panel = d.getElementById('mcpb-modal-panel');
		if (!api || !banner || !modal || !panel) { return; }

		var icon = d.getElementById('mcpb-icon');
		var toast = d.getElementById('mcpb-toast');
		var spacer = d.getElementById('mcpb-spacer');
		var switches = modal.querySelectorAll('.mcpb-switch');
		var COLORS = <?php echo wp_json_encode( array( 'on' => $o['button_bg'], 'onKnob' => $o['button_fg'], 'offKnob' => $o['banner_fg'] ) ); ?>;
		var TEXT = <?php echo wp_json_encode( array( 'honored' => $honored, 'alwaysOn' => 'Always on', 'allowed' => 'Allowed', 'notAllowed' => 'Not allowed', 'doNotSell' => 'Do not sell or share' ) ); ?>;
		var OPTOUT_ICON = <?php echo wp_json_encode( $optout_icon ); ?>;
		var lastFocus = null;
		var toastTimer = null;
		var i;

		function byId(id) { return d.getElementById(id); }
		function show(el, display) { if (el) { el.style.display = display; } }
		function isVisible(el) { return !!(el && el.getClientRects().length); }
		function isOn(sw) { return sw.getAttribute('aria-checked') === 'true'; }
		function isLocked(sw) { return sw.getAttribute('aria-disabled') === 'true'; }
		function categoryOf(sw) { return sw.getAttribute('data-mcpb-category'); }

		// Switches are painted with inline styles so they survive CSS optimizers.
		function paint(sw, on, locked) {
			var knob = sw.firstElementChild;
			var cat = categoryOf(sw);
			var state = byId('mcpb-state-' + cat);
			sw.setAttribute('aria-checked', on ? 'true' : 'false');
			sw.style.background = on ? COLORS.on : 'transparent';
			if (knob) {
				knob.style.background = on ? COLORS.onKnob : COLORS.offKnob;
				knob.style.transform = on ? 'translateX(20px)' : 'translateX(0)';
			}
			if (locked) {
				sw.setAttribute('aria-disabled', 'true');
				sw.style.opacity = '.55';
				sw.style.cursor = 'not-allowed';
			} else {
				sw.removeAttribute('aria-disabled');
				sw.style.opacity = '1';
				sw.style.cursor = 'pointer';
			}
			if (state) {
				state.textContent = cat === 'essential' ? TEXT.alwaysOn
					: on ? TEXT.allowed
					: cat === 'advertising' ? TEXT.doNotSell
					: TEXT.notAllowed;
			}
		}

		// "Opt-Out Request Honored" wherever it applies. It follows the saved
		// choice, never a switch the visitor has moved but not saved yet.
		function updateOptOutStatus() {
			var s = api.get();
			var honored = !s.advertising;
			var adSwitch = modal.querySelector('.mcpb-switch[data-mcpb-category="advertising"]');
			show(byId('mcpb-footer-status'), honored ? 'inline-block' : 'none');
			show(byId('mcpb-banner-gpc'), s.gpc ? 'block' : 'none');
			show(byId('mcpb-optout-status'), honored && adSwitch && !isOn(adSwitch) ? 'block' : 'none');
			show(byId('mcpb-optout-gpc'), s.gpc ? 'inline' : 'none');
		}

		function syncSwitches() {
			var s = api.get();
			var cat;
			for (var n = 0; n < switches.length; n++) {
				cat = categoryOf(switches[n]);
				paint(switches[n], !!s[cat], cat === 'essential' || (cat === 'advertising' && s.gpc));
			}
			updateOptOutStatus();
		}

		function readSwitches() {
			var out = {};
			for (var n = 0; n < switches.length; n++) {
				out[categoryOf(switches[n])] = isOn(switches[n]);
			}
			return out;
		}

		// The page ends with a gap as tall as the banner, so its last lines can
		// be scrolled clear of it.
		function fitSpacer() {
			if (spacer) { spacer.style.height = (isVisible(banner) ? banner.offsetHeight : 0) + 'px'; }
		}

		// Banner until the visitor has chosen; the settings icon afterwards.
		function refresh() {
			var answered = api.get().answered;
			banner.style.display = answered ? 'none' : 'flex';
			if (icon) { icon.style.display = answered ? 'flex' : 'none'; }
			fitSpacer();
			syncSwitches();
		}

		function announce(text) {
			if (!toast) { return; }
			if (toastTimer) { w.clearTimeout(toastTimer); }
			toast.textContent = '';
			toast.style.display = 'block';
			// Filled a moment later so screen readers pick up the change.
			w.setTimeout(function () { toast.textContent = text; }, 60);
			toastTimer = w.setTimeout(function () { toast.style.display = 'none'; }, 7000);
		}

		function isOpen() { return modal.style.display !== 'none'; }

		function openModal(trigger) {
			if (isOpen()) { return; }
			lastFocus = (trigger && trigger.focus) ? trigger : d.activeElement;
			syncSwitches();
			modal.style.display = 'flex';
			panel.scrollTop = 0;
			panel.focus();
		}

		function closeModal() {
			if (!isOpen()) { return; }
			modal.style.display = 'none';
			var target = isVisible(lastFocus) ? lastFocus : (isVisible(icon) ? icon : null);
			lastFocus = null;
			if (target) { target.focus(); }
		}

		// True the first time it is asked in a visit (tab session).
		function firstGpcNotice() {
			var seen = false;
			try {
				seen = w.sessionStorage.getItem('mcpb_gpc_notice') === '1';
				w.sessionStorage.setItem('mcpb_gpc_notice', '1');
			} catch (e) {}
			return !seen;
		}

		function choose(choices, action) {
			api.set(choices, action);
			refresh();
			closeModal();
			// Confirm a processed opt-out of sale/sharing straight away.
			var s = api.get();
			if (!s.advertising) {
				if (s.gpc) { firstGpcNotice(); }
				announce(TEXT.honored);
			}
		}

		var ALL_ON = { analytics: true, advertising: true, functional: true };
		var ALL_OFF = { analytics: false, advertising: false, functional: false };

		byId('mcpb-accept').addEventListener('click', function () { choose(ALL_ON, 'accept'); });
		byId('mcpb-decline').addEventListener('click', function () { choose(ALL_OFF, 'decline'); });
		byId('mcpb-manage').addEventListener('click', function () { openModal(this); });
		byId('mcpb-modal-accept').addEventListener('click', function () { choose(ALL_ON, 'accept'); });
		byId('mcpb-modal-decline').addEventListener('click', function () { choose(ALL_OFF, 'decline'); });
		byId('mcpb-modal-save').addEventListener('click', function () { choose(readSwitches(), 'save'); });
		byId('mcpb-modal-close').addEventListener('click', closeModal);

		for (i = 0; i < switches.length; i++) {
			switches[i].addEventListener('click', function () {
				if (!isLocked(this)) {
					paint(this, !isOn(this), false);
					updateOptOutStatus();
				}
			});
		}

		// Clicking the dimmed backdrop closes the dialog without saving.
		modal.addEventListener('mousedown', function (e) {
			if (e.target === modal) { closeModal(); }
		});

		d.addEventListener('keydown', function (e) {
			if (!isOpen()) { return; }
			if (e.key === 'Escape' || e.key === 'Esc') {
				e.preventDefault();
				closeModal();
				return;
			}
			if (e.key !== 'Tab') { return; }
			// Keep keyboard focus inside the dialog while it is open.
			var nodes = panel.querySelectorAll('button, a[href]');
			var items = [];
			for (var n = 0; n < nodes.length; n++) {
				if (isVisible(nodes[n])) { items.push(nodes[n]); }
			}
			if (!items.length) { return; }
			var firstItem = items[0];
			var lastItem = items[items.length - 1];
			var active = d.activeElement;
			if (e.shiftKey && (active === firstItem || active === panel || !panel.contains(active))) {
				e.preventDefault();
				lastItem.focus();
			} else if (!e.shiftKey && (active === lastItem || !panel.contains(active))) {
				e.preventDefault();
				firstItem.focus();
			}
		});

		// Anything that should reopen preferences: the settings icon, the footer
		// link, the shortcode, a menu link to #privacy-choices, or a custom
		// element with the mcpb-open-preferences class.
		d.addEventListener('click', function (e) {
			var el = e.target;
			if (!el || !el.closest) { return; }
			var opener = el.closest('.mcpb-open-preferences, a[href$="#privacy-choices"]');
			if (opener) {
				e.preventDefault();
				openModal(opener);
			}
		});

		// California wants the opt-out icon beside the link title, so add it to
		// links placed by hand (a footer menu item, for example). A link with
		// the class mcpb-no-icon is left alone.
		if (OPTOUT_ICON) {
			var links = d.querySelectorAll('a[href$="#privacy-choices"]');
			for (i = 0; i < links.length; i++) {
				if (!links[i].querySelector('.mcpb-optout-icon') && !/(^|\s)mcpb-no-icon(\s|$)/.test(links[i].className)) {
					links[i].insertAdjacentHTML('beforeend', OPTOUT_ICON);
				}
			}
		}

		api.open = function () { openModal(null); };

		refresh();
		w.addEventListener('resize', fitSpacer);

		// A Global Privacy Control visitor who has already dealt with the banner
		// gets the confirmation once per visit; before that the banner carries it.
		var now = api.get();
		if (now.gpc && now.answered && firstGpcNotice()) { announce(TEXT.honored); }

		if (w.location.hash === '#privacy-choices') { openModal(null); }
	})(window, document);
	</script>
	<?php
}

/* -------------------------------------------------------------------------
 * WP Rocket compatibility.
 *
 * These filters run only if WP Rocket is present; otherwise nothing listens to
 * them and they are harmless. They keep the plugin's selectors in the generated
 * "used CSS" and keep both inline scripts out of "Delay JavaScript Execution",
 * "Load JavaScript Deferred" and "Combine JavaScript", so the consent commands
 * still run ahead of Google Tag Manager and the buttons respond on first click.
 * ---------------------------------------------------------------------- */

/**
 * Strings that identify this plugin's two inline scripts.
 *
 * @return array
 */
function mcpb_inline_script_markers() {
	return array( MCPB_CONSENT_COOKIE, 'mcpb-banner' );
}

add_filter( 'rocket_rucss_safelist', 'mcpb_rocket_rucss_safelist' );
/**
 * Keep the plugin's CSS when WP Rocket "Remove Unused CSS" is enabled.
 *
 * @param array $safelist Existing safelist entries.
 * @return array
 */
function mcpb_rocket_rucss_safelist( $safelist ) {
	$safelist[] = '#mcpb-banner';
	$safelist[] = '.mcpb-message';
	$safelist[] = '#mcpb-modal';
	$safelist[] = '#mcpb-modal-panel';
	$safelist[] = '.mcpb-switch';
	$safelist[] = '#mcpb-icon';
	$safelist[] = '#mcpb-footer-link';
	$safelist[] = '#mcpb-toast';
	$safelist[] = '.mcpb-optout-icon';
	return $safelist;
}

add_filter( 'rocket_delay_js_exclusions', 'mcpb_rocket_delay_js_exclusions' );
/**
 * Exclude the plugin's scripts from WP Rocket "Delay JavaScript Execution".
 *
 * @param array $exclusions Existing exclusion patterns.
 * @return array
 */
function mcpb_rocket_delay_js_exclusions( $exclusions ) {
	return array_merge( (array) $exclusions, mcpb_inline_script_markers() );
}

add_filter( 'rocket_defer_inline_exclusions', 'mcpb_rocket_inline_js_exclusions' );
add_filter( 'rocket_excluded_inline_js_content', 'mcpb_rocket_inline_js_exclusions' );
/**
 * Exclude the plugin's scripts from WP Rocket's inline defer and JS combine.
 *
 * @param array|string $exclusions Existing exclusion patterns.
 * @return array
 */
function mcpb_rocket_inline_js_exclusions( $exclusions ) {
	if ( ! is_array( $exclusions ) ) {
		$exclusions = array();
	}
	return array_merge( $exclusions, mcpb_inline_script_markers() );
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
