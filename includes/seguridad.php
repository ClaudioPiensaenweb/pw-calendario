<?php
/**
 * Pw Calendario — Núcleo de seguridad
 *
 * Centraliza la verificación de nonces (CSRF), la comprobación de permisos
 * y el saneado de datos de entrada. Todo punto de entrada del plugin
 * (AJAX, formularios, exportaciones) debe pasar por estas funciones.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre de la acción del nonce para las peticiones AJAX del front-end.
 */
const PWCAL_NONCE_FRONT = 'pwcal_nonce_front';

/**
 * Nombre de la acción del nonce para las peticiones AJAX del escritorio.
 */
const PWCAL_NONCE_ADMIN = 'pwcal_nonce_admin';

/**
 * Verifica el nonce de una petición AJAX y corta la ejecución si no es válido.
 *
 * Se comprueba tanto en `nonce` como en `security` para admitir el nombre de
 * campo que ya usaban los formularios de acceso y de contraseña olvidada.
 *
 * @param string $accion Acción del nonce esperada.
 * @return void Termina la petición con un 403 si la verificación falla.
 */
function pwcal_verificar_nonce_ajax( $accion ) {

	$nonce = '';

	if ( isset( $_REQUEST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
	} elseif ( isset( $_REQUEST['security'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['security'] ) );
	}

	if ( ! $nonce || ! wp_verify_nonce( $nonce, $accion ) ) {
		wp_send_json_error(
			array( 'message' => __( 'La comprobación de seguridad ha fallado. Recarga la página e inténtalo de nuevo.', 'pw-calendario' ) ),
			403
		);
	}
}

/**
 * Verifica el nonce y el permiso de una petición AJAX del escritorio.
 *
 * @param string $capacidad Capacidad requerida.
 * @return void Termina la petición si falla cualquiera de las dos comprobaciones.
 */
function pwcal_verificar_ajax_admin( $capacidad = 'edit_booked_appointments' ) {

	pwcal_verificar_nonce_ajax( PWCAL_NONCE_ADMIN );

	if ( ! current_user_can( $capacidad ) ) {
		wp_send_json_error(
			array( 'message' => __( 'No tienes permisos suficientes para realizar esta acción.', 'pw-calendario' ) ),
			403
		);
	}
}

/**
 * Verifica el nonce de una petición AJAX pública del front-end.
 *
 * @return void
 */
function pwcal_verificar_ajax_front() {
	pwcal_verificar_nonce_ajax( PWCAL_NONCE_FRONT );
}

/**
 * Comprueba que un ID corresponde realmente a una cita del plugin.
 *
 * Evita que un ID arbitrario acabe en `wp_delete_post()` o en
 * `wp_update_post()` y afecte a entradas, páginas o adjuntos ajenos
 * al plugin.
 *
 * @param mixed $id_cita ID a validar.
 * @return int|false ID saneado si es una cita válida, false en caso contrario.
 */
function pwcal_validar_id_cita( $id_cita ) {

	$id_cita = absint( $id_cita );

	if ( ! $id_cita ) {
		return false;
	}

	$publicacion = get_post( $id_cita );

	if ( ! $publicacion || 'booked_appointments' !== $publicacion->post_type ) {
		return false;
	}

	return $id_cita;
}

/**
 * Comprueba si el usuario actual puede gestionar una cita concreta.
 *
 * Un gestor con la capacidad `edit_booked_appointments` puede gestionar
 * cualquier cita. Un usuario normal solo la suya.
 *
 * @param int $id_cita ID de la cita, ya validado.
 * @return bool
 */
function pwcal_puede_gestionar_cita( $id_cita ) {

	if ( current_user_can( 'edit_booked_appointments' ) ) {
		return true;
	}

	$publicacion = get_post( $id_cita );

	if ( ! $publicacion ) {
		return false;
	}

	$usuario_actual = get_current_user_id();

	if ( ! $usuario_actual ) {
		return false;
	}

	return (int) $publicacion->post_author === $usuario_actual;
}

/**
 * Recupera un valor de texto de $_POST saneado.
 *
 * @param string $clave           Clave del array.
 * @param string $predeterminado  Valor por defecto.
 * @return string
 */
function pwcal_post_texto( $clave, $predeterminado = '' ) {

	if ( ! isset( $_POST[ $clave ] ) || is_array( $_POST[ $clave ] ) ) {
		return $predeterminado;
	}

	return sanitize_text_field( wp_unslash( $_POST[ $clave ] ) );
}

/**
 * Recupera un entero positivo de $_POST.
 *
 * @param string $clave          Clave del array.
 * @param int    $predeterminado Valor por defecto.
 * @return int
 */
function pwcal_post_entero( $clave, $predeterminado = 0 ) {

	if ( ! isset( $_POST[ $clave ] ) || is_array( $_POST[ $clave ] ) ) {
		return $predeterminado;
	}

	return absint( wp_unslash( $_POST[ $clave ] ) );
}

/**
 * Recupera una fecha de $_POST normalizada a Y-m-d.
 *
 * Rechaza cualquier cadena que no sea una fecha real para que no llegue
 * a `strtotime()` ni se use como clave de un array de opciones.
 *
 * @param string $clave Clave del array.
 * @return string Fecha en formato Y-m-d, o cadena vacía si no es válida.
 */
function pwcal_post_fecha( $clave ) {

	$valor = pwcal_post_texto( $clave );

	if ( ! $valor ) {
		return '';
	}

	// Formato compacto Ymd usado por el listado de citas.
	if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $valor, $partes ) ) {
		$valor = $partes[1] . '-' . $partes[2] . '-' . $partes[3];
	}

	$marca_tiempo = strtotime( $valor );

	if ( false === $marca_tiempo ) {
		return '';
	}

	return gmdate( 'Y-m-d', $marca_tiempo );
}

