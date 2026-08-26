<?php
/**
 * Plugin Name: FForms
 * Description: Лёгкий, headless-friendly плагин для приёма, хранения и обработки данных из форм в WordPress.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Delta Development
 * Text Domain: fforms
 */

namespace FForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FFORMS_VERSION', '1.0.0' );
define( 'FFORMS_FILE', __FILE__ );
define( 'FFORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFORMS_URL', plugin_dir_url( __FILE__ ) );

require_once FFORMS_DIR . 'includes/class-schema.php';
require_once FFORMS_DIR . 'includes/Schema/class-schema-compiler.php';
require_once FFORMS_DIR . 'includes/Schema/class-schema-repository.php';
require_once FFORMS_DIR . 'includes/Migration/class-legacy-migration.php';
require_once FFORMS_DIR . 'includes/class-post-types.php';
require_once FFORMS_DIR . 'includes/class-settings.php';
require_once FFORMS_DIR . 'includes/class-notifications.php';
require_once FFORMS_DIR . 'includes/class-rest-controller.php';
require_once FFORMS_DIR . 'includes/Blocks/class-form-renderer.php';
require_once FFORMS_DIR . 'includes/class-block.php';
require_once FFORMS_DIR . 'includes/class-export.php';
require_once FFORMS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

Plugin::boot();
