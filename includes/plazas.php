<?php
/**
 * Aforo por personas.
 *
 * El plugin original contaba el aforo en **reservas**, no en personas: cada
 * cita descontaba exactamente una plaza de la franja, sin importar cuánta
 * gente viniera. El tamaño del grupo solo existía en el título del producto
 * de WooCommerce («Visita a la bodega – 6 personas»), que el plugin nunca
 * llegaba a leer.
 *
 * La consecuencia era una sobreventa silenciosa: en una franja de 20
 * plazas caben 20 reservas, y si cada una es de 6 personas se presentan
 * 120. Quien configuraba el calendario creía estar limitando el aforo.
 *
 * Este archivo introduce el tamaño de grupo como dato de primer nivel:
 *
 * - Cada cita guarda cuántas personas la componen en el metadato
 *   `_appointment_personas`.
 * - El aforo disponible se calcula sumando personas, no contando citas.
 * - El cliente elige el número de personas al reservar, con el máximo
 *   puesto en las plazas que realmente quedan.
 *
 * Compatibilidad con lo ya existente: las citas anteriores no tienen ese
 * metadato, así que se consideran de una persona. No se pierde nada y el
 * cálculo sigue siendo correcto para ellas.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metadato donde se guarda el número de personas de una cita.
 */
const PWCAL_META_PERSONAS = '_appointment_personas';

/**
 * Devuelve cuántas personas componen una cita.
 *
 * @param int $id_cita ID de la cita.
 * @return int Siempre 1 o más.
 */
function pwcal_personas_de_cita( $id_cita ) {

	$personas = get_post_meta( (int) $id_cita, PWCAL_META_PERSONAS, true );
	$personas = (int) $personas;

	// Las citas creadas antes de esta versión no llevan el metadato.
	if ( $personas < 1 ) {
		$personas = 1;
	}

	/**
	 * Permite ajustar el tamaño de grupo de una cita concreta.
	 *
	 * @param int $personas Número de personas.
	 * @param int $id_cita  ID de la cita.
	 */
	return (int) apply_filters( 'pwcal_personas_de_cita', $personas, (int) $id_cita );
}

/**
 * Suma las plazas que ocupan un conjunto de citas.
 *
 * Sustituye a los `count()` que había repartidos por el cálculo de
 * disponibilidad.
 *
 * @param array $ids_citas IDs de las citas.
 * @return int Total de personas.
 */
function pwcal_plazas_ocupadas( $ids_citas ) {

	if ( empty( $ids_citas ) || ! is_array( $ids_citas ) ) {
		return 0;
	}

	$total = 0;

	foreach ( $ids_citas as $id_cita ) {
		$total += pwcal_personas_de_cita( $id_cita );
	}

	return $total;
}

/**
 * Guarda el número de personas de una cita.
 *
 * @param int $id_cita  ID de la cita.
 * @param int $personas Número de personas.
 * @return int El valor que se ha guardado.
 */
function pwcal_guardar_personas( $id_cita, $personas ) {

	$personas = max( 1, (int) $personas );

	update_post_meta( (int) $id_cita, PWCAL_META_PERSONAS, $personas );

	return $personas;
}

/**
 * Límite máximo de personas por reserva, si se quiere imponer uno.
 *
 * Por defecto no hay límite propio: el techo son las plazas que queden
 * libres en la franja, que es lo que se pidió. Se deja el filtro por si
 * en algún momento interesa poner un tope (por ejemplo, obligar a que los
 * grupos grandes se gestionen por teléfono).
 *
 * @return int 0 significa «sin límite adicional».
 */
function pwcal_limite_por_reserva() {

	return (int) apply_filters( 'pwcal_limite_personas_por_reserva', 0 );
}

/**
 * Devuelve el número de personas solicitado en la petición actual.
 *
 * @return int Siempre 1 o más.
 */
function pwcal_personas_solicitadas() {

	$personas = pwcal_post_entero( 'personas', 1 );

	if ( $personas < 1 ) {
		$personas = 1;
	}

	return $personas;
}

/**
 * Calcula cuántas plazas quedan libres en una franja, en personas.
 *
 * @param string   $fecha         Fecha de la cita.
 * @param string   $intervalo     Franja horaria (`HHMM-HHMM`).
 * @param int|bool $id_calendario Calendario, o false para el predeterminado.
 * @return int Plazas libres.
 */
function pwcal_plazas_libres( $fecha, $intervalo, $id_calendario = false ) {

	return (int) booked_appt_is_available( $fecha, $intervalo, $id_calendario );
}

/**
 * Comprueba si caben las personas solicitadas en una franja.
 *
 * @param string   $fecha         Fecha de la cita.
 * @param string   $intervalo     Franja horaria.
 * @param int      $personas      Personas solicitadas.
 * @param int|bool $id_calendario Calendario.
 * @return true|WP_Error Cierto si caben; si no, el motivo.
 */