/**
 * Recupera un intervalo horario de $_POST validando su formato.
 *
 * Los intervalos siempre tienen la forma `HHMM-HHMM` (por ejemplo
 * `0900-1000`). Se validan porque se usan como claves de arrays de
 * opciones y como parte de metadatos.
 *
 * @param string $clave Clave del array.
 * @return string Intervalo válido o cadena vacía.
 */
function pwcal_post_intervalo( $clave ) {

	$valor = pwcal_post_texto( $clave );

	if ( ! preg_match( '/^\d{4}-\d{4}$/', $valor ) ) {
		return '';
	}

	return $valor;
}

/**
 * Recupera un ID de calendario de $_POST validando que el término existe.
 *
 * @param string $clave Clave del array.
 * @return int|false ID del término, o false si no hay calendario o no existe.
 */
function pwcal_post_calendario( $clave = 'calendar_id' ) {

	$id_calendario = pwcal_post_entero( $clave );

	if ( ! $id_calendario ) {
		return false;
	}

	$termino = get_term( $id_calendario, 'booked_custom_calendars' );

	if ( ! $termino || is_wp_error( $termino ) ) {
		return false;
	}

	return $id_calendario;
}

/**
 * Sanea el valor de un campo personalizado enviado por el usuario.
 *
 * Los campos personalizados admiten varios valores (casillas de
 * verificación) y su contenido se acaba mostrando en el escritorio, así
 * que se limpia de HTML antes de guardarlo.
 *
 * @param mixed $valor Valor bruto procedente de $_POST.
 * @return string Texto plano seguro.
 */
function pwcal_sanear_campo_personalizado( $valor ) {

	$valor = wp_unslash( $valor );

	if ( is_array( $valor ) ) {
		$valor = array_map( 'sanitize_text_field', array_map( 'strval', $valor ) );
		return implode( ', ', $valor );
	}

	// `sanitize_textarea_field` conserva los saltos de línea de las áreas de texto.
	return sanitize_textarea_field( (string) $valor );
}

/**
 * Sanea de forma recursiva un valor que puede ser array o escalar.
 *
 * @param mixed $valor Valor a limpiar.
 * @return mixed Estructura equivalente con todos los escalares saneados.
 */
function pwcal_sanear_recursivo( $valor ) {

	if ( is_array( $valor ) ) {

		$limpio = array();

		foreach ( $valor as $clave => $elemento ) {
			// Las claves también se limpian: acaban en nombres de campo y
			// en claves de arrays de opciones.
			$limpio[ sanitize_text_field( (string) $clave ) ] = pwcal_sanear_recursivo( $elemento );
		}

		return $limpio;
	}

	if ( is_bool( $valor ) || is_int( $valor ) || is_float( $valor ) || null === $valor ) {
		return $valor;
	}

	return sanitize_text_field( (string) $valor );
}

/**
 * Descodifica un array JSON enviado por POST y lo sanea.
 *
 * El escritorio envía las franjas horarias y los campos personalizados
 * como JSON. Se descodifica con `wp_unslash` (no con `stripslashes`, que
 * no tiene en cuenta las comillas mágicas de WordPress) y se limpia
 * recursivamente antes de guardarlo.
 *
 * @param string $clave           Clave del array $_POST.
 * @param mixed  $predeterminado  Valor si no hay dato o el JSON no es válido.
 * @return mixed
 */
