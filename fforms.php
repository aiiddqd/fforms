<?php
/**
 * Plugin Name: FForms
 * Description: Lightweight, headless-friendly plugin that receives, stores and processes form data in WordPress.
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Delta Development
 * Text Domain: fforms
 * Domain Path: /languages
 * Version: 0.1.260826
 */

namespace FForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FFORMS_VERSION', '1.0.0' );
define( 'FFORMS_FILE', __FILE__ );
define( 'FFORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFORMS_URL', plugin_dir_url( __FILE__ ) );

require_once FFORMS_DIR . 'includes/Schema.php';
require_once FFORMS_DIR . 'includes/Schema/Schema_Compiler.php';
require_once FFORMS_DIR . 'includes/Schema/Schema_Repository.php';
require_once FFORMS_DIR . 'includes/Migration/Legacy_Migration.php';
require_once FFORMS_DIR . 'includes/Migration/Mode_Migration.php';
require_once FFORMS_DIR . 'includes/Migration/Type_Meta_Migration.php';
require_once FFORMS_DIR . 'includes/Post_Types.php';
require_once FFORMS_DIR . 'includes/Form_Types.php';
require_once FFORMS_DIR . 'includes/Default_Forms.php';
require_once FFORMS_DIR . 'includes/Form_Ref.php';
require_once FFORMS_DIR . 'includes/Registry/Code_Forms.php';
require_once FFORMS_DIR . 'includes/Registry/Main_Form.php';
require_once FFORMS_DIR . 'includes/Form_Locator.php';
require_once FFORMS_DIR . 'includes/functions.php';
require_once FFORMS_DIR . 'includes/Settings.php';
require_once FFORMS_DIR . 'includes/Notifications.php';
require_once FFORMS_DIR . 'includes/REST_Controller.php';
require_once FFORMS_DIR . 'includes/CORS.php';
require_once FFORMS_DIR . 'includes/Public_Form.php';
require_once FFORMS_DIR . 'includes/Blocks/Form_Renderer.php';
require_once FFORMS_DIR . 'includes/Block.php';
require_once FFORMS_DIR . 'includes/Shortcode.php';
require_once FFORMS_DIR . 'includes/Export.php';
require_once FFORMS_DIR . 'includes/Dashboard.php';
require_once FFORMS_DIR . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );

Plugin::boot();
