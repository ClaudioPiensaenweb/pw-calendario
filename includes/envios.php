<?php
/**
 * Control de los envíos de correo.
 *
 * El problema que resuelve: una copia del sitio en un dominio de pruebas
 * manda correos de verdad a clientes de verdad. Confirmaciones,
 * cancelaciones y, sobre todo, recordatorios de citas que en la copia
 * pueden estar duplicados o desfasados.
 *
 * No se resuelve poniendo un dominio en el código, porque el plugin se
 * usa en varios sitios. Se resuelve al revés: cada instalación declara en
 * qué dominio tiene permiso para enviar, y en cualquier otro se calla.
 *
 * Tres comprobaciones, de la más general a la más concreta:
 *
 * 1. **El tipo de entorno de WordPress.** `wp_get_environment_type()`
 *    existe desde WordPress 5.5 y se define en `wp-config.php` con
 *    `WP_ENVIRONMENT_TYPE`. Si el sitio se declara `staging`, `development`
 *    o `local`, no se envía nada. Es la vía que recomienda WordPress y no
 *    cuesta nada respetarla.
 *
 * 2. **El dominio autorizado.** La opción `pwcal_dominio_envios`. Si está
 *    vacía, se envía con normalidad: una instalación nueva se comporta
 *    como siempre y nadie se lleva una sorpresa. Si tiene un dominio, solo
 *    se envía cuando el sitio responde en ese dominio. Cuando la web se
 *    publica en su dominio definitivo, los envíos se reanudan solos.
 *
 * 3. **La pausa manual.** `pwcal_envios_pausados`, para una ventana de
 *    mantenimiento.
 *
 * Y dos cosas para que esto no se convierta en un problema peor que el que
 * resuelve:
 *
 * - **Nada se descarta en silencio.** Cada correo bloqueado se cuenta y se
 *   guardan los últimos, con destinatario, asunto y motivo.
 * - **Un aviso en todas las pantallas del escritorio** mientras los
 *   envíos estén detenidos. Que no se pueda olvidar es la mitad del
 *   trabajo.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cuántos correos bloqueados se recuerdan.
 */
const PWCAL_ENVIOS_HISTORICO = 30;

/**
 * Devuelve el dominio del sitio, sin el «www.».
 *
 * @param string $url URL. Por omisión, la del sitio.
 * @return string
 */
function pwcal_dominio( $url = '' ) {

	if ( '' === $url ) {
		$url = home_url();
	}

	$partes = wp_parse_url( $url );
	$host   = isset( $partes['host'] ) ? $partes['host'] : '';

	if ( '' === $host ) {
		// Puede llegar solo el dominio, sin esquema.
		$host = preg_replace( '~^.*?://~', '', (string) $url );
		$host = preg_replace( '~[/?#].*$~', '', $host );
	}

	$host = strtolower( trim( $host ) );

	return preg_replace( '~^www\.~', '', $host );
}

/**
 * Decide si el plugin puede enviar correo, y por qué.
 *
 * @return array {
 *     @type bool   $permitido Si se puede enviar.
 *     @type string $motivo    Identificador del motivo.
 *     @type string $texto     Explicación para una persona.
 * }
 */
function pwcal_estado_envios() {

	$estado = array(
		'permitido' => true,
		'motivo'    => 'permitido',
		'texto'     => __( 'Los correos se envían con normalidad.', 'pw-calendario' ),
	);

	// 1) Pausa manual.
	if ( get_option( 'pwcal_envios_pausados', false ) ) {
		$estado = array(
			'permitido' => false,
			'motivo'    => 'pausado',
			'texto'     => __( 'Los envíos están pausados a mano en los ajustes del plugin.', 'pw-calendario' ),
		);
	}

	// 2) Tipo de entorno declarado en WordPress.
	if ( $estado['permitido'] && function_exists( 'wp_get_environment_type' ) ) {

		$entorno = wp_get_environment_type();

		if ( 'production' !== $entorno ) {
			$estado = array(
				'permitido' => false,
				'motivo'    => 'entorno',
				'texto'     => sprintf(
					/* translators: %s: tipo de entorno de WordPress. */
					__( 'WordPress declara este sitio como «%s», no como producción.', 'pw-calendario' ),
					$entorno
				),
			);
		}
	}

	// 3) Dominio autorizado.
	if ( $estado['permitido'] ) {

		$autorizado = pwcal_dominio( (string) get_option( 'pwcal_dominio_envios', '' ) );

		if ( '' !== $autorizado ) {

			$actual = pwcal_dominio();

			if ( $actual !== $autorizado ) {
				$estado = array(
					'permitido' => false,
					'motivo'    => 'dominio',
					'texto'     => sprintf(
						/* translators: 1: dominio actual. 2: dominio autorizado. */
						__( 'Este sitio responde en %1$s y los envíos están limitados a %2$s.', 'pw-calendario' ),
						$actual,
						$autorizado
					),
				);
			}
		}
	}

	/**
	 * Permite decidir por completo si se envía.
	 *
	 * @param array $estado Estado calculado.
	 */
	return apply_filters( 'pwcal_estado_envios', $estado );
}