function pwcal_post_json( $clave, $predeterminado = array() ) {

	if ( ! isset( $_POST[ $clave ] ) || is_array( $_POST[ $clave ] ) ) {
		return $predeterminado;
	}

	$bruto = wp_unslash( (string) $_POST[ $clave ] );

	if ( '' === $bruto ) {
		return $predeterminado;
	}

	$datos = json_decode( $bruto, true );

	if ( null === $datos && JSON_ERROR_NONE !== json_last_error() ) {
		return $predeterminado;
	}

	return pwcal_sanear_recursivo( $datos );
}

/**
 * Recupera un array de $_POST saneado recursivamente.
 *
 * @param string $clave          Clave del array.
 * @param array  $predeterminado Valor por defecto.
 * @return array
 */
function pwcal_post_array( $clave, $predeterminado = array() ) {

	if ( ! isset( $_POST[ $clave ] ) || ! is_array( $_POST[ $clave ] ) ) {
		return $predeterminado;
	}

	return pwcal_sanear_recursivo( wp_unslash( $_POST[ $clave ] ) );
}

/**
 * Recupera un valor de $_POST restringido a una lista de valores admitidos.
 *
 * Se usa para los desplegables y los estados: evita que llegue a una
 * consulta un valor que no está previsto.
 *
 * @param string $clave           Clave del array.
 * @param array  $permitidos      Valores admitidos.
 * @param string $predeterminado  Valor por defecto si no coincide ninguno.
 * @return string
 */
function pwcal_post_lista( $clave, array $permitidos, $predeterminado = '' ) {

	$valor = pwcal_post_texto( $clave );

	return in_array( $valor, $permitidos, true ) ? $valor : $predeterminado;
}

/**
 * Escapa un valor para un campo iCalendar (RFC 5545).
 *
 * Sin esto, un cliente que reserve con un nombre que contenga un salto de
 * linea puede inyectar campos y eventos arbitrarios en el archivo .ics.
 *
 * @param string $valor Texto a escapar.
 * @return string
 */
function pwcal_escapar_ics( $valor ) {

	$valor = (string) $valor;

	$barra = chr( 92 );
	$cr    = chr( 13 );
	$lf    = chr( 10 );

	// Primero la barra invertida, para no volver a escapar lo que se
	// anade en los pasos siguientes.
	$valor = str_replace( $barra, $barra . $barra, $valor );

	// Los saltos de linea reales pasan a la secuencia literal que exige
	// el formato. Se trata primero CRLF y luego CR y LF sueltos.
	$valor = str_replace( $cr . $lf, $barra . 'n', $valor );
	$valor = str_replace( array( $cr, $lf ), $barra . 'n', $valor );

	// La coma y el punto y coma son separadores de campo en iCalendar.
	$valor = str_replace(
		array( ',', ';' ),
		array( $barra . ',', $barra . ';' ),
		$valor
	);

	return $valor;
}

/**
 * Devuelve el HTML permitido en los contenidos de los correos.
 *
 * Las plantillas de correo las edita un gestor, así que se admite
 * formato básico pero no scripts ni atributos de evento.
 *
 * @return array Lista de etiquetas para `wp_kses()`.
 */
function pwcal_html_permitido_correo() {

	return array(
		'a'      => array(
			'href'   => true,
			'title'  => true,
			'target' => true,
		),
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'b'      => array(),
		'i'      => array(),
		'p'      => array( 'style' => true ),
		'div'    => array( 'style' => true, 'class' => true ),
		'span'   => array( 'style' => true, 'class' => true ),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'h1'     => array(),
		'h2'     => array(),
		'h3'     => array(),
		'h4'     => array(),
		'table'  => array( 'style' => true, 'width' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true ),
		'thead'  => array(),
		'tbody'  => array(),
		'tr'     => array( 'style' => true ),
		'td'     => array( 'style' => true, 'colspan' => true, 'align' => true, 'valign' => true, 'width' => true ),
		'th'     => array( 'style' => true, 'colspan' => true, 'align' => true ),
		'img'    => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true ),
		'hr'     => array(),
	);
}

/**
 * Sanea una opción de texto del plugin.
 *
 * Admite tanto cadenas como arrays (casillas de verificación de los
 * ajustes). Se registra como `sanitize_callback` en `register_setting()`.
 *
 * @param mixed $valor Valor enviado.
 * @return mixed
 */
function pwcal_sanear_opcion_texto( $valor ) {

	if ( is_array( $valor ) ) {
		return array_map( 'sanitize_text_field', array_map( 'strval', $valor ) );
	}

	return sanitize_text_field( (string) $valor );
}

/**
 * Sanea una opción del plugin que admite HTML básico.
 *
 * Se usa en los contenidos de las plantillas de correo.
 *
 * @param mixed $valor Valor enviado.
 * @return string
 */
function pwcal_sanear_opcion_html( $valor ) {

	if ( is_array( $valor ) ) {
		return '';
	}

	return wp_kses( (string) $valor, pwcal_html_permitido_correo() );
}
