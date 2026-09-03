<?php
/**
 * Puntos de entrada AJAX del complemento de pagos con WooCommerce.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atiende las peticiones AJAX del complemento de pagos.
 */
class Booked_WC_Ajax {

	/**
	 * Registra los puntos de entrada.
	 */
	private function __construct() {

		$funciones = array(
			'wp_ajax_'        => array(
				'load_variations' => 'load_product_variations',
				'add_to_cart'     => 'add_appointment_to_cart',
				'mark_paid'       => 'mark_appointment_as_paid',
			),
			'wp_ajax_nopriv_' => array(
				'load_variations' => 'load_product_variations',
				'add_to_cart'     => 'add_appointment_to_cart',
			),
		);

		foreach ( $funciones as $tipo => $peticiones ) {
			foreach ( $peticiones as $accion => $metodo ) {
				add_action( $tipo . BOOKED_WC_PLUGIN_PREFIX . $accion, array( $this, $metodo ) );
			}
		}
	}

	/**
	 * Crea la instancia.
	 *
	 * @return Booked_WC_Ajax
	 */
	public static function setup() {

		return new self();
	}

	/**
	 * Marca una cita como pagada.
	 *
	 * @return void
	 */
	public function mark_appointment_as_paid() {

		// Se exige nonce además del permiso: sin él, bastaba con que un
		// administrador visitara una página preparada para completar
		// pedidos de WooCommerce sin darse cuenta.
		pwcal_verificar_ajax_admin( 'manage_booked_options' );

		$id_cita = pwcal_validar_id_cita( pwcal_post_entero( 'appt_id' ) );

		if ( ! $id_cita ) {
			wp_send_json_error(
				array( 'message' => __( 'La cita indicada no existe.', 'pw-calendario' ) ),
				404
			);
		}

		$cita = Booked_WC_Appointment::get( $id_cita );

		if ( ! $cita->order_id ) {
			$id_pedido = false;
			update_post_meta( $id_cita, '_booked_wc_appointment_order_id', 'manual' );
		} else {
			$id_pedido = $cita->order_id;
			$pedido    = new WC_Order( $cita->order_id );
			$pedido->update_status( 'completed' );
		}

		echo $id_pedido ? esc_url( get_edit_post_link( $id_pedido ) ) : 'no_order';

		wp_die();
	}

	/**
	 * Devuelve las variaciones de un producto.
	 *
	 * @return void
	 */
	public function load_product_variations() {

		pwcal_verificar_nonce_ajax( PWCAL_NONCE_FRONT );

		$product_id = pwcal_post_entero( 'product_id' );

		if ( ! $product_id ) {
			wp_die();
		}

		$publicacion = get_post( $product_id );

		// Se comprueba que sea realmente un producto: antes valía el ID de
		// cualquier entrada del sitio.
		if ( ! $publicacion || 'product' !== $publicacion->post_type ) {
			wp_die();
		}

		$calendar_id = pwcal_post_entero( 'calendar_id' );
		$field_name  = pwcal_post_texto( 'field_name' );
		$is_required = false;

		if ( $field_name ) {

			$partes = explode( '---', $field_name );

			// Sin estas comprobaciones un `field_name` con otro formato
			// provoca un aviso de índice indefinido en PHP 8.
			if ( isset( $partes[1] ) ) {

				$field_type    = $partes[0];
				$final         = explode( '___', $partes[1] );
				$solo_numeros  = $final[0];
				$is_required   = isset( $final[1] );

				$field_name = 'paid-service-variation---' . $solo_numeros;

				if ( $is_required ) {
					$field_name .= '___' . $final[1];
				}
			}
		}

		try {
			$product        = Booked_WC_Product::get( $product_id );
			$fragment_file  = Booked_WC_Fragments::get_path( 'ajax-loaded/product', 'variations' );
			require $fragment_file;
		} catch ( Exception $e ) {
			wp_send_json_error(
				array( 'message' => __( 'Se ha producido un error al cargar las variaciones.', 'pw-calendario' ) ),
				500
			);
		}

		wp_die();
	}

	/**
	 * Añade una cita al carrito.
	 *
	 * @return void
	 */
	public function add_appointment_to_cart() {

		pwcal_verificar_nonce_ajax( PWCAL_NONCE_FRONT );

		$respuesta = new Booked_WC_Response();

		$id_cita = pwcal_validar_id_cita( pwcal_post_entero( 'app_id' ) );

		if ( ! $id_cita ) {
			$respuesta->add_message( __( 'No se ha indicado una cita válida.', 'pw-calendario' ) );
			$respuesta->create();
			return;
		}

		try {

			$cita = Booked_WC_Appointment::get( $id_cita );

			if ( ! $cita->products ) {
				// Antes se llamaba aquí a `$e->getMessage()` con `$e` sin
				// definir, lo que en PHP 8 es un error fatal.
				$respuesta->add_message( __( 'Esta cita no tiene ningún servicio asociado.', 'pw-calendario' ) );
				$respuesta->create();
				return;
			}

			Booked_WC_Cart::add_appointment( $id_cita );

		} catch ( Exception $e ) {
			$respuesta->add_message( $e->getMessage() );
			$respuesta->create();
			return;
		}

		$respuesta->add_message( __( 'La cita se ha añadido al carrito.', 'pw-calendario' ) );
		$respuesta->set_status( true );
		$respuesta->create();
	}
}
