<?php
/**
 * Exportación de las citas a CSV.
 *
 * Este archivo se carga desde `Pw_Calendario::admin_init()`, que ya ha
 * comprobado la capacidad `edit_booked_appointments` y el nonce
 * `pwcal_exportar_csv`.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Neutraliza la inyección de fórmulas en hojas de cálculo.
 *
 * Excel, LibreOffice y Google Sheets interpretan como fórmula cualquier
 * celda que empiece por `=`, `+`, `-`, `@`, tabulador o retorno de carro.
 * Como los nombres y los campos personalizados los escribe el cliente al
 * reservar, sin esto un cliente podía colocar una fórmula en el CSV que se
 * ejecutaría en el equipo de quien abre el archivo.
 *
 * @param mixed $valor Valor de la celda.
 * @return string
 */
function pwcal_celda_csv_segura( $valor ) {

	$valor = (string) $valor;

	if ( '' === $valor ) {
		return '';
	}

	$peligrosos = array( '=', '+', '-', '@', chr( 9 ), chr( 13 ) );

	if ( in_array( substr( $valor, 0, 1 ), $peligrosos, true ) ) {
		// El apóstrofo inicial fuerza a la hoja de cálculo a tratar la
		// celda como texto sin alterar el contenido visible.
		$valor = "'" . $valor;
	}

	return $valor;
}

// ---------------------------------------------------------------------
// Filtros de la exportación
// ---------------------------------------------------------------------

$pwcal_periodo = pwcal_post_lista(
	'appointment_time',
	array( 'upcoming', 'past', 'all' ),
	'all'
);

$pwcal_meta_query = array();

if ( 'upcoming' === $pwcal_periodo || 'past' === $pwcal_periodo ) {
	$pwcal_meta_query = array(
		array(
			'key'     => '_appointment_timestamp',
			'value'   => current_time( 'timestamp' ),
			'compare' => ( 'upcoming' === $pwcal_periodo ) ? '>=' : '<',
			'type'    => 'NUMERIC',
		),
	);
}

// El estado se limita a los valores previstos: antes llegaba tal cual
// desde el formulario a `WP_Query`.
$pwcal_estado = pwcal_post_texto( 'appointment_type' );

$pwcal_estados_validos = array( 'publish', 'draft', 'future', 'pending' );

if ( ! in_array( $pwcal_estado, $pwcal_estados_validos, true ) ) {
	$pwcal_estado = array( 'publish', 'future' );
}

$pwcal_args = array(
	'post_type'      => 'booked_appointments',
	'posts_per_page' => 500,
	'post_status'    => $pwcal_estado,
	'meta_key'       => '_appointment_timestamp',
	'orderby'        => 'meta_value_num',
	'order'          => 'ASC',
	'meta_query'     => $pwcal_meta_query,
);

$pwcal_calendario = pwcal_post_calendario( 'calendar_id' );

if ( $pwcal_calendario ) {
	$pwcal_args['tax_query'] = array(
		array(
			'taxonomy' => 'booked_custom_calendars',
			'field'    => 'term_id',
			'terms'    => $pwcal_calendario,
		),
	);
}

// ---------------------------------------------------------------------
// Recopilación de los datos
// ---------------------------------------------------------------------

$pwcal_citas         = array();
$pwcal_formato_fecha = get_option( 'date_format' );
$pwcal_formato_hora  = get_option( 'time_format' );

$pwcal_consulta = new WP_Query( apply_filters( 'booked_fe_date_content_query', $pwcal_args ) );

