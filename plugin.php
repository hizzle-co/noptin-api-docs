<?php
/**
 * Plugin Name: Noptin docs
 * Description: Create a function reference site powered by WordPress
 * Author: picocodes
 * Author URI: https://noptin.co
 * Plugin URI: https://noptin.co
 * Version:
 * Text Domain: wp-parser
 */

$dir = plugin_dir_path( __FILE__ );
require_once( "$dir/lib/class-noptin-api-docs.php" );
new Noptin_Api_Docs();

require_once( "$dir/lib/class-noptin-api-docs-relationships.php" );
new Noptin_Api_Docs_Relationships();

require_once( "$dir/lib/class-noptin-api-docs-render.php" );
new Noptin_Api_Docs_Render();
