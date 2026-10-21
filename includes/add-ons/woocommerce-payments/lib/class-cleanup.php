<?php

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Booked_WC_Cleanup {

	protected $appointments = array();

	private function __construct() {
		$this->appointments_to_clean();
		$this->clean();
	}

	public static function start() {
		return new self();
	}

	protected function clean() {
		foreach ($this->appointments as $app) {
			$app_meta = get_post_meta($app->ID);
			/*
			 * Solo citas sin publicar (las de pago que nadie ha pagado) y a la
			 * papelera, no borradas del todo. Las creadas a mano en el
			 * escritorio tambien guardan producto y fecha de creacion pero no
			 * tienen pedido, y antes se borraban sin remedio.
			 */
			if ( 'publish' === $app->post_status || 'future' === $app->post_status ) {
				continue;
			}
			$id_pedido = isset( $app_meta['_booked_wc_appointment_order_id'][0] ) ? (int) $app_meta['_booked_wc_appointment_order_id'][0] : 0;

			if ( ! $id_pedido ) {
				wp_trash_post( $app->ID );
				continue;
			}

			// Con pedido, solo si el pago fallo o no se llego a hacer. Si el
			// cliente lo reintenta y paga, la cita se vuelve a publicar.
			$pedido = function_exists( 'wc_get_order' ) ? wc_get_order( $id_pedido ) : false;
			if ( ! $pedido || in_array( $pedido->get_status(), array( 'failed', 'pending', 'cancelled' ), true ) ) {
				wp_trash_post( $app->ID );
			}
		}
	}

	protected function appointments_to_clean() {

		add_action('pre_get_posts', array($this, 'pre_get_posts'));

		$this->appointments = get_posts(array(
			'posts_per_page' => -1,
			'post_type' => BOOKED_WC_POST_TYPE,
			'post_status' => array('any'),
			'suppress_filters' => false,
			'_' . BOOKED_WC_PLUGIN_PREFIX . 'cleanup' => true
		));

		remove_action('pre_get_posts', array($this, 'pre_get_posts'));
		return $this;

	}

	public function pre_get_posts( $query ) {

		if ( !$query->get( '_' . BOOKED_WC_PLUGIN_PREFIX . 'cleanup') ) {
			return $query;
		}

		add_filter('posts_where', array($this, 'posts_where'));
		return $query;

	}

	public function posts_where( $where ) {
		remove_filter('posts_where', array($this, 'posts_where'));

		$time_created_meta_key = '_' . BOOKED_WC_PLUGIN_PREFIX . 'time_created';
		$custom_fields_meta_key = '_cf_meta_value';
		$post_type = BOOKED_WC_POST_TYPE;

		$mode = Booked_WC_Settings::get_option('cleanup_mode');
		$available_schedules = wp_get_schedules();
		$schedule_details = $available_schedules[$mode];
		$current_time = current_time('timestamp');
		$time_to_compare = $current_time - esc_sql($schedule_details['interval']);

		global $wpdb;
		$appointment_to_delete = "SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} time_created ON (p.ID = time_created.post_id AND time_created.meta_key = '{$time_created_meta_key}')
				INNER JOIN {$wpdb->postmeta} custom_fields ON (p.ID = custom_fields.post_id AND custom_fields.meta_key = '{$custom_fields_meta_key}')
				WHERE p.post_type = '{$post_type}'
				AND CAST(time_created.meta_value AS SIGNED) < '{$time_to_compare}'
				AND custom_fields.meta_value LIKE '%product_id::%'";

		$where .= "AND `$wpdb->posts`.`ID` IN ( {$appointment_to_delete} )";

		return $where;
	}
}