function pwcal_validar_personas( $fecha, $intervalo, $personas, $id_calendario = false ) {

	$personas = (int) $personas;

	if ( $personas < 1 ) {
		return new WP_Error(
			'pwcal_personas_minimo',
			__( 'Indica para cuántas personas es la reserva.', 'pw-calendario' )
		);
	}

	$limite = pwcal_limite_por_reserva();

	if ( $limite > 0 && $personas > $limite ) {
		return new WP_Error(
			'pwcal_personas_limite',
			sprintf(
				/* translators: %d: número máximo de personas por reserva. */
				_n(
					'El máximo por reserva es de %d persona. Para grupos mayores, ponte en contacto con nosotros.',
					'El máximo por reserva es de %d personas. Para grupos mayores, ponte en contacto con nosotros.',
					$limite,
					'pw-calendario'
				),
				$limite
			)
		);
	}

	$libres = pwcal_plazas_libres( $fecha, $intervalo, $id_calendario );

	if ( $libres < 1 ) {
		return new WP_Error(
			'pwcal_sin_plazas',
			__( 'Esta franja horaria ya está completa. Elige otra hora.', 'pw-calendario' )
		);
	}

	if ( $personas > $libres ) {
		return new WP_Error(
			'pwcal_plazas_insuficientes',
			sprintf(
				/* translators: %d: plazas que quedan libres. */
				_n(
					'Solo queda %d plaza libre en esta franja horaria.',
					'Solo quedan %d plazas libres en esta franja horaria.',
					$libres,
					'pw-calendario'
				),
				$libres
			)
		);
	}

	return true;
}

/**
 * Plazas libres mínimas entre todas las franjas de una reserva.
 *
 * El formulario admite reservar varias franjas de una vez. El número de
 * personas es común a todas, así que el techo lo marca la franja con menos
 * hueco: si una tiene 8 libres y otra 3, el máximo son 3.
 *
 * @param array $reservas Estructura `$bookings` del formulario.
 * @return int Plazas libres. 0 si no hay ninguna franja.
 */
function pwcal_plazas_libres_minimas( $reservas ) {

	if ( empty( $reservas ) || ! is_array( $reservas ) ) {
		return 0;
	}

	$minimo = null;

	foreach ( $reservas as $citas ) {

		if ( ! is_array( $citas ) ) {
			continue;
		}

		foreach ( $citas as $cita ) {

			if ( empty( $cita['date'] ) || empty( $cita['timeslot'] ) ) {
				continue;
			}

			$libres = pwcal_plazas_libres(
				$cita['date'],
				$cita['timeslot'],
				isset( $cita['calendar_id'] ) ? $cita['calendar_id'] : false
			);

			$minimo = ( null === $minimo ) ? $libres : min( $minimo, $libres );
		}
	}

	return ( null === $minimo ) ? 0 : (int) $minimo;
}

/**
 * Pinta el selector de número de personas del formulario de reserva.
 *
 * El máximo es el número de plazas que quedan libres, que es exactamente
 * el comportamiento pedido: poder reservar tantas plazas como queden.
 *
 * @param int $libres Plazas libres en la franja.
 * @return void
 */
function pwcal_selector_personas( $libres ) {

	$libres = max( 0, (int) $libres );
	$limite = pwcal_limite_por_reserva();

	$maximo = ( $limite > 0 ) ? min( $libres, $limite ) : $libres;

	if ( $maximo < 1 ) {
		return;
	}

	// Con una sola plaza libre no hay nada que elegir: se envía oculto.
	if ( 1 === $maximo ) {
		echo '<input type="hidden" name="personas" value="1">';
		return;
	}

	?>
	<div class="field field-personas">
		<label for="pwcal-personas">
			<?php esc_html_e( 'Número de personas', 'pw-calendario' ); ?>
			<span class="pwcal-plazas-libres">
				<?php
				printf(
					/* translators: %d: plazas libres. */
					esc_html( _n( '(queda %d plaza)', '(quedan %d plazas)', $maximo, 'pw-calendario' ) ),
					(int) $maximo
				);
				?>
			</span>
		</label>
		<select name="personas" id="pwcal-personas" class="large" required>
			<?php for ( $i = 1; $i <= $maximo; $i++ ) : ?>
				<option value="<?php echo esc_attr( $i ); ?>">
					<?php
					printf(
						/* translators: %d: número de personas. */
						esc_html( _n( '%d persona', '%d personas', $i, 'pw-calendario' ) ),
						$i
					);
					?>
				</option>
			<?php endfor; ?>
		</select>
	</div>
	<?php
}

/**
 * Texto corto con el tamaño del grupo, para los listados del escritorio.
 *
 * Devuelve cadena vacía cuando la cita es de una sola persona, para no
 * ensuciar el listado con un dato que no aporta.
 *
 * @param int $id_cita ID de la cita.
 * @return string
 */
