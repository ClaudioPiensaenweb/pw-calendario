<?php
/**
 * Limpieza al desinstalar el plugin.
 *
 * WordPress ejecuta este archivo automáticamente cuando se **borra** el
 * plugin desde la pantalla de Plugins (no al desactivarlo).
 *
 * Comportamiento por defecto: NO se borra nada de las citas ni de la
 * configuración. Solo se retiran los datos regenerables (transitorios,
 * eventos programados, capacidades y el secreto de los feeds).
 *
 * El borrado completo es voluntario y hay que activarlo antes de
 * desinstalar, poniendo a `1` la opción `pwcal_borrar_datos_al_desinstalar`:
 *
 *     update_option( 'pwcal_borrar_datos_al_desinstalar', 1 );
 *
 * Se ha hecho así a propósito: un `uninstall.php` que borrase las citas sin
 * avisar destruiría el historial de reservas de forma irreversible al
 * desinstalar el plugin por error.
 *
 * @package Pw_Calendario
 */

// WordPress define esta constante al ejecutar la desinstalación. Si no
// está, alguien ha solicitado el archivo directamente.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

/**
 * Retira los datos regenerables del plugin.
 *
 * @return void
 */
function pwcal_limpiar_datos_regenerables() {

	global $wpdb;

	// Eventos programados de los recordatorios.
	wp_clear_scheduled_hook( 'booked_send_user_reminders' );
	wp_clear_scheduled_hook( 'booked_send_admin_reminders' );

	// Secreto de los feeds de calendario y marcadores internos.
	delete_option( 'pwcal_feed_secreto' );
	delete_option( 'booked_version_check' );

	delete_transient( '_booked_welcome_screen_activation_redirect' );
	delete_transient( 'booked_show_new_tags' );

	// Transitorios del limitador de intentos de acceso.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM `{$wpdb->options}`
			 WHERE `option_name` LIKE %s OR `option_name` LIKE %s",
			$wpdb->esc_like( '_transient_pwcal_intentos_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pwcal_intentos_' ) . '%'
		)
	);

	// Capacidades añadidas a los perfiles y el perfil propio del plugin.
	foreach ( array( 'administrator', 'booked_booking_agent' ) as $nombre_perfil ) {

		$perfil = get_role( $nombre_perfil );

		if ( $perfil ) {
			$perfil->remove_cap( 'edit_booked_appointments' );
			$perfil->remove_cap( 'manage_booked_options' );
		}
	}

	remove_role( 'booked_booking_agent' );
}

/**
 * Borra las citas, los calendarios y la configuración.
 *
 * Solo se ejecuta si se ha activado expresamente la opción.
 *
 * @return void
 */
function pwcal_borrar_todos_los_datos() {

	global $wpdb;

	// --- Citas y sus metadatos ---
	$citas = get_posts(
		array(
			'post_type'        => 'booked_appointments',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	foreach ( $citas as $id_cita ) {
		wp_delete_post( $id_cita, true );
	}

	// --- Calendarios personalizados ---
	$calendarios = get_terms(
		array(
			'taxonomy'   => 'booked_custom_calendars',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $calendarios ) ) {
		foreach ( $calendarios as $id_calendario ) {
			// Los ajustes por calendario se guardan en `taxonomy_{id}`.
			delete_option( 'taxonomy_' . $id_calendario );
			wp_delete_term( $id_calendario, 'booked_custom_calendars' );
		}
	}

	// --- Opciones del plugin ---
	// Se borran por patrón porque son unas cincuenta y todas comparten
	// prefijo. `esc_like` evita que un guion bajo actúe como comodín.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
			$wpdb->esc_like( 'booked_' ) . '%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
			$wpdb->esc_like( 'pwcal_' ) . '%'
		)
	);

	// --- Metadatos de usuario ---
	delete_metadata( 'user', 0, 'booked_phone', '', true );
	delete_metadata( 'user', 0, 'avatar', '', true );
}

pwcal_limpiar_datos_regenerables();

if ( get_option( 'pwcal_borrar_datos_al_desinstalar' ) ) {
	pwcal_borrar_todos_los_datos();
}

// Por si el borrado completo no se ejecutó, la propia opción sí se retira.
delete_option( 'pwcal_borrar_datos_al_desinstalar' );
