<?php
/**
 * Cierre temporal de un calendario.
 *
 * Un calendario se puede dar por cerrado desde Citas > Calendarios, en la
 * ficha de cada calendario: mientras lo esté, el shortcode
 * `[booked-calendar calendar="…"]` muestra un mensaje en lugar del
 * calendario y el servidor rechaza cualquier reserva en él.
 *
 * Con una fecha de reapertura, el calendario vuelve solo ese día, sin que
 * nadie tenga que acordarse de quitar el cierre. Sin fecha, queda cerrado
 * hasta que se desmarque.
 *
 * Los datos van en la misma opción `taxonomy_{id}` que ya guardaba a quién
 * se asigna el calendario.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devuelve la configuración de cierre de un calendario.
 *
 * @param int $id_calendario ID del calendario.
 * @return array{cerrado:bool,hasta:string,mensaje:string}
 */
function pwcal_cierre_calendario( $id_calendario ) {

	$meta = get_option( 'taxonomy_' . (int) $id_calendario );
	$meta = is_array( $meta ) ? $meta : array();

	return array(
		'cerrado' => ! empty( $meta['pwcal_cerrado'] ),
		'hasta'   => isset( $meta['pwcal_cerrado_hasta'] ) ? (string) $meta['pwcal_cerrado_hasta'] : '',
		'mensaje' => isset( $meta['pwcal_mensaje_cerrado'] ) ? (string) $meta['pwcal_mensaje_cerrado'] : '',
	);
}

/**
 * Dice si un calendario está cerrado hoy.
 *
 * @param int $id_calendario ID del calendario.
 * @return bool
 */
function pwcal_calendario_cerrado( $id_calendario ) {

	if ( ! $id_calendario ) {
		return false;
	}

	$cierre = pwcal_cierre_calendario( $id_calendario );

	if ( ! $cierre['cerrado'] ) {
		return false;
	}

	// Sin fecha de reapertura, cerrado hasta que se desmarque.
	if ( ! $cierre['hasta'] ) {
		return true;
	}

	return current_time( 'Y-m-d' ) < $cierre['hasta'];
}

/**
 * Mensaje que se muestra en lugar del calendario cerrado.
 *
 * @param int $id_calendario ID del calendario.
 * @return string
 */
function pwcal_mensaje_cierre( $id_calendario ) {

	$cierre = pwcal_cierre_calendario( $id_calendario );

	if ( '' !== trim( $cierre['mensaje'] ) ) {
		return $cierre['mensaje'];
	}

	return __( 'Este calendario no está disponible por el momento.', 'pw-calendario' );
}

/**
 * Sustituye el calendario por el mensaje de cierre.
 *
 * Se engancha antes de ejecutar el shortcode, así que no se llega a montar
 * el calendario ni a hacer sus consultas.
 *
 * @param false|string $salida Salida previa.
 * @param string       $etiqueta Nombre del shortcode.
 * @param array|string $atributos Atributos.
 * @return false|string
 */
function pwcal_shortcode_calendario_cerrado( $salida, $etiqueta, $atributos ) {

	if ( 'booked-calendar' !== $etiqueta || ! is_array( $atributos ) || empty( $atributos['calendar'] ) ) {
		return $salida;
	}

	$id_calendario = (int) $atributos['calendar'];

	if ( ! pwcal_calendario_cerrado( $id_calendario ) ) {
		return $salida;
	}

	return '<div class="pwcal-calendario-cerrado" data-calendar-id="' . esc_attr( $id_calendario ) . '"><p>'
		. nl2br( esc_html( pwcal_mensaje_cierre( $id_calendario ) ) )
		. '</p></div>';
}
add_filter( 'pre_do_shortcode_tag', 'pwcal_shortcode_calendario_cerrado', 10, 3 );

/**
 * Campos del cierre en la ficha del calendario (edición).
 *
 * @param WP_Term $termino Término del calendario.
 * @return void
 */
function pwcal_cierre_campos_editar( $termino ) {

	$cierre = pwcal_cierre_calendario( $termino->term_id );

	?>
	<tr class="form-field">
		<th scope="row"><label for="pwcal_cerrado"><?php esc_html_e( 'Cerrado temporalmente', 'pw-calendario' ); ?></label></th>
		<td>
			<label>
				<input type="checkbox" name="term_meta[pwcal_cerrado]" id="pwcal_cerrado" value="1" <?php checked( $cierre['cerrado'] ); ?> style="width:auto">
				<?php esc_html_e( 'Ocultar el calendario y no aceptar reservas en él', 'pw-calendario' ); ?>
			</label>
			<?php if ( $cierre['cerrado'] && ! pwcal_calendario_cerrado( $termino->term_id ) ) : ?>
				<p class="description"><strong><?php esc_html_e( 'La fecha de reapertura ya ha pasado: el calendario vuelve a estar abierto.', 'pw-calendario' ); ?></strong></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="pwcal_cerrado_hasta"><?php esc_html_e( 'Reabrir el', 'pw-calendario' ); ?></label></th>
		<td>
			<input type="date" name="term_meta[pwcal_cerrado_hasta]" id="pwcal_cerrado_hasta" value="<?php echo esc_attr( $cierre['hasta'] ); ?>" style="width:auto">
			<p class="description"><?php esc_html_e( 'Opcional. Ese día el calendario vuelve a mostrarse solo. Si se deja vacío, sigue cerrado hasta que se desmarque la casilla.', 'pw-calendario' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="pwcal_mensaje_cerrado"><?php esc_html_e( 'Mensaje mientras está cerrado', 'pw-calendario' ); ?></label></th>
		<td>
			<textarea name="term_meta[pwcal_mensaje_cerrado]" id="pwcal_mensaje_cerrado" rows="3"><?php echo esc_textarea( $cierre['mensaje'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Se muestra en la web en el lugar del calendario.', 'pw-calendario' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'booked_custom_calendars_edit_form_fields', 'pwcal_cierre_campos_editar', 20 );

/**
 * Guarda el cierre del calendario.
 *
 * Va después del guardado de la asignación (prioridad 10), que reescribe la
 * opción completa; aquí se relee y se añaden solo las claves del cierre.
 *
 * @param int $id_termino ID del término.
 * @return void
 */
function pwcal_cierre_guardar( $id_termino ) {

	if ( ! isset( $_POST['term_meta'] ) || ! is_array( $_POST['term_meta'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_booked_options' ) ) {
		return;
	}

	$datos = wp_unslash( $_POST['term_meta'] );

	$hasta = isset( $datos['pwcal_cerrado_hasta'] ) ? sanitize_text_field( $datos['pwcal_cerrado_hasta'] ) : '';
	if ( $hasta && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $hasta ) ) {
		$hasta = '';
	}

	$meta = get_option( "taxonomy_$id_termino" );
	$meta = is_array( $meta ) ? $meta : array();

	$meta['pwcal_cerrado']         = empty( $datos['pwcal_cerrado'] ) ? '' : '1';
	$meta['pwcal_cerrado_hasta']   = $hasta;
	$meta['pwcal_mensaje_cerrado'] = isset( $datos['pwcal_mensaje_cerrado'] ) ? sanitize_textarea_field( $datos['pwcal_mensaje_cerrado'] ) : '';

	update_option( "taxonomy_$id_termino", $meta );
}
add_action( 'edited_booked_custom_calendars', 'pwcal_cierre_guardar', 20 );
add_action( 'create_booked_custom_calendars', 'pwcal_cierre_guardar', 20 );