function pwcal_etiqueta_personas( $id_cita ) {

	$personas = pwcal_personas_de_cita( $id_cita );

	if ( $personas < 2 ) {
		return '';
	}

	return sprintf(
		/* translators: %d: número de personas. */
		_n( '%d persona', '%d personas', $personas, 'pw-calendario' ),
		$personas
	);
}

/* -------------------------------------------------------------------------
 * Días de la semana en los rangos de franjas personalizadas
 * ---------------------------------------------------------------------- */

/**
 * Devuelve los días de la semana en los que se muestran las visitas de la
 * bodega, con las etiquetas cortas que se usan en la interfaz.
 *
 * Se sigue la convención de `date('w')`: 0 es domingo y 6 es sábado.
 *
 * @return array Índice numérico del día => etiqueta corta.
 */
function pwcal_dias_de_la_semana() {

	return array(
		1 => _x( 'L', 'lunes', 'pw-calendario' ),
		2 => _x( 'M', 'martes', 'pw-calendario' ),
		3 => _x( 'X', 'miércoles', 'pw-calendario' ),
		4 => _x( 'J', 'jueves', 'pw-calendario' ),
		5 => _x( 'V', 'viernes', 'pw-calendario' ),
		6 => _x( 'S', 'sábado', 'pw-calendario' ),
		0 => _x( 'D', 'domingo', 'pw-calendario' ),
	);
}

/**
 * Normaliza la lista de días guardada en el campo oculto.
 *
 * @param string $guardado Lista separada por comas, o cadena vacía.
 * @return array Días válidos. Array vacío significa «todos los días».
 */
function pwcal_dias_permitidos( $guardado ) {

	$guardado = trim( (string) $guardado );

	if ( '' === $guardado ) {
		return array();
	}

	$dias = array();

	foreach ( explode( ',', $guardado ) as $trozo ) {

		$trozo = trim( $trozo );

		if ( '' === $trozo || ! is_numeric( $trozo ) ) {
			continue;
		}

		$dia = (int) $trozo;

		if ( $dia >= 0 && $dia <= 6 ) {
			$dias[ $dia ] = $dia;
		}
	}

	return array_values( $dias );
}

/**
 * Comprueba si un día de la semana entra en la selección.
 *
 * @param int   $dia_semana Día según `date('w')`.
 * @param array $permitidos Días permitidos. Vacío significa todos.
 * @return bool
 */
function pwcal_dia_incluido( $dia_semana, $permitidos ) {

	// Sin selección, el rango se aplica a todos los días: es como se
	// comportaban las entradas anteriores a esta versión.
	if ( empty( $permitidos ) ) {
		return true;
	}

	return in_array( (int) $dia_semana, array_map( 'intval', $permitidos ), true );
}

/**
 * Pinta las casillas de día de la semana de una franja personalizada.
 *
 * El valor se guarda en un campo oculto y no en las propias casillas. El
 * formulario de ajustes se serializa en arrays paralelos por posición, y
 * una casilla sin marcar no se envía: eso desplazaría los datos de todos
 * los bloques siguientes. El campo oculto siempre viaja, así que la
 * alineación se mantiene.
 *
 * @param string $guardado Valor actual del campo.
 * @param string $sufijo   Sufijo único para los identificadores.
 * @return void
 */
function pwcal_casillas_dias_semana( $guardado, $sufijo = '' ) {

	$permitidos = pwcal_dias_permitidos( $guardado );
	$sufijo     = $sufijo ? $sufijo : (string) wp_rand( 1, 99999999 );

	?>
	<div class="pwcal-dias-semana">
		<span class="pwcal-dias-semana-titulo">
			<?php esc_html_e( 'Solo estos días', 'pw-calendario' ); ?>
			<small><?php esc_html_e( '(si no marcas ninguno, se aplica todos los días del rango)', 'pw-calendario' ); ?></small>
		</span>

		<span class="pwcal-dias-semana-casillas">
			<?php foreach ( pwcal_dias_de_la_semana() as $numero => $etiqueta ) : ?>
				<?php $id = 'pwcal-dia-' . $numero . '-' . $sufijo; ?>
				<input
					type="checkbox"
					id="<?php echo esc_attr( $id ); ?>"
					class="pwcal-dia-semana"
					value="<?php echo esc_attr( $numero ); ?>"
					<?php checked( in_array( $numero, $permitidos, true ) ); ?>>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $etiqueta ); ?></label>
			<?php endforeach; ?>
		</span>

		<input
			type="hidden"
			name="booked_custom_dias"
			class="pwcal-dias-semana-valor"
			value="<?php echo esc_attr( implode( ',', $permitidos ) ); ?>">
	</div>
	<?php
}