/**
 * Atajo: ¿se puede enviar?
 *
 * @return bool
 */
function pwcal_envio_permitido() {

	$estado = pwcal_estado_envios();

	return ! empty( $estado['permitido'] );
}

/**
 * Anota un correo que no se ha enviado.
 *
 * Que quede registro es la diferencia entre una medida de seguridad y un
 * agujero por el que se pierden avisos sin que nadie se entere.
 *
 * @param string $destino Destinatario.
 * @param string $asunto  Asunto.
 * @param string $motivo  Motivo del bloqueo.
 * @return void
 */
function pwcal_anotar_envio_bloqueado( $destino, $asunto, $motivo ) {

	$registro = get_option( 'pwcal_envios_bloqueados', array() );

	if ( ! is_array( $registro ) ) {
		$registro = array();
	}

	if ( ! isset( $registro['total'] ) ) {
		$registro['total'] = 0;
	}

	if ( ! isset( $registro['ultimos'] ) || ! is_array( $registro['ultimos'] ) ) {
		$registro['ultimos'] = array();
	}

	$registro['total']++;

	array_unshift(
		$registro['ultimos'],
		array
		(
			'fecha'   => gmdate( 'Y-m-d H:i:s' ),
			'destino' => is_array( $destino ) ? implode( ', ', $destino ) : (string) $destino,
			'asunto'  => (string) $asunto,
			'motivo'  => (string) $motivo,
		)
	);

	$registro['ultimos'] = array_slice( $registro['ultimos'], 0, PWCAL_ENVIOS_HISTORICO );

	update_option( 'pwcal_envios_bloqueados', $registro, false );

	/**
	 * Se dispara cuando un correo del plugin no se envía.
	 *
	 * @param string $destino Destinatario.
	 * @param string $asunto  Asunto.
	 * @param string $motivo  Motivo.
	 */
	do_action( 'pwcal_envio_bloqueado', $destino, $asunto, $motivo );
}

/**
 * Puerta única por la que pasa todo el correo del plugin.
 *
 * @param string $destino Destinatario.
 * @param string $asunto  Asunto.
 * @return bool Cierto si se puede seguir.
 */
function pwcal_puede_enviar( $destino, $asunto = '' ) {

	$estado = pwcal_estado_envios();

	if ( ! empty( $estado['permitido'] ) ) {
		return true;
	}

	pwcal_anotar_envio_bloqueado( $destino, $asunto, $estado['motivo'] );

	return false;
}

/**
 * Vacía el registro de correos bloqueados.
 *
 * @return void
 */
function pwcal_vaciar_registro_envios() {

	update_option( 'pwcal_envios_bloqueados', array( 'total' => 0, 'ultimos' => array() ), false );
}

/**
 * Avisa en el escritorio mientras los envíos estén detenidos.
 *
 * Se muestra en todas las pantallas a propósito: el riesgo real no es que
 * los correos se detengan, es que nadie recuerde volver a activarlos el
 * día que la web se publique.
 *
 * @return void
 */
function pwcal_aviso_envios_detenidos() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$estado = pwcal_estado_envios();

	if ( ! empty( $estado['permitido'] ) ) {
		return;
	}

	$registro = get_option( 'pwcal_envios_bloqueados', array() );
	$total    = ( is_array( $registro ) && isset( $registro['total'] ) ) ? (int) $registro['total'] : 0;

	echo '<div class="notice notice-warning">';
	echo '<p><strong>' . esc_html__( 'Pw Calendario: los correos están detenidos.', 'pw-calendario' ) . '</strong> ';
	echo esc_html( $estado['texto'] );
	echo '</p>';

	if ( $total > 0 ) {
		echo '<p>';
		printf(
			esc_html(
				/* translators: %d: número de correos no enviados. */
				_n(
					'Se ha dejado de enviar %d correo.',
					'Se han dejado de enviar %d correos.',
					$total,
					'pw-calendario'
				)
			),
			(int) $total
		);
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=pwcal-settings#email-settings' ) ) . '">';
		echo esc_html__( 'Ver los ajustes de correo', 'pw-calendario' );
		echo '</a></p>';
	}

	if ( 'dominio' === $estado['motivo'] ) {
		echo '<p>' . esc_html__( 'Se reanudarán solos cuando el sitio responda en el dominio autorizado. No hay que acordarse de nada.', 'pw-calendario' ) . '</p>';
	}

	echo '</div>';
}
add_action( 'admin_notices', 'pwcal_aviso_envios_detenidos' );
