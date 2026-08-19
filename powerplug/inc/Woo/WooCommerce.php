<?php
declare( strict_types=1 );

namespace PowerPlug\Woo;

use PowerPlug\Core\Bootable;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce presentation layer: badges, trust signals, sticky add-to-cart,
 * brand attribute, and Merchant-Center-friendly product data.
 */
final class WooCommerce implements Bootable {

	public function boot(): void {
		add_action( 'after_setup_theme', [ $this, 'columns' ] );
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'stock_badge' ], 9 );
		// Structured product-assurance facts above the buy button (Brand, SKU/MPN, Condition, Warranty, Delivery, Returns).
		add_action( 'woocommerce_single_product_summary', [ $this, 'product_assurance' ], 25 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'trust_badges' ], 35 );
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'single_whatsapp' ] );
		add_action( 'wp_footer', [ $this, 'sticky_atc' ] );
		add_filter( 'woocommerce_product_tabs', [ $this, 'specifications_tab' ] );
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragments' ] );

		// Remove default WooCommerce sidebar (Pages/Archives/Categories widgets) on shop, category and product pages.
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
			remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
			remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}

	public function columns(): void {
		add_filter( 'loop_shop_columns', static fn() => 4 );
		add_filter( 'loop_shop_per_page', static fn() => 24 );
	}

	/**
	 * Keep the header cart count and mini-cart drawer live on AJAX add-to-cart.
	 *
	 * @param array<string,string> $fragments
	 * @return array<string,string>
	 */
	public function cart_fragments( array $fragments ): array {
		$count = ( WC()->cart instanceof \WC_Cart ) ? WC()->cart->get_cart_contents_count() : 0;
		$fragments['span.pp-cart__count'] = '<span class="pp-cart__count">' . esc_html( (string) $count ) . '</span>';

		ob_start();
		woocommerce_mini_cart();
		$mini = (string) ob_get_clean();
		$fragments['div.pp-minicart__body'] = '<div class="pp-minicart__body">' . $mini . '</div>';

		return $fragments;
	}

	public function stock_badge(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		if ( $product->is_on_sale() ) {
			echo '<span class="pp-badge pp-badge--sale">' . esc_html__( 'Sale', 'powerplug' ) . '</span>';
		}
		if ( ! $product->is_in_stock() ) {
			echo '<span class="pp-badge pp-badge--oos">' . esc_html__( 'Out of stock', 'powerplug' ) . '</span>';
		}
	}

	/**
	 * At-a-glance product facts shown under the short description, above the buy button.
	 * Only truthful, per-product data is rendered; optional rows are omitted when empty.
	 */
	public function product_assurance(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$shipping = home_url( '/shipping-delivery-policy/' );
		$returns  = home_url( '/return-refund-policy/' );

		// Condition attribute, defaulting to New.
		$condition = (string) $product->get_attribute( 'condition' );
		if ( '' === $condition ) {
			$condition = __( 'New', 'powerplug' );
		}

		// Brand from a brand taxonomy, if one is registered and set.
		$brand = '';
		foreach ( [ 'product_brand', 'pwb-brand', 'pa_brand' ] as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $product->get_id(), $tax, [ 'fields' => 'names' ] );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$brand = implode( ', ', $terms );
					break;
				}
			}
		}

		$model = (string) $product->get_attribute( 'model' );
		$sku   = (string) $product->get_sku();

		$rows = [];
		if ( '' !== $brand ) {
			$rows[] = [ __( 'Brand', 'powerplug' ), esc_html( $brand ) ];
		}
		if ( '' !== $model ) {
			$rows[] = [ __( 'Model', 'powerplug' ), esc_html( $model ) ];
		}
		if ( '' !== $sku ) {
			$rows[] = [ __( 'SKU / MPN', 'powerplug' ), esc_html( $sku ) ];
		}
		$rows[] = [ __( 'Condition', 'powerplug' ), esc_html( $condition ) ];
		$rows[] = [ __( 'Warranty', 'powerplug' ), esc_html__( 'Manufacturer warranty where applicable', 'powerplug' ) ];
		$rows[] = [
			__( 'Delivery', 'powerplug' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $shipping ),
				esc_html__( 'KSh 300 within Nairobi · KSh 500 rest of Kenya', 'powerplug' )
			),
		];
		$rows[] = [
			__( 'Returns', 'powerplug' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $returns ),
				esc_html__( 'Easy returns under our Return & Refund Policy', 'powerplug' )
			),
		];

		echo '<ul class="pp-assurance" aria-label="' . esc_attr__( 'Product details', 'powerplug' ) . '">';
		foreach ( $rows as $r ) {
			printf(
				'<li><span class="pp-assurance__k">%s</span><span class="pp-assurance__v">%s</span></li>',
				esc_html( $r[0] ),
				wp_kses_post( $r[1] )
			);
		}
		echo '</ul>';
	}

	public function trust_badges(): void {
		$items = [
			[ __( 'Warranty where applicable', 'powerplug' ), __( 'On eligible brands & items', 'powerplug' ) ],
			[ __( 'Fast delivery', 'powerplug' ), __( 'Same-day Nairobi dispatch', 'powerplug' ) ],
			[ __( 'Secure payment', 'powerplug' ), __( 'M-Pesa or pay on delivery', 'powerplug' ) ],
			[ __( 'Real support', 'powerplug' ), __( 'Call, WhatsApp or email', 'powerplug' ) ],
		];
		echo '<ul class="pp-trust" aria-label="' . esc_attr__( 'Why buy from us', 'powerplug' ) . '">';
		foreach ( $items as $it ) {
			printf(
				'<li class="pp-trust__item"><span class="pp-trust__t">%s</span><span class="pp-trust__s">%s</span></li>',
				esc_html( $it[0] ),
				esc_html( $it[1] )
			);
		}
		echo '</ul>';
	}

	public function single_whatsapp(): void {
		global $product;
		if ( ( $product instanceof \WC_Product ) === false ) {
			return;
		}
		$number = preg_replace( '/[^0-9]/', '', (string) \PowerPlug\Customizer\Customizer::val( 'pp_whatsapp' ) );
		if ( '' === (string) $number ) {
			return;
		}
		$template = (string) get_theme_mod( 'pp_wa_order_msg', 'Hi Power Tools Plug, I would like to order: {product} ({url})' );
		$message  = str_replace( array( '{product}', '{url}' ), array( $product->get_name(), get_permalink( $product->get_id() ) ), $template );
		$href     = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );
		$path     = 'M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.97L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.82c2.16 0 4.19.84 5.72 2.37a8.06 8.06 0 0 1 2.37 5.72c0 4.46-3.63 8.09-8.1 8.09a8.1 8.1 0 0 1-4.12-1.13l-.3-.18-3.12.82.83-3.04-.19-.31a8.03 8.03 0 0 1-1.25-4.25c0-4.46 3.63-8.09 8.1-8.09Zm4.68 10.29c-.26-.13-1.51-.75-1.74-.83-.23-.09-.4-.13-.57.13-.17.26-.65.83-.8 1-.15.17-.29.19-.55.06-.26-.13-1.08-.4-2.06-1.27-.76-.68-1.28-1.52-1.43-1.78-.15-.26-.02-.4.11-.53.12-.12.26-.31.39-.46.13-.15.17-.26.26-.44.09-.17.04-.33-.02-.46-.06-.13-.57-1.38-.78-1.89-.21-.5-.42-.43-.57-.44l-.49-.01c-.17 0-.44.06-.68.33-.23.26-.89.87-.89 2.12 0 1.25.91 2.46 1.04 2.63.13.17 1.79 2.74 4.34 3.84.61.26 1.08.42 1.45.54.61.19 1.16.16 1.6.1.49-.07 1.51-.62 1.72-1.21.21-.6.21-1.11.15-1.21-.06-.11-.23-.17-.49-.3Z';
		$icon     = '<svg class="pp-wa-order__icon" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="' . $path . '"/></svg>';
		printf(
			'<a class="pp-wa-order pp-wa-order--single" href="%1$s" target="_blank" rel="nofollow noopener">%2$s<span>%3$s</span></a>',
			esc_url( $href ),
			$icon,
			esc_html__( 'Order on WhatsApp', 'powerplug' )
		);
	}

	public function sticky_atc(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		printf(
			'<div class="pp-sticky-atc" role="region" aria-label="%1$s"><span class="pp-sticky-atc__name">%2$s</span><span class="pp-sticky-atc__price">%3$s</span><a class="pp-sticky-atc__btn" href="#" data-add-to-cart="%4$d">%5$s</a></div>',
			esc_attr__( 'Add to cart', 'powerplug' ),
			esc_html( $product->get_name() ),
			wp_kses_post( $product->get_price_html() ),
			(int) $product->get_id(),
			esc_html__( 'Add to Cart', 'powerplug' )
		);
	}

	/**
	 * @param array<string,array<string,mixed>> $tabs
	 * @return array<string,array<string,mixed>>
	 */
	public function specifications_tab( array $tabs ): array {
		$tabs['pp_specs'] = [
			'title'    => __( 'Specifications', 'powerplug' ),
			'priority' => 15,
			'callback' => static function (): void {
				global $product;
				if ( ! $product instanceof \WC_Product ) {
					return;
				}
				$attrs = $product->get_attributes();
				if ( empty( $attrs ) ) {
					echo '<p>' . esc_html__( 'Specifications available on request.', 'powerplug' ) . '</p>';
					return;
				}
				echo '<table class="pp-specs shop_attributes">';
				foreach ( $attrs as $attr ) {
					$name  = wc_attribute_label( $attr->get_name() );
					$value = $product->get_attribute( $attr->get_name() );
					printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $name ), esc_html( $value ) );
				}
				echo '</table>';
			},
		];
		return $tabs;
	}
}
