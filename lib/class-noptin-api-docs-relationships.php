<?php

/**
 * Registers and implements relationships with Posts 2 Posts.
 */
class Noptin_Api_Docs_Relationships {

	/**
	 * @var array Post types we're setting relationships between
	 */
	public $post_types;

	/**
	 * @var array Map of post slugs to post ids.
	 */
	public $slugs_to_ids = array();

	/**
	 * Map of how post IDs relate to one another.
	 *
	 * array(
	 *   $from_type => array(
	 *     $from_id => array(
	 *       $to_type => array(
	 *         $to_slug => $to_id
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 * @var array
	 */
	public $relationships = array();

	/**
	 * Adds the actions.
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'register_post_relationships' ) );

	}

	/**
	 * Set up relationships using Posts to Posts plugin.
	 *
	 * Default settings for p2p_register_connection_type:
	 *   'cardinality' => 'many-to-many'
	 *   'reciprocal' => false
	 *
	 * @link  https://github.com/scribu/wp-posts-to-posts/wiki/p2p_register_connection_type
	 */
	public function register_post_relationships() {

		/*
		 * Functions to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name' => 'functions_to_functions',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-function',
			'self_connections' => 'true',
			'title' => array( 'from' => 'Uses Functions', 'to' => 'Used by Functions' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'functions_to_methods',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-method',
			'title' => array( 'from' => 'Uses Methods', 'to' => 'Used by Functions' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'functions_to_hooks',
			'from' => 'wp-parser-function',
			'to' => 'wp-parser-hook',
			'title' => array( 'from' => 'Uses Hooks', 'to' => 'Used by Functions' ),
		) );

		/*
		 * Methods to functions, methods and hooks
		 */
		p2p_register_connection_type( array(
			'name' => 'methods_to_functions',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-function',
			'title' => array( 'from' => 'Uses Functions', 'to' => 'Used by Methods' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'methods_to_methods',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-method',
			'self_connections' => 'true',
			'title' => array( 'from' => 'Uses Methods', 'to' => 'Used by Methods' ),
		) );

		p2p_register_connection_type( array(
			'name' => 'methods_to_hooks',
			'from' => 'wp-parser-method',
			'to' => 'wp-parser-hook',
			'title' => array( 'from' => 'Used by Methods', 'to' => 'Uses Hooks' ),
		) );
	}

	/**
	 * Checks to see if the posts to posts tables exist and returns if they do
	 *
	 * @return bool Whether or not the posts 2 posts tables exist.
	 */
	public function p2p_tables_exist() {
		global $wpdb;

		$tables = $wpdb->get_col( 'SHOW TABLES' );

		// There is no way to get the name out of P2P so we hard code it here.
		return in_array( $wpdb->prefix . 'p2p', $tables );
	}

	/**
	 * Map a name to slug, taking into account namespace context.
	 *
	 * When a function is called within a namespace, the function is first looked
	 * for in the current namespace. If it exists, the namespaced version is used.
	 * If the function does not exist in the current namespace, PHP tries to find
	 * the function in the global scope.
	 *
	 * Unless the call has been prefixed with '\' indicating it is fully qualified
	 * we need to check first in the current namespace and then in the global
	 * scope.
	 *
	 * This also catches the case where relative namespaces are used. You can
	 * create a file in namespace `\Foo` and then call a funtion called `baz` in
	 * namespace `\Foo\Bar\` by just calling `Bar\baz()`. PHP will first look
	 * for `\Foo\Bar\baz()` and if it can't find it fall back to `\Bar\baz()`.
	 *
	 * @see    WP_Parser\Importer::import_item()
	 * @param  string $name      The name of the item a slug is needed for.
	 * @param  string $namespace The namespace the item is in when for context.
	 * @return array             An array of slugs, starting with the context of the
	 *                           namespace, and falling back to the global namespace.
	 */
	public function names_to_slugs( $name, $namespace = null ) {
		$fully_qualified = ( 0 === strpos( '\\', $name ) );
		$name = ltrim( $name, '\\' );
		$names = array();

		if ( $namespace && ! $fully_qualified  ) {
			$names[] = $this->name_to_slug( $namespace . '\\' . $name );
		}
		$names[] = $this->name_to_slug( $name );

		return $names;
	}

	/**
	 * Simple conversion of a method, function, or hook name to a post slug.
	 *
	 * Replaces '::' and '\' to dashes and then runs the name through `sanitize_title()`.
	 *
	 * @param  string $name Method, function, or hook name
	 * @return string       The post slug for the passed name.
	 */
	public function name_to_slug( $name ) {
		return sanitize_title( str_replace( '\\', '-', str_replace( '::', '-', $name ) ) );
	}

	/**
	 * Convert a post slug to an array( 'slug' => id )
	 * Ignores slugs that are not found in $slugs_to_ids
	 *
	 * @param  array $slugs         Array of post slugs.
	 * @param  array $slugs_to_ids  Map of slugs to IDs.
	 * @return array
	 */
	public function get_ids_for_slugs( array $slugs, array $slugs_to_ids ) {
		$slugs_with_ids = array();

		foreach ( $slugs as $index => $scoped_slugs ) {
			// Find the first matching scope the ID exists for.
			foreach ( $scoped_slugs as $slug ) {
				if ( array_key_exists( $slug, $slugs_to_ids ) ) {
					$slugs_with_ids[ $slug ] = $slugs_to_ids[ $slug ];
					// if we found it in this scope, stop searching the chain.
					continue;
				}
			}
		}

		return $slugs_with_ids;
	}
}
