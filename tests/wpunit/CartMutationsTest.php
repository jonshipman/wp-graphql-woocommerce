<?php

class CartMutationsTest extends \Tests\WPGraphQL\WooCommerce\TestCase\WooGraphQLTestCase {
    public function setUp(): void {
        parent::setUp();

        $this->shop_manager = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
        $this->customer     = $this->getModule('\Helper\Wpunit')->customer();
        $this->coupon       = $this->getModule('\Helper\Wpunit')->coupon();
        $this->variation    = $this->getModule('\Helper\Wpunit')->product_variation();
        $this->cart         = $this->getModule('\Helper\Wpunit')->cart();
    }

    public function tearDown(): void {
        \WC()->cart->empty_cart( true );

        parent::tearDown();
	}

    private function addToCart( $input ) {
        $query = '
            mutation( $input: AddToCartInput! ) {
                addToCart( input: $input ) {
                    clientMutationId
                    cartItem {
                        key
                        product {
                            ... on SimpleProduct {
                                id
                            }
                            ... on VariableProduct {
                                id
                            }
                        }
                        variation {
                            id
                        }
                        quantity
                        subtotal
                        subtotalTax
                        total
                        tax
                    }
                }
            }
		';

		$variables = compact( 'input' );

        return $this->graphql( compact( 'query', 'variables' ) );
    }

    private function removeItemsFromCart( $input ) {
        $query = '
            mutation( $input: RemoveItemsFromCartInput! ) {
                removeItemsFromCart( input: $input ) {
                    clientMutationId
                    cartItems {
                        key
                    }
                }
            }
        ';
		$variables = compact( 'input' );

        return $this->graphql( compact( 'query', 'variables' ) );
    }

    private function restoreItems( $input ) {
        $query = '
            mutation( $input: RestoreCartItemsInput! ) {
                restoreCartItems( input: $input ) {
                    clientMutationId
                    cartItems {
                        key
                    }
                }
            }
		';

		$variables = compact( 'input' );

        return $this->graphql( compact( 'query', 'variables' ) );
    }

