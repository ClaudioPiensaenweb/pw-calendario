<?php
/**
 * Plugin Name:       Pw Calendario
 * Plugin URI:        https://piensaenweb.com
 * Description:       Gestión de citas y reservas de visitas para WordPress. Calendario público, aprobación de citas, recordatorios por correo y calendarios múltiples.
 * Version:           3.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Piensaenweb
 * Author URI:        https://piensaenweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pw-calendario
 * Domain Path:       /languages
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Este archivo es solo el arranque: cabecera, constantes, carga de
 * dependencias y puesta en marcha. La lógica vive en `includes/`.
 */

/*
 * Comprobación de conflicto con el calendario anterior.
 *
 * Este plugin desciende de otro y comparte con él 79 funciones globales
 * (`booked_*`), ninguna protegida con `function_exists()`. Si los dos
 * están activos a la vez, PHP aborta con «Cannot redeclare …» y el sitio
 * entero deja de responder con un error crítico.
 *
 * WordPress carga los plugins por orden alfabético, así que `booked` ya se
 * ha cargado cuando llega el turno de `pw-calendario`: basta con buscar una
 * de sus funciones. Si está presente, este plugin no carga nada y avisa,
 * en lugar de tumbar el sitio.
 *
 * En cuanto se desactive el plugin anterior, esta comprobación deja de
 * saltar y Pw Calendario arranca con normalidad. No hay que hacer nada más.
 */
if ( function_exists( 'booked_appt_is_available' ) || class_exists( 'booked_plugin', false ) ) {

	/**
	 * Avisa del conflicto en el escritorio.
	 *
	 * @return void
	 */
	function pwcal_aviso_conflicto() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Pw Calendario</strong> ';
		echo esc_html__( 'no se ha cargado porque el calendario de citas anterior sigue activo. Los dos plugins comparten los mismos nombres de función y no pueden convivir.', 'pw-calendario' );
		echo '</p><p>';
		echo esc_html__( 'Desactiva el plugin anterior en la pantalla de Plugins. Pw Calendario arrancará solo, sin perder ninguna cita ni la configuración.', 'pw-calendario' );
		echo '</p></div>';
	}

	add_action( 'admin_notices', 'pwcal_aviso_conflicto' );

	// Nada más de este archivo se ejecuta.
	return;
}

define( 'PWCAL_VERSION', '3.2.1' );
define( 'PWCAL_PLUGIN_FILE', __FILE__ );
define( 'PWCAL_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'PWCAL_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PWCAL_PLANTILLAS_DIR', PWCAL_PLUGIN_DIR . '/templates/' );
define( 'PWCAL_AJAX_INCLUDES_DIR', PWCAL_PLUGIN_DIR . '/includes/ajax/' );

// Núcleo de seguridad: nonces, permisos y saneado. Debe cargarse primero.
require_once PWCAL_PLUGIN_DIR . '/includes/seguridad.php';

// Control de los envios de correo. Antes que nada que pueda enviar.
require_once PWCAL_PLUGIN_DIR . '/includes/envios.php';

// Aforo por personas: tamano de grupo de cada cita.
require_once PWCAL_PLUGIN_DIR . '/includes/plazas.php';

// Importacion de citas desde otra instalacion.
require_once PWCAL_PLUGIN_DIR . '/includes/importador.php';
require_once PWCAL_PLUGIN_DIR . '/includes/rest-importacion.php';

// Complementos incluidos.
require_once PWCAL_PLUGIN_DIR . '/includes/add-ons/init.php';

// Funciones de envío de correo.
require_once PWCAL_PLUGIN_DIR . '/includes/mailer_functions.php';

// Botón «Añadir al calendario», sin dependencias externas.
require_once PWCAL_PLUGIN_DIR . '/includes/anadir-al-calendario.php';

// Registro de ganchos y clase principal.
require_once PWCAL_PLUGIN_DIR . '/includes/class-pw-calendario-loader.php';
require_once PWCAL_PLUGIN_DIR . '/includes/class-pw-calendario.php';

/*
 * Actualizaciones desde GitHub.
 *
 * Solo se carga en el escritorio y en el cron: es donde WordPress
 * comprueba e instala actualizaciones, asi que no tiene por que pesar en
 * las peticiones del front-end.
 */
if ( is_admin() || wp_doing_cron() ) {
	require_once PWCAL_PLUGIN_DIR . '/includes/class-pw-calendario-actualizador.php';
	new Pw_Calendario_Actualizador();
}

/*
 * Gancho de activación: crea el perfil «Gestor de citas» y sus
 * capacidades una sola vez, no en cada carga de página.
 */
register_activation_hook( __FILE__, array( 'Pw_Calendario', 'activate' ) );

/**
 * Arranca el plugin.
 *
 * @return Pw_Calendario
 */
function pwcal_arrancar() {

	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Pw_Calendario();
	}

	return $plugin;
}

pwcal_arrancar();

/**
 * Carga las traducciones del plugin.
 *
 * @return void
 */
function pwcal_cargar_traducciones() {

	load_plugin_textdomain(
		'pw-calendario',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
}
add_action( 'init', 'pwcal_cargar_traducciones' );
