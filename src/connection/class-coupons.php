<?php
/**
 * Connection type - Coupons
 *
 * Registers connections to Coupons
 *
 * @package WPGraphQL\Extensions\WooCommerce\Connection
 * @since 0.0.1
 */

namespace WPGraphQL\Extensions\WooCommerce\Connection;

use WPGraphQL\Extensions\WooCommerce\Data\Factory;
use WPGraphQL\Data\DataSource;

/**
 * Class Coupons
 */
class Coupons {
	/**
	 * Registers the various connections from other Types to Coupon
	 */
	public static function register_connections() {
		register_graphql_connection( self::get_connection_config() );
	}

	/**
	 * Given an array of $args, this returns the connection config, merging the provided args
	 * with the defaults
	 *
	 * @access public
	 * @param array $args Connection configuration
	 *
	 * @return array
	 */
	public static function get_connection_config( $args = array() ) {
		$defaults = array(
			'fromType'       => 'RootQuery',
			'toType'         => 'Coupon',
			'queryClass'       => 'WP_Query',
			'connectionFields' => [
				'postTypeInfo' => [
					'type'        => 'PostType',
					'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
					'resolve'     => function ( $source, array $args, $context, $info ) {
						return DataSource::resolve_post_type( 'shop_coupon' );
					},
				],
			],
			'resolveNode'      => function( $id, $args, $context, $info ) {
				return Factory::resolve_coupon( $id, $context );
			},
			'fromFieldName'  => 'coupons',
			'connectionArgs' => self::get_connection_args(),
			'resolve'        => function ( $root, $args, $context, $info ) {
				return Factory::resolve_coupon_connection( $root, $args, $context, $info );
			},
		);

		return array_merge( $defaults, $args );
	}

	/**
	 * This returns the connection args for the Coupon connection
	 *
	 * @access public
	 * @return array
	 */
	public static function get_connection_args() {
		return array(
			'code' => array(
				'type'        => 'String',
				'description' => __( 'Coupon code', 'wp-graphql-woocommerce' ),
			),
		);
	}
}