foreach ( $pwcal_consulta->posts as $pwcal_cita ) {

	$pwcal_id = $pwcal_cita->ID;

	$pwcal_calendarios = array();
	$pwcal_terminos    = get_the_terms( $pwcal_id, 'booked_custom_calendars' );

	if ( ! empty( $pwcal_terminos ) && ! is_wp_error( $pwcal_terminos ) ) {
		foreach ( $pwcal_terminos as $pwcal_termino ) {
			$pwcal_calendarios[ $pwcal_termino->term_id ] = $pwcal_termino->name;
		}
	}

	$pwcal_marca     = get_post_meta( $pwcal_id, '_appointment_timestamp', true );
	$pwcal_intervalo = explode( '-', (string) get_post_meta( $pwcal_id, '_appointment_timeslot', true ) );
	$pwcal_usuario   = (int) get_post_meta( $pwcal_id, '_appointment_user', true );

	// Sin estos valores por defecto un intervalo mal formado provoca un
	// aviso de índice indefinido en PHP 8.
	$pwcal_hora_inicio = isset( $pwcal_intervalo[0] ) ? $pwcal_intervalo[0] : '0000';
	$pwcal_hora_fin    = isset( $pwcal_intervalo[1] ) ? $pwcal_intervalo[1] : '2400';

	$pwcal_nombre    = '';
	$pwcal_apellidos = '';
	$pwcal_correo    = '';

	if ( $pwcal_usuario ) {

		$pwcal_datos = get_userdata( $pwcal_usuario );

		if ( ! $pwcal_datos ) {
			continue;
		}

		$pwcal_nombre    = booked_get_name( $pwcal_usuario, 'first' );
		$pwcal_apellidos = booked_get_name( $pwcal_usuario, 'last' );
		$pwcal_correo    = $pwcal_datos->user_email;
	}

	if ( ! $pwcal_nombre ) {
		$pwcal_nombre    = get_post_meta( $pwcal_id, '_appointment_guest_name', true );
		$pwcal_apellidos = get_post_meta( $pwcal_id, '_appointment_guest_surname', true );
		$pwcal_correo    = get_post_meta( $pwcal_id, '_appointment_guest_email', true );
	}

	// Los campos personalizados se guardan como HTML; para el CSV se
	// convierten a texto plano.
	$pwcal_campos = get_post_meta( $pwcal_id, '_cf_meta_value', true );

	if ( $pwcal_campos ) {
		$pwcal_campos = str_replace(
			array( '</p>', '<br>', '<br/>', '<br />' ),
			array( chr( 10 ) . chr( 10 ), chr( 10 ), chr( 10 ), chr( 10 ) ),
			$pwcal_campos
		);
		$pwcal_campos = trim( wp_strip_all_tags( $pwcal_campos ) );
	} else {
		$pwcal_campos = '';
	}

	$pwcal_fecha  = date_i18n( $pwcal_formato_fecha, $pwcal_marca );
	$pwcal_inicio = date_i18n( $pwcal_formato_hora, strtotime( gmdate( 'Y-m-d', $pwcal_marca ) . ' ' . $pwcal_hora_inicio ) );
	$pwcal_fin    = date_i18n( $pwcal_formato_hora, strtotime( gmdate( 'Y-m-d', $pwcal_marca ) . ' ' . $pwcal_hora_fin ) );

	// Nota: aquí NO se usa `esc_html()`. Un CSV no es HTML; escaparlo
	// convertía los acentos y los "&" en entidades dentro del archivo.
	$pwcal_citas[ $pwcal_id ] = array(
		'customer_name'                  => $pwcal_nombre,
		'customer_surname'               => $pwcal_apellidos,
		'customer_email'                 => $pwcal_correo,
		'calendar'                       => implode( ', ', $pwcal_calendarios ),
		'appointment_date'               => $pwcal_fecha,
		'appointment_start_time'         => $pwcal_inicio,
		'appointment_end_time'           => $pwcal_fin,
		'appointment_combined_date_time' => sprintf(
			/* translators: 1: fecha, 2: hora de inicio, 3: hora de fin. */
			__( '%1$s de %2$s a %3$s', 'pw-calendario' ),
			$pwcal_fecha,
			$pwcal_inicio,
			$pwcal_fin
		),
		'custom_field_data'              => $pwcal_campos,
	);
}

wp_reset_postdata();

// ---------------------------------------------------------------------
// Volcado del archivo
// ---------------------------------------------------------------------

$pwcal_nombre_archivo = 'citas-' . gmdate( 'Y-m-d' ) . '.csv';

nocache_headers();
header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename=' . $pwcal_nombre_archivo );

$pwcal_salida = fopen( 'php://output', 'w' );

// Marca de orden de bytes UTF-8: sin ella Excel en Windows muestra los
// acentos y las eñes como caracteres corruptos.
fwrite( $pwcal_salida, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

$pwcal_columnas = apply_filters(
	'booked_csv_export_columns',
	array(
		__( 'Nombre', 'pw-calendario' ),
		__( 'Apellidos', 'pw-calendario' ),
		__( 'Correo electrónico', 'pw-calendario' ),
		__( 'Calendario', 'pw-calendario' ),
		__( 'Fecha', 'pw-calendario' ),
		__( 'Hora de inicio', 'pw-calendario' ),
		__( 'Hora de fin', 'pw-calendario' ),
		__( 'Fecha y hora', 'pw-calendario' ),
		__( 'Campos personalizados', 'pw-calendario' ),
	)
);

fputcsv( $pwcal_salida, $pwcal_columnas, ';' );

foreach ( $pwcal_citas as $pwcal_id => $pwcal_fila ) {

	$pwcal_fila = apply_filters( 'booked_csv_row_data', $pwcal_fila, $pwcal_id );
	$pwcal_fila = array_map( 'pwcal_celda_csv_segura', $pwcal_fila );

	// Punto y coma como separador: es lo que espera Excel con la
	// configuración regional española.
	fputcsv( $pwcal_salida, $pwcal_fila, ';' );
}

fclose( $pwcal_salida );

exit;
