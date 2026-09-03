<?php
/**
 * Plugin Name:       Pw Calendario
 * Plugin URI:        https://piensaenweb.com
 * Description:       Gestión de citas y reservas de visitas para WordPress. Calendario público, aprobación de citas, recordatorios por correo y calendarios múltiples.
 * Version:           3.0.0
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

define( 'PWCAL_VERSION', '3.0.0' );
define( 'PWCAL_PLUGIN_FILE', __FILE__ );
define( 'PWCAL_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'PWCAL_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PWCAL_PLANTILLAS_DIR', PWCAL_PLUGIN_DIR . '/templates/' );
define( 'PWCAL_AJAX_INCLUDES_DIR', PWCAL_PLUGIN_DIR . '/includes/ajax/' );

// Núcleo de seguridad: nonces, permisos y saneado. Debe cargarse primero.
require_once PWCAL_PLUGIN_DIR . '/includes/seguridad.php';

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
