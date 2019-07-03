<?php

//define('ALLOW_UNFILTERED_UPLOADS', true);
define('DISALLOW_FILE_EDIT', true);

// no direct access
if (!defined('WPINC')) {
	exit;
}

class PXL_Cleanup {

	private $hideDefaultPost = false;
	private $hideThemeMenu = false;
	private $woocommerce = false;
	private $gravityForms = true;
	private $advancedCustomFields = true;
	private $hideUpdateNags = false;
	private $disableEmojis = true;
	private $hideAdminMenu = true;

	function __construct()
	{
		/* general */
		add_action('wp_dashboard_setup', [$this, 'remove_default_dashboard_widgets'], 999);
		add_filter('upload_mimes', [$this, 'allow_svg'], 10, 1);
		add_filter('wpseo_metabox_prio', [$this, 'reduce_yoast_metabox_priority']);
		add_action('admin_menu', [$this, 'move_menus_link']);
		if ($this->hideThemeMenu) {
			add_action('admin_menu', [$this, 'remove_themes_menu']);
		}
		if ($this->hideDefaultPost) {
			add_action('admin_menu', [$this, 'remove_default_posttype']);
		}
		remove_action('after_password_reset', 'wp_password_change_notification');
		if ($this->hideAdminMenu) {
			add_action('after_setup_theme', [$this, 'disable_admin_bar']);
		}

		add_filter('embed_oembed_html', [$this, 'oembed_wrapper'], 99, 4);

		/* gravity forms */
		if ($this->gravityForms) {
			add_filter('gform_init_scripts_footer', '__return_true');
		}

		/* woocommerce */
		if ($this->woocommerce) {
			add_filter('woocommerce_enqueue_styles', '__return_empty_array');
			add_action('wp_print_scripts', [$this, 'woo_remove_password_strength_requirement'], 100);
		}

		/** advanced custom fields */
		if ($this->advancedCustomFields) {
			add_filter('acf/settings/show_admin', [$this, 'acf_show_menu_item']);
		}

		/* update nags */
		if ($this->hideUpdateNags) {
			add_filter('pre_site_transient_update_core', [$this, 'remove_core_updates']);
			add_filter('pre_site_transient_update_plugins', [$this, 'remove_core_updates']);
			add_filter('pre_site_transient_update_themes', [$this, 'remove_core_updates']);
		}

		/* emojis */
		if ($this->disableEmojis) {
			remove_action('wp_head', 'print_emoji_detection_script', 7);
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('wp_print_styles', 'print_emoji_styles');
			remove_action('admin_print_styles', 'print_emoji_styles');
			remove_filter('the_content_feed', 'wp_staticize_emoji');
			remove_filter('comment_text_rss', 'wp_staticize_emoji');
			remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
			add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
			add_filter('wp_resource_hints', [$this, 'disable_emojis_remove_dns_prefetch'], 10, 2);
		}

		/* revisions */
        add_filter('wp_revisions_to_keep', [$this, 'wp_revisions_to_keep'], 10, 2);
	}

	function allow_svg($mimes)
	{
		$mimes['svg'] = 'image/svg+xml';
		$mimes['svgz'] = 'image/svgz+xml';

		return $mimes;
	}

	function reduce_yoast_metabox_priority()
	{
		return 'low';
	}

	function remove_default_dashboard_widgets()
	{
		global $wp_meta_boxes;

		unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
		unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);
		unset($wp_meta_boxes['dashboard']['normal']['core']['wpseo-dashboard-overview']);
		unset($wp_meta_boxes['dashboard']['normal']['core']['example_dashboard_widget']);
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);

		remove_action('welcome_panel', 'wp_welcome_panel');
	}

	function move_menus_link()
	{
		remove_submenu_page('themes.php', 'nav-menus.php');

		add_menu_page(
			'Menus',
			'Menus',
			'edit_theme_options',
			'nav-menus.php',
			'',
			'dashicons-list-view',
			60
		);
	}

	function remove_themes_menu()
	{
		remove_menu_page('themes.php');
		remove_submenu_page( 'themes.php', 'customize.php' );
		remove_menu_page('theme-editor.php');

		global $submenu;
		unset($submenu['themes.php'][5]);
		unset($submenu['themes.php'][6]);
	}

	function remove_default_posttype()
	{
		remove_menu_page('edit.php');
		remove_menu_page('edit-comments.php');
	}

	function disable_admin_bar()
	{
		show_admin_bar(false);
	}

	function oembed_wrapper($html, $url, $attr, $post_id)
	{
		return '<div class="oembed__wrapper">'. $html .'</div>';
	}

	function load_gform_scripts_in_footer()
	{
		return true;
	}

	function woo_remove_password_strength_requirement()
	{
		if (wp_script_is('wc-password-strength-meter', 'enqueued')) {
			wp_dequeue_script('wc-password-strength-meter');
		}
	}

	function remove_core_updates()
	{
		global $wp_version;

		return (object) ['last_checked' => time(), 'version_checked' => $wp_version];
	}

	function acf_show_menu_item()
	{
		if (get_current_user_id() != 1) {
			return false;
		}
		return true;
	}

	function disable_emojis_tinymce($plugins)
	{
		if (is_array($plugins)) {
			return array_diff($plugins, ['wpemoji']);
		} else {
			return [];
		}
	}

	function disable_emojis_remove_dns_prefetch($urls, $relation_type)
	{
		if ('dns-prefetch' == $relation_type) {
			$emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');

			$urls = array_diff($urls, [$emoji_svg_url]);
		}

		return $urls;
	}

	function wp_revisions_to_keep($number, $post)
    {
        return 3;
    }

}

new PXL_Cleanup();


// Disable Change Password Notification
if (!function_exists('wp_password_change_notification')) {
	function wp_password_change_notification() {}
}