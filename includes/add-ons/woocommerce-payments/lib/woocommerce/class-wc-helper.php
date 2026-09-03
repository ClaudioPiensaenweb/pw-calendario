<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Booked_WC_Helper {

	public static function exists() {
		return class_exists('WooCommerce');
	}

	/**
	 * Devuelve el carrito de WooCommerce, o null si no esta disponible.
	 *
	 * WooCommerce solo inicializa el carrito en el front-end y en sus
	 * peticiones AJAX. En el escritorio, en el cron y en la API REST,
	 * `WC()->cart` es null.
	 *
	 * En PHP 7 pasar ese null a `method_exists()` era un aviso y la funcion
	 * devolvia false, asi que el codigo seguia adelante sin mas. Desde PHP
	 * 8 es un TypeError sin capturar, es decir, un error fatal que tumba el
	 * sitio entero. Este ayudante centraliza la comprobacion para que no
	 * vuelva a pasar.
	 *
	 * @return WC_Cart|null
	 */
	public static function get_cart() {

		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();

		if ( ! is_object( $wc ) || ! isset( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return null;
		}

		return $wc->cart;
	}

	/**
	 * Devuelve la sesion de WooCommerce, o null si no esta disponible.
	 *
	 * Igual que con el carrito: WooCommerce solo arranca la sesion en el
	 * front-end y en sus peticiones AJAX. Fuera de ahi, `WC()->session` es
	 * null, y llamar a `->get()` o `->set()` sobre null es un error fatal
	 * en PHP 8.
	 *
	 * @return WC_Session|null
	 */
	public static function get_session() {

		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();

		if ( ! is_object( $wc ) || ! isset( $wc->session ) || ! is_object( $wc->session ) ) {
			return null;
		}

		return $wc->session;
	}

	public static function is_woocommerce() {
		return self::exists() && is_woocommerce();
	}

	// Get The Page ID You Need

	public static function get_shop_page() {
		return get_option('woocommerce_shop_page_id');
	}

	public static function get_cart_page() {
		return get_option('woocommerce_cart_page_id');
	}

	public static function get_checkout_page() {
		return get_option('woocommerce_checkout_page_id');
	}

	public static function get_pay_page() {
		return get_option('woocommerce_pay_page_id');
	}

	public static function get_thanks_page() {
		return get_option('woocommerce_thanks_page_id');
	}

	public static function get_myaccount_page() {
		return get_option('woocommerce_myaccount_page_id');
	}

	public static function get_edit_address_page() {
		return get_option('woocommerce_edit_address_page_id');
	}

	public static function get_view_order_page() {
		return get_option('woocommerce_view_order_page_id');
	}

	public static function get_terms_page() {
		return get_option('woocommerce_terms_page_id');
	}

	// is if is on a cirtain WooCommerce page

	public static function is_product() {
		return self::exists() && is_product();
	}

	public static function is_shop() {
		return self::exists() && is_shop();
	}

	public static function is_checkout() {
		return self::exists() && is_checkout();
	}

	public static function is_account_page() {
		return self::exists() && is_account_page();
	}

	public static function is_cart() {
		return self::exists() && is_cart();
	}

	public static function is_product_category() {
		return self::exists() && is_product_category();
	}

	public static function is_product_tag() {
		return self::exists() && is_product_category();
	}

	public static function is_order_received_page() {
		return self::exists() && is_order_received_page();
	}
}