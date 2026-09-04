<?php
/**
 * Punto de entrada REST para la importación.
 *
 * Existe para poder lanzar y comprobar la importación de un archivo grande
 * sin depender de una subida por formulario: el archivo de la web de
 * origen pesaba 1,5 MB con 1242 citas.
 *
 * Exige `manage_options`, así que solo funciona autenticado. Se puede usar
 * con una contraseña de aplicación de WordPress.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registra las rutas.
 *
 * @return void
 */
function pwcal_registrar_rest_importacion() {

	register_rest_route(
		'pwcal/v1',
		'/importar',
		array(
			'methods'             => 'POST',
			'callback'            => 'pwcal_rest_importar',
			'permission_callback' => 'pwcal_rest_permiso',
		)
	);

	register_rest_route(
		'pwcal/v1',
		'/deshacer-importacion',
		array(
			'methods'             => 'POST',
			'callback'            => 'pwcal_rest_deshacer',
			'permission_callback' => 'pwcal_rest_permiso',
		)
	);

	register_rest_route(
		'pwcal/v1',
		'/registro',
		array(
			'methods'             => 'GET',
			'callback'            => 'pwcal_rest_registro',
			'permission_callback' => 'pwcal_rest_permiso',
		)
	);

	register_rest_route(
		'pwcal/v1',
		'/estado',
		array(
			'methods'             => 'GET',
			'callback'            => 'pwcal_rest_estado',
			'permission_callback' => 'pwcal_rest_permiso',
		)
	);
}
add_action( 'rest_api_init', 'pwcal_registrar_rest_importacion' );

/**
 * Comprueba los permisos.
 *
 * @return bool
 */
function pwcal_rest_permiso() {

	return current_user_can( 'manage_options' );
}

/**
 * Atiende la importación.
 *
 * @param WP_REST_Request $peticion Petición.
 * @return WP_REST_Response|WP_Error
 */
function pwcal_rest_importar( $peticion ) {

	$cuerpo = $peticion->get_json_params();

	if ( ! is_array( $cuerpo ) ) {
		return new WP_Error(
			'pwcal_cuerpo_invalido',
			__( 'No se ha recibido un cuerpo JSON válido.', 'pw-calendario' ),
			array( 'status' => 400 )
		);
	}

	// El archivo puede venir directamente o dentro de "datos".
	$datos = isset( $cuerpo['datos'] ) ? $cuerpo['datos'] : $cuerpo;

	$opciones = array(
		'en_seco'           => ! isset( $cuerpo['en_seco'] ) || ! empty( $cuerpo['en_seco'] ),
		'mapa_calendarios'  => isset( $cuerpo['mapa_calendarios'] ) && is_array( $cuerpo['mapa_calendarios'] )
			? $cuerpo['mapa_calendarios']
			: array(),
		'crear_calendarios' => ! isset( $cuerpo['crear_calendarios'] ) || ! empty( $cuerpo['crear_calendarios'] ),
		'desde'             => isset( $cuerpo['desde'] ) ? (int) $cuerpo['desde'] : 0,
		'cuantas'           => isset( $cuerpo['cuantas'] ) ? (int) $cuerpo['cuantas'] : 0,
	);

	$informe = pwcal_importar( $datos, $opciones );

	if ( is_wp_error( $informe ) ) {
		return $informe;
	}

	return rest_ensure_response( $informe );
}

/**
 * Deshace la importación.
 *
 * @param WP_REST_Request $peticion Petición.
 * @return WP_REST_Response
 */
function pwcal_rest_deshacer( $peticion ) {

	$cuerpo  = $peticion->get_json_params();
	$en_seco = ! is_array( $cuerpo ) || ! isset( $cuerpo['en_seco'] ) || ! empty( $cuerpo['en_seco'] );

	return rest_ensure_response( pwcal_deshacer_importacion( $en_seco ) );
}

/**
 * Devuelve un resumen del estado de las citas de este sitio.
 *
 * @return WP_REST_Response
 */
function pwcal_rest_estado() {

	global $wpdb;

	$estado = array(
		'version'    => PWCAL_VERSION,
		'citas'      => 0,
		'importadas' => 0,
		'personas'   => 0,
		'por_estado' => array(),
	);

	$estado['citas'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'booked_appointments'"
	);

	$estado['importadas'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
			PWCAL_META_ORIGEN
		)
	);

	$estado['personas'] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM( CAST( meta_value AS UNSIGNED ) ) FROM {$wpdb->postmeta} WHERE meta_key = %s",
			PWCAL_META_PERSONAS
		)
	);

	$filas = $wpdb->get_results(
		"SELECT post_status, COUNT(*) AS total
		 FROM {$wpdb->posts}
		 WHERE post_type = 'booked_appointments'
		 GROUP BY post_status",
		ARRAY_A
	);

	if ( is_array( $filas ) ) {
		foreach ( $filas as $fila ) {
			$estado['por_estado'][ $fila['post_status'] ] = (int) $fila['total'];
		}
	}

	$calendarios = get_terms( array( 'taxonomy' => 'booked_custom_calendars', 'hide_empty' => false ) );

	if ( is_array( $calendarios ) ) {
		foreach ( $calendarios as $calendario ) {
			$estado['calendarios'][] = array(
				'term_id' => (int) $calendario->term_id,
				'name'    => $calendario->name,
				'count'   => (int) $calendario->count,
			);
		}
	}

	return rest_ensure_response( $estado );
}

/**
 * Devuelve el final del registro de errores de WordPress.
 *
 * En un sitio sin acceso al servidor, un error critico solo deja el
 * mensaje generico de WordPress y el detalle se queda en
 * `wp-content/debug.log`, que no es accesible. Esto lo devuelve al
 * administrador para poder diagnosticar sin FTP.
 *
 * Solo lee, y solo para quien puede administrar el sitio.
 *
 * @param WP_REST_Request $peticion Petición.
 * @return WP_REST_Response|WP_Error
 */
function pwcal_rest_registro( $peticion ) {

	$lineas = (int) $peticion->get_param( 'lineas' );

	if ( $lineas < 1 || $lineas > 500 ) {
		$lineas = 60;
	}

	$ruta = WP_CONTENT_DIR . '/debug.log';

	if ( ! file_exists( $ruta ) || ! is_readable( $ruta ) ) {
		return rest_ensure_response(
			array(
				'existe' => false,
				'ruta'   => $ruta,
				'aviso'  => __( 'No hay registro de errores. Comprueba que WP_DEBUG_LOG esté activo.', 'pw-calendario' ),
			)
		);
	}

	$contenido = file( $ruta, FILE_IGNORE_NEW_LINES );

	if ( ! is_array( $contenido ) ) {
		$contenido = array();
	}

	return rest_ensure_response(
		array(
			'existe'  => true,
			'bytes'   => filesize( $ruta ),
			'total'   => count( $contenido ),
			'ultimas' => array_slice( $contenido, -$lineas ),
		)
	);
}
