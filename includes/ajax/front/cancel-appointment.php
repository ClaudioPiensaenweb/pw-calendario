<?php
/**
 * Cancelación de una cita por parte del cliente.
 *
 * Este archivo se carga desde `Booked_AJAX::booked_cancel_appt()`, que ya ha
 * verificado el nonce, que hay sesión iniciada y que `$id_cita` corresponde
 * de verdad a una cita del plugin.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// `$id_cita` lo define el manejador AJAX. Sin él no se continúa.
if ( empty( $id_cita ) ) {
	return;
}

$cita = get_post( $id_cita );

if ( ! $cita ) {
	return;
}

// Solo el cliente que solicitó la cita puede cancelarla. Un gestor con
// permisos usa las pantallas del escritorio, no este punto de entrada.
if ( (int) $cita->post_author !== get_current_user_id() ) {
	return;
}

// El ajuste de "no permitir cancelaciones" se comprueba también aquí. Antes
// solo se aplicaba al pintar el enlace, así que bastaba con llamar al
// endpoint a mano para saltárselo.
if ( get_option( 'booked_dont_allow_user_cancellations', false ) ) {
	return;
}

if ( ! apply_filters( 'booked_shortcode_appointments_allow_cancel', true, $id_cita ) ) {
	return;
}

$calendarios       = wp_get_post_terms( $id_cita, 'booked_custom_calendars' );
$intervalo         = get_post_meta( $id_cita, '_appointment_timeslot', true );
$marca_tiempo      = (int) get_post_meta( $id_cita, '_appointment_timestamp', true );
$partes_intervalo  = explode( '-', (string) $intervalo );
$hora_inicio       = isset( $partes_intervalo[0] ) ? $partes_intervalo[0] : '0000';
$inicio_cita       = strtotime( gmdate( 'Y-m-d', $marca_tiempo ) . ' ' . $hora_inicio );
$ahora             = current_time( 'timestamp' );

// El margen de cancelación se expresa en horas y admite decimales
// (por ejemplo 0,5 para media hora).
$margen_cancelacion = (float) get_option( 'booked_cancellation_buffer', 0 );

if ( $margen_cancelacion > 0 ) {

	$limite = $inicio_cita - (int) round( $margen_cancelacion * HOUR_IN_SECONDS );

	// Fuera de plazo: se rechaza igual que se rechazaría en la interfaz.
	if ( $ahora > $limite ) {
		return;
	}
}

// Los avisos solo se envían si la cita todavía no ha pasado.
if ( $inicio_cita >= $ahora ) {

	// Aviso al cliente.
	$contenido = get_option( 'booked_cancellation_email_content' );
	$asunto    = get_option( 'booked_cancellation_email_subject' );

	if ( $contenido && $asunto ) {

		$sustituciones = booked_get_appointment_tokens( $id_cita );
		$contenido     = booked_token_replacement( $contenido, $sustituciones );
		$asunto        = booked_token_replacement( $asunto, $sustituciones );

		do_action( 'booked_cancellation_email', $sustituciones['email'], $asunto, $contenido );
	}

	// Aviso al gestor.
	$contenido_gestor = get_option( 'booked_admin_cancellation_email_content' );
	$asunto_gestor    = get_option( 'booked_admin_cancellation_email_subject' );

	if ( $contenido_gestor && $asunto_gestor ) {

		$correo_gestor    = booked_which_admin_to_send_email( $calendarios );
		$sustituciones    = booked_get_appointment_tokens( $id_cita );
		$contenido_gestor = booked_token_replacement( $contenido_gestor, $sustituciones );
		$asunto_gestor    = booked_token_replacement( $asunto_gestor, $sustituciones );

		do_action(
			'booked_admin_cancellation_email',
			$correo_gestor,
			$asunto_gestor,
			$contenido_gestor,
			$sustituciones['email'],
			$sustituciones['name']
		);
	}
}

do_action( 'booked_appointment_cancelled', $id_cita );

wp_delete_post( $id_cita, true );