    // tests
    public function testAddToCartMutationWithProduct() {
        $product_id = $this->factory->product->createSimple();
        $response   = $this->addToCart(
            array(
                'clientMutationId' => 'someId',
                'productId'        => $product_id,
                'quantity'         => 2,
            )
        );

        // Confirm valid response
		$this->assertIsValidQueryResponse( $response );

		// Get/validate cart item key.
		$cart_item_key = $this->lodashGet( $response, 'data.addToCart.cartItem.key' );
		$this->assertNotEmpty( $cart_item_key );

		// Get cart item data.
		$cart      = \WC()->cart;
        $cart_item = $cart->get_cart_item( $cart_item_key );
        $this->assertNotEmpty( $cart_item );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addToCart.clientMutationId', 'someId' ),
				$this->expectedObject( 'addToCart.cartItem.key', $cart_item_key ),
				$this->expectedObject( 'addToCart.cartItem.product.id', $this->toRelayId( 'product', $product_id ) ),
				$this->expectedObject( 'addToCart.cartItem.variation', 'NULL' ),
				$this->expectedObject( 'addToCart.cartItem.quantity', 2 ),
				$this->expectedObject( 'addToCart.cartItem.subtotal', wc_graphql_price( $cart_item['line_subtotal'] ) ),
				$this->expectedObject( 'addToCart.cartItem.subtotalTax', wc_graphql_price( $cart_item['line_subtotal_tax'] ) ),
				$this->expectedObject( 'addToCart.cartItem.total', wc_graphql_price( $cart_item['line_total'] ) ),
				$this->expectedObject( 'addToCart.cartItem.tax', wc_graphql_price( $cart_item['line_tax'] ) ),
			)
		);
    }

    public function testAddToCartMutationWithProductVariation() {
        $ids      = $this->factory->product_variation->createSome();
        $response = $this->addToCart(
            array(
                'clientMutationId' => 'someId',
                'productId'        => $ids['product'],
                'quantity'         => 3,
				'variationId'      => $ids['variations'][0],
				'variation'        => array(
					array(
						'attributeName'  => 'color',
						'attributeValue' => 'red',
					),
				),
            )
        );

        // Confirm valid response
		$this->assertIsValidQueryResponse( $response );

		// Get/validate cart item key.
		$cart_item_key = $this->lodashGet( $response, 'data.addToCart.cartItem.key' );
		$this->assertNotEmpty( $cart_item_key );

		// Get cart item data.
		$cart      = \WC()->cart;
        $cart_item = $cart->get_cart_item( $cart_item_key );
        $this->assertNotEmpty( $cart_item );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addToCart.clientMutationId', 'someId' ),
				$this->expectedObject( 'addToCart.cartItem.key', $cart_item_key ),
				$this->expectedObject( 'addToCart.cartItem.product.id', $this->toRelayId( 'product', $ids['product'] ) ),
				$this->expectedObject( 'addToCart.cartItem.variation.id', $this->toRelayId( 'product_variation', $ids['variations'][0] ) ),
				$this->expectedObject( 'addToCart.cartItem.quantity', 3 ),
				$this->expectedObject( 'addToCart.cartItem.subtotal', wc_graphql_price( $cart_item['line_subtotal'] ) ),
				$this->expectedObject( 'addToCart.cartItem.subtotalTax', wc_graphql_price( $cart_item['line_subtotal_tax'] ) ),
				$this->expectedObject( 'addToCart.cartItem.total', wc_graphql_price( $cart_item['line_total'] ) ),
				$this->expectedObject( 'addToCart.cartItem.tax', wc_graphql_price( $cart_item['line_tax'] ) ),
			)
		);
    }

    public function testUpdateCartItemQuantitiesMutation() {
        // Create/add some products to the cart.
        $cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			),
		);

		// Store cart item keys for use in mutation.
		$keys = $this->factory->cart->add( ...$cart_item_data );

        // Define mutation.
        $query = '
            mutation( $input: UpdateItemQuantitiesInput! ) {
                updateItemQuantities( input: $input ) {
                    clientMutationId
                    updated {
                        key
                        quantity
                    }
                    removed {
                        key
                        quantity
                    }
                    items {
                        key
                        quantity
                    }
                }
            }
		';

		// Define variables
		$variables = array(
			'input' => array(
				'clientMutationId' => 'someId',
				'items'            => array(
					array( 'key' => $keys[0], 'quantity' => 4 ),
					array( 'key' => $keys[1], 'quantity' => 2 ),
					array( 'key' => $keys[2], 'quantity' => 0 ),
				),
			),
		);

		// Execute mutation.
        $response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'updateItemQuantities.clientMutationId', 'someId' ),
				$this->expectedNode( 'updateItemQuantities.updated', array( 'key' => $keys[0], 'quantity' => 4 ) ),
				$this->expectedNode( 'updateItemQuantities.updated', array( 'key' => $keys[1], 'quantity' => 2 ) ),
				$this->expectedNode( 'updateItemQuantities.removed', array( 'key' => $keys[2], 'quantity' => 1 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[0], 'quantity' => 4 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[1], 'quantity' => 2 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[2], 'quantity' => 1 ) ),
			)
		);
    }

    public function testRemoveItemsFromCartMutation() {
		// Create/add some products to the cart.
        $cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			)
		);

		// Store cart item keys for use in mutation.
		$keys = $this->factory->cart->add( ...$cart_item_data );

		// Define expected response data.
		$expected = array( $this->expectedObject( 'removeItemsFromCart.clientMutationId', 'someId' ) );
		foreach( $keys as $key ) {
			$expected[] = $this->expectedNode( 'removeItemsFromCart.cartItems', compact( 'key' ) );
		}

		// Execute mutation w/ "keys" array.
        $response = $this->removeItemsFromCart(
            array(
                'clientMutationId' => 'someId',
                'keys'             => $keys,
            )
		);

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm none of the items in cart.
		foreach( $keys as $key ) {
			$this->assertEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} still in cart after \"removeItemsFromCart\" mutation."
			);
		}

		// Add more items and execute mutation with "all" flag.
		$keys = $this->factory->cart->add( ...$cart_item_data );
		$response = $this->removeItemsFromCart(
            array(
                'clientMutationId' => 'someId',
                'all'              => true
            )
		);

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm none of the items in cart.
		foreach( $keys as $key ) {
			$this->assertEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} still in cart after \"removeItemsFromCart\" mutation with \"all\" flag."
			);
		}
    }

    public function testRestoreCartItemsMutation() {
        // Create/add some products to the cart.
		$cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			)
		);
		$keys = $this->factory->cart->add( ...$cart_item_data );
		$this->factory->cart->remove( ...$keys );

        $response = $this->restoreItems(
            array(
                'clientMutationId' => 'someId',
                'keys'             => $keys,
            )
		);

		$expected = array( $this->expectedObject( 'restoreCartItems.clientMutationId', 'someId' ) );
		foreach( $keys as $key ) {
			$expected[] = $this->expectedNode( 'restoreCartItems.cartItems', compact( 'key' ) );
		}

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm items in cart.
		foreach( $keys as $key ) {
			$this->assertNotEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} not found in cart after \"restoreCartItems\" mutation."
			);
		}
    }

    public function testEmptyCartMutation() {
        // Create/add some products to the cart.
		$cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			)
		);
		$keys = $this->factory->cart->add( ...$cart_item_data );

        $query = '
            mutation emptyCart( $input: EmptyCartInput! ) {
                emptyCart( input: $input ) {
                    clientMutationId
                    deletedCart {
                        contents {
                            nodes {
                                key
                                product {
                                    ... on SimpleProduct {
                                        id
                                    }
                                    ... on VariableProduct {
                                        id
                                    }
                                }
                                variation {
                                    id
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array( 'clientMutationId' => 'someId' ),
        );
        $actual    = $this->graphql( $mutation, 'emptyCart', $variables );

        $expected = array(
            'data' => array(
                'emptyCart' => array(
                    'clientMutationId' => 'someId',
                    'deletedCart'         => array(
                        'contents' => array(
                            'nodes' => array(
                                array(
                                    'key'          => $cart_item['key'],
                                    'product'      => array(
                                        'id'       => $this->product->to_relay_id( $cart_item['product_id'] ),
                                    ),
                                    'variation'    => array(
                                        'id'       => $this->variation->to_relay_id( $cart_item['variation_id'] ),
                                    ),
                                    'quantity'     => $cart_item['quantity'],
                                    'subtotal'     => wc_graphql_price( $cart_item['line_subtotal'] ),
                                    'subtotalTax'  => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
                                    'total'        => wc_graphql_price( $cart_item['line_total'] ),
                                    'tax'          => wc_graphql_price( $cart_item['line_tax'] ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals( $expected, $actual );
        $this->assertTrue( \WC()->cart->is_empty() );
    }

    public function testApplyCouponMutation() {
        $cart = WC()->cart;

        // Create products.
        $product_id = $this->product->create_simple(
            array( 'regular_price' => 100 )
        );

        // Create coupon.
        $coupon_code = wc_get_coupon_code_by_id(
            $this->coupon->create(
                array(
                    'amount'      => 0.5,
                    'product_ids' => array( $product_id )
                )
            )
        );

        // Add items to carts.
        $cart_item_key = $cart->add_to_cart( $product_id, 1 );

        $old_total = \WC()->cart->get_cart_contents_total();

        $mutation = '
            mutation applyCoupon( $input: ApplyCouponInput! ) {
                applyCoupon( input: $input ) {
                    clientMutationId
                    cart {
                        appliedCoupons {
                            nodes {
                                code
                            }
                        }
                        contents {
                            nodes {
                                key
                                product {
                                    ... on SimpleProduct {
                                        id
                                    }
                                    ... on VariableProduct {
                                        id
                                    }
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $coupon_code,
            ),
        );
        $actual    = $this->graphql( $mutation, 'applyCoupon', $variables );

        // Get updated cart item.
        $cart_item = WC()->cart->get_cart_item( $cart_item_key );

        $expected = array(
            'data' => array(
                'applyCoupon' => array(
                    'clientMutationId' => 'someId',
                    'cart'         => array(
                        'appliedCoupons' => array(
                            'nodes' => array(
                                array(
                                    'code' => $coupon_code,
                                ),
                            ),
                        ),
                        'contents' => array(
                            'nodes' => array(
                                array(
                                    'key'          => $cart_item['key'],
                                    'product'      => array(
                                        'id' => $this->product->to_relay_id( $cart_item['product_id'] ),
                                    ),
                                    'quantity'     => $cart_item['quantity'],
                                    'subtotal'     => wc_graphql_price( $cart_item['line_subtotal'] ),
                                    'subtotalTax'  => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
                                    'total'        => wc_graphql_price( $cart_item['line_total'] ),
                                    'tax'          => wc_graphql_price( $cart_item['line_tax'] ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals( $expected, $actual );


        $new_total = \WC()->cart->get_cart_contents_total();

        // Use --debug to view.
        codecept_debug( array( 'old' => $old_total, 'new' => $new_total ) );

        $this->assertTrue( $old_total > $new_total );
    }

    public function testApplyCouponMutationWithInvalidCoupons() {
        $cart = WC()->cart;

        // Create products.
        $product_id = $this->product->create_simple();

        // Create invalid coupon codes.
        $coupon_id           = $this->coupon->create(
            array( 'product_ids' => array( $product_id ) )
        );
        $expired_coupon_code = wc_get_coupon_code_by_id(
            $this->coupon->create(
                array(
                    'product_ids'  => array( $product_id ),
                    'date_expires' => time() - 20,
                )
            )
        );
        $applied_coupon_code = wc_get_coupon_code_by_id(
            $this->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

        // Add items to carts.
        $cart_item_key = $cart->add_to_cart( $product_id, 1 );
        $cart->apply_coupon( $applied_coupon_code );

        $old_total = \WC()->cart->get_cart_contents_total();

        $mutation = '
            mutation ( $input: ApplyCouponInput! ) {
                applyCoupon( input: $input ) {
                    clientMutationId
                }
            }
        ';

        /**
         * Assertion One
         *
         * Can't pass coupon ID as coupon "code". Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $coupon_id,
            ),
        );
        $actual    = $this->graphql( $mutation, null, $variables );

        $this->assertNotEmpty( $actual['errors'] );
        $this->assertEmpty( $actual['data']['applyCoupon'] );

        /**
         * Assertion Two
         *
         * Can't pass expired coupon code. Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $expired_coupon_code,
            ),
        );
        $actual    = $this->graphql( $mutation, null, $variables );

        $this->assertNotEmpty( $actual['errors'] );
        $this->assertEmpty( $actual['data']['applyCoupon'] );

        /**
         * Assertion Three
         *
         * Can't pass coupon already applied to the cart. Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $applied_coupon_code,
            ),
        );
        $actual    = $this->graphql( $mutation, null, $variables );

        $this->assertNotEmpty( $actual['errors'] );
        $this->assertEmpty( $actual['data']['applyCoupon'] );

        $this->assertEquals( $old_total, \WC()->cart->get_cart_contents_total() );
    }

    public function testRemoveCouponMutation() {
        $cart = WC()->cart;

        // Create product and coupon.
        $product_id  = $this->product->create_simple();
        $coupon_code = wc_get_coupon_code_by_id(
            $this->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

        // Add item and coupon to cart and get total..
        $cart_item_key = $cart->add_to_cart( $product_id, 3 );
        $cart->apply_coupon( $coupon_code );

        $mutation = '
            mutation removeCoupons( $input: RemoveCouponsInput! ) {
                removeCoupons( input: $input ) {
                    clientMutationId
                    cart {
                        appliedCoupons {
                            nodes {
                                code
                            }
                        }
                        contents {
                            nodes {
                                key
                                product {
                                    ... on SimpleProduct {
                                        id
                                    }
                                    ... on VariableProduct {
                                        id
                                    }
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'codes'            => array( $coupon_code ),
            ),
        );
        $actual    = $this->graphql( $mutation, 'removeCoupons', $variables );

        // Get updated cart item.
        $cart_item = \WC()->cart->get_cart_item( $cart_item_key );

        $expected = array(
            'data' => array(
                'removeCoupons' => array(
                    'clientMutationId' => 'someId',
                    'cart'         => array(
                        'appliedCoupons' => array(
                            'nodes' => array(),
                        ),
                        'contents' => array(
                            'nodes' => array(
                                array(
                                    'key'          => $cart_item['key'],
                                    'product'      => array(
                                        'id' => $this->product->to_relay_id( $cart_item['product_id'] ),
                                    ),
                                    'quantity'     => $cart_item['quantity'],
                                    'subtotal'     => wc_graphql_price( $cart_item['line_subtotal'] ),
                                    'subtotalTax'  => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
                                    'total'        => wc_graphql_price( $cart_item['line_total'] ),
                                    'tax'          => wc_graphql_price( $cart_item['line_tax'] ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $this->assertEquals( $expected, $actual );
    }

    public function testAddFeeMutation() {
        $cart = WC()->cart;

        // Create product and coupon.
        $product_id  = $this->product->create_simple();
        $coupon_code = wc_get_coupon_code_by_id(
            $this->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

        // Add item and coupon to cart.
        $cart->add_to_cart( $product_id, 3 );
        $cart->apply_coupon( $coupon_code );

        $mutation = '
            mutation addFee( $input: AddFeeInput! ) {
                addFee( input: $input ) {
                    clientMutationId
                    cartFee {
                        id
                        name
                        taxClass
                        taxable
                        amount
                        total
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'name'             => 'extra_fee',
                'amount'           => 49.99,
            ),
        );
        $actual    = $this->graphql( $mutation, 'addFee', $variables );

        $this->assertArrayHasKey('errors', $actual );

        wp_set_current_user( $this->shop_manager );
        $actual = $this->graphql( $mutation, 'addFee', $variables );

        $expected = array(
            'data' => array(
                'addFee' => array(
                    'clientMutationId' => 'someId',
                    'cartFee'          => $this->cart->print_fee_query( 'extra_fee' ),
                ),
            ),
        );

        $this->assertEquals( $expected, $actual );
	}

	public function testAddToCartMutationErrors() {
		// Create products.
        $product_id    = $this->product->create_simple(
			array(
				'manage_stock'   => true,
				'stock_quantity' => 1,
			)
		);
		$variation_ids = $this->variation->create( $this->product->create_variable() );

		$product   = \wc_get_product( $variation_ids['product'] );
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'test' );
		$attribute->set_options( array( 'yes', 'no' ) );
		$attribute->set_position( 3 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes = array_values( $product->get_attributes() );
		$attributes[] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		\WC()->session->set( 'wc_notices', null );
		$missing_attributes = $this->addToCart(
            array(
                'clientMutationId' => 'someId',
                'productId'        => $variation_ids['product'],
				'quantity'         => 5,
                'variationId'      => $variation_ids['variations'][0],
            )
		);

		$this->assertArrayHasKey( 'errors', $missing_attributes );

		\WC()->session->set( 'wc_notices', null );
		$not_enough_stock = $this->addToCart(
            array(
                'clientMutationId' => 'someId',
                'productId'        => $product_id,
                'quantity'         => 5,
            )
		);

		$this->assertArrayHasKey( 'errors', $not_enough_stock );
	}
}
