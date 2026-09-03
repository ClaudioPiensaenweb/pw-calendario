<?php
/**
 * Botón «Añadir al calendario», sin dependencias externas.
 *
 * Sustituye a la librería `atc.min.js` (widget de AddEvent) que traía el
 * plugin original. Se ha retirado por tres motivos:
 *
 * 1. Llevaba incrustada una clave de licencia del autor original. Como no
 *    se le pasaba ninguna licencia válida, la librería añadía siempre un
 *    enlace de atribución a «AddEvent.com» en el desplegable.
 * 2. Al pulsar cualquier opción enviaba el título, la descripción y la
 *    ubicación de la cita a `addevent.com`, es decir, los datos del
 *    cliente salían a un tercero.
 * 3. Dependía de que ese servicio externo estuviese disponible, y cargaba
 *    CSS e imágenes desde su dominio.
 *
 * Esta versión genera los enlaces en el propio servidor: Google Calendar y
 * Outlook mediante sus URL públicas de creación de evento, y un archivo
 * .ics descargable para Apple Calendario y Outlook de escritorio. No hace
 * falta ninguna clave ni conexión con terceros.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convierte una fecha y una hora locales a la marca UTC que exige iCalendar.
 *
 * @param string $fecha Fecha en formato Y-m-d.
 * @param string $hora  Hora en formato H:i:s.
 * @return string Marca en formato Ymd\THis\Z.
 */
function pwcal_fecha_utc( $fecha, $hora ) {

	$marca = strtotime( $fecha . ' ' . $hora );

	if ( false === $marca ) {
		$marca = time();
	}

	// `get_gmt_from_date` aplica la zona horaria configurada en WordPress.
	return gmdate( 'Ymd\THis\Z', strtotime( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $marca ) ) ) );
}

/**
 * Genera el contenido de un archivo .ics para una cita.
 *
 * @param array $evento Datos del evento: titulo, descripcion, ubicacion,
 *                      inicio y fin (ya en formato UTC).
 * @return string
 */
function pwcal_generar_ics( $evento ) {

	$salto = chr( 13 ) . chr( 10 );

	$lineas = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//piensaenweb.com//Pw Calendario//ES',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:' . $evento['uid'],
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
		'DTSTART:' . $evento['inicio'],
		'DTEND:' . $evento['fin'],
		'SUMMARY:' . pwcal_escapar_ics( $evento['titulo'] ),
		'DESCRIPTION:' . pwcal_escapar_ics( $evento['descripcion'] ),
		'LOCATION:' . pwcal_escapar_ics( $evento['ubicacion'] ),
		'END:VEVENT',
		'END:VCALENDAR',
	);

	return implode( $salto, $lineas ) . $salto;
}

/**
 * Pinta el botón «Añadir al calendario» de una cita.
 *
 * Mantiene la misma firma que la función original para no romper las
 * llamadas existentes ni el gancho de los complementos.
 *
 * @param array  $dates          Fechas y horas de la cita.
 * @param string $cf_meta_value  Datos de los campos personalizados (HTML).
 * @return void
 */
function booked_add_to_calendar_button( $dates, $cf_meta_value ) {

	if ( get_option( 'booked_hide_google_link', false ) ) {
		return;
	}

	$inicio_fecha = isset( $dates['atc_date_startend'] ) ? $dates['atc_date_startend'] : '';
	$inicio_hora  = isset( $dates['atc_time_start'] ) ? $dates['atc_time_start'] : '00:00:00';
	$fin_fecha    = isset( $dates['atc_date_startend_end'] ) ? $dates['atc_date_startend_end'] : $inicio_fecha;
	$fin_hora     = isset( $dates['atc_time_end'] ) ? $dates['atc_time_end'] : '23:59:00';

	if ( ! $inicio_fecha ) {
		return;
	}

	// La descripción viene con HTML de los campos personalizados: se
	// convierte a texto plano conservando los saltos de línea.
	$descripcion = preg_replace( '#<br\s*/?>#i', chr( 10 ), (string) $cf_meta_value );
	$descripcion = str_replace( '</p>', chr( 10 ) . chr( 10 ), $descripcion );
	$descripcion = trim( wp_strip_all_tags( $descripcion ) );

	$titulo = sprintf(
		/* translators: %s: nombre del sitio. */
		__( 'Cita con %s', 'pw-calendario' ),
		get_bloginfo( 'name' )
	);

	$ubicacion = get_bloginfo( 'name' );

	$inicio_utc = pwcal_fecha_utc( $inicio_fecha, $inicio_hora );
	$fin_utc    = pwcal_fecha_utc( $fin_fecha, $fin_hora );

	$evento = array(
		'uid'         => 'pwcal-' . md5( $inicio_utc . $titulo . $descripcion ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
		'titulo'      => $titulo,
		'descripcion' => $descripcion,
		'ubicacion'   => $ubicacion,
		'inicio'      => $inicio_utc,
		'fin'         => $fin_utc,
	);

	// --- Google Calendar ---
	$url_google = add_query_arg(
		array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $titulo ),
			'dates'    => rawurlencode( $inicio_utc . '/' . $fin_utc ),
			'details'  => rawurlencode( $descripcion ),
			'location' => rawurlencode( $ubicacion ),
		),
		'https://calendar.google.com/calendar/render'
	);

	// --- Outlook / Microsoft 365 ---
	// Nota: el parámetro `rru=addevent` es de la propia API de enlaces de
	// Outlook (significa «add event») y no tiene ninguna relación con la
	// librería de terceros AddEvent que se ha retirado.
	$url_outlook = add_query_arg(
		array(
			'path'     => rawurlencode( '/calendar/action/compose' ),
			'rru'      => 'addevent',
			'subject'  => rawurlencode( $titulo ),
			'startdt'  => rawurlencode( gmdate( 'c', strtotime( $inicio_utc ) ) ),
			'enddt'    => rawurlencode( gmdate( 'c', strtotime( $fin_utc ) ) ),
			'body'     => rawurlencode( $descripcion ),
			'location' => rawurlencode( $ubicacion ),
		),
		'https://outlook.live.com/calendar/0/deeplink/compose'
	);

	// --- Archivo .ics ---
	// Se sirve como URI de datos: no requiere ningún punto de entrada en
	// el servidor y funciona con el navegador sin conexión a terceros.
	$ics     = pwcal_generar_ics( $evento );
	$url_ics = 'data:text/calendar;charset=utf-8;base64,' . base64_encode( $ics );

	$nombre_archivo = 'cita-' . gmdate( 'Y-m-d', strtotime( $inicio_utc ) ) . '.ics';

	?>
	<div class="pwcal-atc">
		<button type="button" class="pwcal-atc-boton google-cal-button" aria-expanded="false">
			<i class="booked-icon booked-icon-calendar"></i>
			<?php esc_html_e( 'Añadir al calendario', 'pw-calendario' ); ?>
		</button>
		<ul class="pwcal-atc-menu" hidden>
			<li>
				<a href="<?php echo esc_url( $url_google ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Google Calendar', 'pw-calendario' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( $url_outlook ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Outlook.com', 'pw-calendario' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_attr( $url_ics ); ?>" download="<?php echo esc_attr( $nombre_archivo ); ?>">
					<?php esc_html_e( 'Apple Calendario u Outlook (.ics)', 'pw-calendario' ); ?>
				</a>
			</li>
		</ul>
	</div>
	<?php
}
