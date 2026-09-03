<?php
/**
 * Complemento: feeds de calendario (iCalendar).
 *
 * Publica las citas en formato .ics para poder suscribirse desde Google
 * Calendar, Outlook o Apple Calendario.
 *
 * Nota de seguridad: en la versión original el hash de acceso al feed era
 * `md5( 'booked_ical_feed_' . get_site_url() )`, es decir, un valor
 * completamente predecible a partir de la URL del sitio. Cualquiera podía
 * calcularlo y descargar todas las citas con nombres y correos sin estar
 * identificado. Ahora se genera un secreto aleatorio, se guarda en la base
 * de datos y se compara en tiempo constante.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devuelve el secreto de acceso a los feeds, generándolo si no existe.
 *
 * @return string Secreto de 32 caracteres hexadecimales.
 */
function pwcal_secreto_feed() {

	$secreto = get_option( 'pwcal_feed_secreto' );

	if ( ! $secreto || ! is_string( $secreto ) || strlen( $secreto ) < 32 ) {
		// `wp_generate_password` con caracteres especiales desactivados
		// produce un valor apto para una URL.
		$secreto = wp_hash( wp_generate_password( 64, true, true ) . microtime() );
		update_option( 'pwcal_feed_secreto', $secreto, false );
	}

	return $secreto;
}

/**
 * Regenera el secreto de los feeds.
 *
 * Se usa para invalidar las URL antiguas si se han filtrado.
 *
 * @return string El secreto nuevo.
 */
function pwcal_regenerar_secreto_feed() {

	delete_option( 'pwcal_feed_secreto' );

	return pwcal_secreto_feed();
}

add_action( 'plugins_loaded', 'pwcal_iniciar_feeds_calendario' );

/**
 * Arranca el complemento de feeds.
 *
 * @return void
 */
function pwcal_iniciar_feeds_calendario() {

	if ( ! defined( 'BOOKEDICAL_SECURE_HASH' ) ) {
		define( 'BOOKEDICAL_SECURE_HASH', pwcal_secreto_feed() );
	}

	if ( ! defined( 'BOOKEDICAL_PLUGIN_DIR' ) ) {
		define( 'BOOKEDICAL_PLUGIN_DIR', __DIR__ );
	}

	new Pwcal_Feed_Calendario();
}

/**
 * Atiende las peticiones del feed.
 */
class Pwcal_Feed_Calendario {

	/**
	 * Registra los ganchos.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'atender_feed' ) );

		// La desactivación del complemento original solo tiene sentido en el
		// escritorio; antes se comprobaba en cada visita pública.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'desactivar_complemento_original' ) );
		}
	}

	/**
	 * Desactiva el plugin independiente equivalente, si está activo.
	 *
	 * @return void
	 */
	public function desactivar_complemento_original() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$original = 'booked-calendar-feeds/booked-calendar-feeds.php';

		if ( in_array( $original, (array) get_option( 'active_plugins', array() ), true ) ) {
			deactivate_plugins( plugin_basename( $original ) );
		}
	}

	/**
	 * Devuelve el feed si la petición lo solicita y el secreto es correcto.
	 *
	 * @return void
	 */
	public function atender_feed() {

		if ( ! isset( $_GET['booked_ical'] ) ) {
			return;
		}

		require BOOKEDICAL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'calendar-feed.php';
		exit;
	}
}
