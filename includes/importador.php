<?php
/**
 * Importación de citas desde otra instalación.
 *
 * Lee el archivo que genera el exportador y crea las citas en este sitio.
 *
 * Tres decisiones de fondo, cada una obligada por algo que se comprobó en
 * el código del complemento de WooCommerce antes de escribir esto:
 *
 * 1. **El identificador del pedido no se copia tal cual.** El pedido de la
 *    web de origen no existe aquí, y `Booked_WC_Appointment_Payment_Status`
 *    hace `$this->order_obj->order->get_status()` sin comprobar nada. Con
 *    un pedido inexistente, `new WC_Order()` lanza una excepción que nadie
 *    captura: error fatal en cualquier pantalla que muestre el estado de
 *    pago. Se guarda `'manual'`, que ese mismo código trata como pagado y
 *    sin buscar el pedido, y el número original queda en
 *    `_pwcal_origen_pedido` como registro.
 *
 * 2. **El marcador del producto se retira del HTML.** Los productos de la
 *    web de origen tampoco existen aquí. Dejar el marcador haría que
 *    `get_products_data()` llamara a `wc_get_product()` para nada y
 *    avisara de propiedades inexistentes. El texto visible (la
 *    experiencia y su importe) se conserva intacto; los identificadores
 *    pasan a `_pwcal_origen_productos`.
 *
 * 3. **Los recordatorios se marcan como enviados.** El cron solo mira la
 *    ventana entre ahora y el margen configurado, y las citas importadas
 *    son pasadas, así que no puede dispararse. Marcarlas de todas formas
 *    cuesta cero y hace imposible que una fecha mal leída provoque un
 *    envío masivo de correos a clientes de hace cinco años.
 *
 * La importación es repetible: cada cita guarda su identificador de origen
 * en `_pwcal_origen_id`, y si ya existe no se vuelve a crear.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metadato con el identificador de la cita en la web de origen.
 */
const PWCAL_META_ORIGEN = '_pwcal_origen_id';

/**
 * Marca de que la cita se termino de importar.
 *
 * Se escribe en ultimo lugar. Si una importacion se interrumpe a medias
 * (una peticion que se corta, un tiempo de ejecucion agotado), la cita
 * queda con procedencia pero sin esta marca, y al relanzar se sustituye en
 * lugar de darla por buena.
 */
const PWCAL_META_COMPLETA = '_pwcal_importada_ok';

/**
 * Metadatos que no se copian.
 *
 * Los prefijos cubren la basura que dejan los maquetadores y los plugins
 * de SEO en las entradas: en el archivo de origen había 396 citas con
 * `_vc_post_settings` de Visual Composer.
 */
function pwcal_prefijos_meta_excluidos() {

	return apply_filters(
		'pwcal_prefijos_meta_excluidos',
		array( '_vc_', '_wpb_', '_elementor', '_yoast_', '_edit_', '_oembed_', '_thumbnail_id' )
	);
}

/**
 * Metadatos que se gestionan aparte y no se copian directamente.
 */
function pwcal_meta_gestionados() {

	return array(
		'_booked_wc_appointment_order_id',
		'_appointment_user_reminder_sent',
		'_appointment_admin_reminder_sent',
		PWCAL_META_ORIGEN,
		PWCAL_META_COMPLETA,
		PWCAL_META_PERSONAS,
	);
}

/**
 * Indica si un metadato debe copiarse.
 *
 * @param string $clave Nombre del metadato.
 * @return bool
 */
function pwcal_meta_se_copia( $clave ) {

	$clave = (string) $clave;

	if ( in_array( $clave, pwcal_meta_gestionados(), true ) ) {
		return false;
	}

	foreach ( pwcal_prefijos_meta_excluidos() as $prefijo ) {
		if ( 0 === strpos( $clave, $prefijo ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Retira los marcadores de producto de un metadato de campos.
 *
 * Devuelve el HTML sin los comentarios y la lista de identificadores que
 * llevaban dentro.
 *
 * @param string $meta Contenido de `_cf_meta_value`.
 * @return array {
 *     @type string $html      HTML sin marcadores.
 *     @type array  $productos Identificadores encontrados.
 * }
 */
function pwcal_retirar_marcadores( $meta ) {

	$resultado = array(
		'html'      => is_string( $meta ) ? $meta : '',
		'productos' => array(),
	);

	if ( '' === $resultado['html'] ) {
		return $resultado;
	}

	$coincidencias = array();

	if ( preg_match_all( '~<!--\s([^\s]+)\s-->~mi', $resultado['html'], $coincidencias ) ) {

		foreach ( $coincidencias[1] as $cadena ) {

			$info = array();

			foreach ( explode( '|', $cadena ) as $trozo ) {
				$partes = explode( '::', $trozo );
				if ( isset( $partes[1] ) ) {
					$info[ $partes[0] ] = $partes[1];
				}
			}

			if ( ! empty( $info ) ) {
				$resultado['productos'][] = $info;
			}
		}

		$resultado['html'] = preg_replace( '~<!--\s[^\s]+\s-->~mi', '', $resultado['html'] );
	}

	return $resultado;
}

/**
 * Devuelve el ID de la cita ya importada que corresponde a un origen.
 *
 * @param int $id_origen Identificador en la web de origen.
 * @return int 0 si no existe.
 */
function pwcal_cita_ya_importada( $id_origen ) {

	$id_origen = (int) $id_origen;

	if ( $id_origen < 1 ) {
		return 0;
	}

	$encontradas = get_posts(
		array(
			'post_type'        => 'booked_appointments',
			'post_status'      => 'any',
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => PWCAL_META_ORIGEN,
			'meta_value'       => (string) $id_origen,
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	return ! empty( $encontradas ) ? (int) $encontradas[0] : 0;
}

/**
 * Indica si una cita importada se termino de importar.
 *
 * @param int $id_cita ID de la cita en este sitio.
 * @return bool
 */
function pwcal_importacion_completa( $id_cita ) {

	return (bool) get_post_meta( (int) $id_cita, PWCAL_META_COMPLETA, true );
}

/**
 * Resuelve a qué calendario de este sitio corresponde uno de origen.
 *
 * @param array $calendario Datos del calendario de origen.
 * @param array $mapa       Correspondencia explícita: id_origen => id_aquí.
 * @param bool  $crear      Si se permite crear el calendario que falte.
 * @return int 0 si no hay calendario (calendario por defecto).
 */
function pwcal_resolver_calendario( $calendario, $mapa, $crear ) {

	if ( empty( $calendario['term_id'] ) ) {
		return 0;
	}

	$id_origen = (int) $calendario['term_id'];

	// 1) Correspondencia indicada a mano.
	if ( isset( $mapa[ $id_origen ] ) ) {
		return (int) $mapa[ $id_origen ];
	}

	if ( isset( $mapa[ (string) $id_origen ] ) ) {
		return (int) $mapa[ (string) $id_origen ];
	}

	$nombre = isset( $calendario['name'] ) ? (string) $calendario['name'] : '';

	if ( '' === $nombre ) {
		return 0;
	}

	// 2) Un calendario que ya se llame igual.
	$existente = get_term_by( 'name', $nombre, 'booked_custom_calendars' );

	if ( $existente && ! is_wp_error( $existente ) ) {
		return (int) $existente->term_id;
	}

	// 3) Crearlo, si se ha pedido.
	if ( ! $crear ) {
		return 0;
	}

	$nuevo = wp_insert_term( $nombre, 'booked_custom_calendars' );

	if ( is_wp_error( $nuevo ) ) {
		return 0;
	}

	return (int) $nuevo['term_id'];
}

/**
 * Opciones que SÍ se importan.
 *
 * Deliberadamente cortas: solo el contenido que escribió el cliente
 * (plantillas de correo, margen de los recordatorios, remitente, logo).
 *
 * Lo que queda fuera es tan importante como lo que entra. `booked_defaults`,
 * `booked_custom_fields_*`, `booked_custom_timeslots_encoded`,
 * `booked_disabled_timeslots` y `taxonomy_*` describen la estructura de
 * horarios y campos del sitio de DESTINO, que normalmente ya está
 * configurada y suele ser distinta de la de origen. Importarlas en bloque
 * se llevaría por delante esa configuración.
 *
 * @return array
 */
function pwcal_opciones_importables() {

	return apply_filters(
		'pwcal_opciones_importables',
		array(
			// Correos al cliente.
			'booked_appt_confirmation_email_subject',
			'booked_appt_confirmation_email_content',
			'booked_approval_email_subject',
			'booked_approval_email_content',
			'booked_cancellation_email_subject',
			'booked_cancellation_email_content',
			'booked_registration_email_subject',
			'booked_registration_email_content',
			'booked_reminder_email_subject',
			'booked_reminder_email',

			// Correos al gestor.
			'booked_admin_appointment_email_subject',
			'booked_admin_appointment_email_content',
			'booked_admin_cancellation_email_subject',
			'booked_admin_cancellation_email_content',
			'booked_admin_reminder_email_subject',
			'booked_admin_reminder_email',

			// Cuándo se avisa.
			'booked_reminder_buffer',
			'booked_admin_reminder_buffer',

			// Remitente y aspecto.
			'booked_default_email_user',
			'booked_email_logo',

			/*
			 * Ajustes de comportamiento. Son decisiones del cliente, no
			 * estructura: cuantas plazas, si se reserva como invitado, si
			 * las citas se aprueban solas, los margenes, los colores. Se
			 * traen con el resto porque perderlos obliga a reconfigurar el
			 * sitio a mano y a adivinar lo que habia.
			 */
			'booked_booking_type',
			'booked_new_appointment_default',
			'booked_require_guest_email_address',
			'booked_registration_name_requirements',
			'booked_appointment_buffer',
			'booked_appointment_limit',
			'booked_cancellation_buffer',
			'booked_dont_allow_user_cancellations',
			'booked_timeslot_intervals',
			'booked_hide_default_calendar',
			'booked_hide_end_times',
			'booked_hide_weekends',
			'booked_hide_available_timeslots',
			'booked_hide_unavailable_timeslots',
			'booked_hide_google_link',
			'booked_show_only_titles',
			'booked_public_appointments',
			'booked_prevent_appointments_before',
			'booked_prevent_appointments_after',
			'booked_light_color',
			'booked_dark_color',
			'booked_button_color',
			'booked_custom_login_message',
		)
	);
}

/**
 * Opciones de horario, que solo se traen si se piden expresamente.
 *
 * Sustituyen el calendario del sitio de destino: las franjas semanales,
 * los rangos de fechas con sus cierres y las franjas apagadas día a día.
 * Si el destino ya tiene horarios configurados, esto los reemplaza, y de
 * ahí que no entre en la importación normal.
 *
 * Los identificadores de calendario van dentro de estos valores, así que
 * hay que traducirlos: en el origen eran 307 y 309, y aquí son otros.
 *
 * @return array
 */
function pwcal_opciones_horarios() {

	return apply_filters(
		'pwcal_opciones_horarios',
		array(
			'booked_custom_timeslots_encoded',
			'booked_disabled_timeslots',
		)
	);
}

/**
 * Traduce los identificadores de calendario de la estructura de rangos.
 *
 * @param string $codificado JSON de `booked_custom_timeslots_encoded`.
 * @param array  $mapa       id_origen => id_aquí.
 * @return string JSON con los identificadores traducidos.
 */
function pwcal_traducir_rangos( $codificado, $mapa ) {

	if ( ! is_string( $codificado ) || '' === $codificado ) {
		return $codificado;
	}

	$datos = json_decode( $codificado, true );

	if ( ! is_array( $datos ) || empty( $datos['booked_custom_calendar_id'] ) ) {
		return $codificado;
	}

	if ( ! is_array( $datos['booked_custom_calendar_id'] ) ) {
		return $codificado;
	}

	foreach ( $datos['booked_custom_calendar_id'] as $indice => $origen ) {

		$origen = (string) $origen;

		// Vacío es el calendario por defecto: se queda como está.
		if ( '' === $origen || '0' === $origen ) {
			continue;
		}

		if ( isset( $mapa[ $origen ] ) ) {
			$datos['booked_custom_calendar_id'][ $indice ] = (string) $mapa[ $origen ];
		} elseif ( isset( $mapa[ (int) $origen ] ) ) {
			$datos['booked_custom_calendar_id'][ $indice ] = (string) $mapa[ (int) $origen ];
		}
	}

	return wp_json_encode( $datos );
}

/**
 * Traduce los identificadores de calendario de las franjas apagadas.
 *
 * La estructura es `array( id_calendario => array( fecha => franjas ) )`.
 *
 * @param mixed $valor Valor de `booked_disabled_timeslots`.
 * @param array $mapa  id_origen => id_aquí.
 * @return mixed
 */
function pwcal_traducir_apagadas( $valor, $mapa ) {

	if ( ! is_array( $valor ) ) {
		return $valor;
	}

	$salida = array();

	foreach ( $valor as $origen => $fechas ) {

		$destino = (string) $origen;

		if ( '0' !== $destino && '' !== $destino ) {
			if ( isset( $mapa[ $destino ] ) ) {
				$destino = (string) $mapa[ $destino ];
			} elseif ( isset( $mapa[ (int) $destino ] ) ) {
				$destino = (string) $mapa[ (int) $destino ];
			}
		}

		// Un calendario sin correspondencia se descarta: apagar franjas de
		// un calendario que aquí no existe no sirve para nada.
		if ( '0' !== $destino && '' !== $destino && ! isset( $mapa[ $origen ] ) && ! isset( $mapa[ (int) $origen ] ) ) {
			continue;
		}

		$salida[ $destino ] = $fechas;
	}

	return $salida;
}

/**
 * Importa los horarios del sitio de origen.
 *
 * @param array $datos   Contenido del archivo.
 * @param array $mapa    Correspondencia de calendarios.
 * @param bool  $en_seco No escribir.
 * @return array Informe.
 */
function pwcal_importar_horarios( $datos, $mapa, $en_seco = true ) {

	$informe = array(
		'semanales'  => array(),
		'rangos'     => 0,
		'apagadas'   => 0,
		'avisos'     => array(),
	);

	if ( empty( $datos['opciones'] ) || ! is_array( $datos['opciones'] ) ) {
		return $informe;
	}

	$ops = $datos['opciones'];

	// --- Franjas semanales, una opción por calendario -----------------
	foreach ( $ops as $nombre => $valor ) {

		if ( 'booked_defaults' === $nombre ) {

			$informe['semanales']['booked_defaults'] = is_array( $valor ) ? count( $valor ) : 0;

			if ( ! $en_seco ) {
				update_option( 'booked_defaults', $valor );
			}

			continue;
		}

		if ( 0 !== strpos( $nombre, 'booked_defaults_' ) ) {
			continue;
		}

		$origen = substr( $nombre, strlen( 'booked_defaults_' ) );

		if ( ! isset( $mapa[ $origen ] ) && ! isset( $mapa[ (int) $origen ] ) ) {
			$informe['avisos'][] = sprintf(
				/* translators: %s: identificador del calendario de origen. */
				__( 'Las franjas del calendario %s no se traen: ese calendario no tiene equivalente aquí.', 'pw-calendario' ),
				$origen
			);
			continue;
		}

		$destino = isset( $mapa[ $origen ] ) ? $mapa[ $origen ] : $mapa[ (int) $origen ];

		if ( ! $destino ) {
			continue;
		}

		$clave_destino = 'booked_defaults_' . (int) $destino;

		$informe['semanales'][ $clave_destino ] = is_array( $valor ) ? count( $valor ) : 0;

		if ( ! $en_seco ) {
			update_option( $clave_destino, $valor );
		}
	}

	// --- Rangos de fechas ---------------------------------------------
	if ( ! empty( $ops['booked_custom_timeslots_encoded'] ) ) {

		$traducido = pwcal_traducir_rangos( $ops['booked_custom_timeslots_encoded'], $mapa );
		$decodificado = json_decode( $traducido, true );

		$informe['rangos'] = ( is_array( $decodificado ) && ! empty( $decodificado['booked_custom_start_date'] ) )
			? count( $decodificado['booked_custom_start_date'] )
			: 0;

		if ( ! $en_seco ) {
			update_option( 'booked_custom_timeslots_encoded', $traducido );
		}
	}

	// --- Franjas apagadas día a día -----------------------------------
	if ( ! empty( $ops['booked_disabled_timeslots'] ) ) {

		$traducidas = pwcal_traducir_apagadas( $ops['booked_disabled_timeslots'], $mapa );

		$total = 0;
		if ( is_array( $traducidas ) ) {
			foreach ( $traducidas as $fechas ) {
				if ( is_array( $fechas ) ) {
					$total += count( $fechas );
				}
			}
		}

		$informe['apagadas'] = $total;

		if ( ! $en_seco ) {
			update_option( 'booked_disabled_timeslots', $traducidas );
		}
	}

	return $informe;
}

/**
 * Importa las opciones permitidas de una exportación.
 *
 * @param array $datos   Contenido del archivo.
 * @param bool  $en_seco No escribir.
 * @return array Informe.
 */
function pwcal_importar_opciones( $datos, $en_seco = true ) {

	$informe = array(
		'importadas' => array(),
		'omitidas'   => array(),
		'ya_puestas' => array(),
		'avisos'     => array(),
	);

	if ( empty( $datos['opciones'] ) || ! is_array( $datos['opciones'] ) ) {
		return $informe;
	}

	$permitidas = pwcal_opciones_importables();

	foreach ( $datos['opciones'] as $nombre => $valor ) {

		if ( ! in_array( $nombre, $permitidas, true ) ) {
			$informe['omitidas'][] = $nombre;
			continue;
		}

		if ( '' === $valor || null === $valor ) {
			continue;
		}

		// No se pisa nada que ya tenga contenido en el destino.
		$actual = get_option( $nombre, '' );

		if ( '' !== $actual && null !== $actual && false !== $actual ) {
			$informe['ya_puestas'][] = $nombre;
			continue;
		}

		$informe['importadas'][] = $nombre;

		if ( ! $en_seco ) {
			update_option( $nombre, $valor );
		}
	}

	/*
	 * El logo apunta al dominio de origen. Se importa igual, porque tener
	 * la plantilla completa vale más que el detalle, pero conviene decirlo.
	 */
	if ( in_array( 'booked_email_logo', $informe['importadas'], true ) ) {
		$informe['avisos'][] = __( 'El logo de los correos apunta al dominio de origen: conviene volver a subirlo.', 'pw-calendario' );
	}

	return $informe;
}

/**
 * Comprueba que el archivo tiene la forma esperada.
 *
 * @param mixed $datos Contenido decodificado del archivo.
 * @return true|WP_Error
 */
function pwcal_validar_importacion( $datos ) {

	if ( ! is_array( $datos ) ) {
		return new WP_Error(
			'pwcal_importar_formato',
			__( 'El archivo no contiene datos válidos.', 'pw-calendario' )
		);
	}

	if ( empty( $datos['formato'] ) || 'pwcal-exportacion' !== $datos['formato'] ) {
		return new WP_Error(
			'pwcal_importar_formato',
			__( 'El archivo no es una exportación de Pw Calendario.', 'pw-calendario' )
		);
	}

	if ( ! isset( $datos['citas'] ) || ! is_array( $datos['citas'] ) ) {
		return new WP_Error(
			'pwcal_importar_sin_citas',
			__( 'El archivo no contiene ninguna cita.', 'pw-calendario' )
		);
	}

	return true;
}

/**
 * Importa las citas de una exportación.
 *
 * @param array $datos    Contenido decodificado del archivo.
 * @param array $opciones {
 *     @type bool  $en_seco           No escribir nada, solo informar.
 *     @type array $mapa_calendarios  id_origen => id_de_aquí.
 *     @type bool  $crear_calendarios Crear los que no existan.
 *     @type int   $desde             Índice de la primera cita a tratar.
 *     @type int   $cuantas           Cuántas tratar (0 = todas).
 * }
 * @return array|WP_Error Informe de lo hecho.
 */
function pwcal_importar( $datos, $opciones = array() ) {

	$valido = pwcal_validar_importacion( $datos );

	if ( is_wp_error( $valido ) ) {
		return $valido;
	}

	$opciones = wp_parse_args(
		$opciones,
		array(
			'en_seco'           => true,
			'mapa_calendarios'  => array(),
			'crear_calendarios' => true,
			'desde'             => 0,
			'cuantas'           => 0,
			'importar_opciones' => false,
			'importar_horarios' => false,
		)
	);

	$en_seco = ! empty( $opciones['en_seco'] );

	$informe = array(
		'en_seco'      => $en_seco,
		'origen'       => isset( $datos['origen'] ) ? $datos['origen'] : array(),
		'total'        => count( $datos['citas'] ),
		'creadas'      => 0,
		'omitidas'     => 0,
		'fallidas'     => 0,
		'personas'     => 0,
		'sin_personas' => 0,
		'con_pedido'   => 0,
		'calendarios'  => array(),
		'meta_omitida' => array(),
		'avisos'       => array(),
		'muestra'      => array(),
	);

	// Correspondencia de calendarios.
	$mapa       = is_array( $opciones['mapa_calendarios'] ) ? $opciones['mapa_calendarios'] : array();
	$resueltos  = array();

	if ( ! empty( $datos['calendarios'] ) && is_array( $datos['calendarios'] ) ) {

		foreach ( $datos['calendarios'] as $calendario ) {

			if ( empty( $calendario['term_id'] ) ) {
				continue;
			}

			$id_origen = (int) $calendario['term_id'];

			// En seco no se crea nada: solo se informa de lo que se haría.
			$destino = pwcal_resolver_calendario(
				$calendario,
				$mapa,
				$en_seco ? false : ! empty( $opciones['crear_calendarios'] )
			);

			$resueltos[ $id_origen ] = $destino;

			$informe['calendarios'][] = array
			(
				'origen'         => $id_origen,
				'nombre'         => isset( $calendario['name'] ) ? $calendario['name'] : '',
				'destino'        => $destino,
				'se_creara'      => ( 0 === $destino && ! isset( $mapa[ $id_origen ] ) ),
			);
		}
	}

	/*
	 * Opciones. Solo las de la lista, y solo si en el destino están
	 * vacías: nunca se pisa lo que ya haya configurado aquí.
	 */
	if ( ! empty( $opciones['importar_opciones'] ) ) {
		$informe['opciones'] = pwcal_importar_opciones( $datos, $en_seco );
	}

	/*
	 * Horarios. Aparte y solo si se piden, porque SUSTITUYEN el calendario
	 * de este sitio: franjas semanales, rangos con sus cierres y franjas
	 * apagadas día a día.
	 */
	if ( ! empty( $opciones['importar_horarios'] ) ) {
		$informe['horarios'] = pwcal_importar_horarios( $datos, $resueltos, $en_seco );
	}

	// Recorte del lote.
	$citas = $datos['citas'];

	if ( $opciones['desde'] > 0 || $opciones['cuantas'] > 0 ) {
		$citas = array_slice(
			$citas,
			(int) $opciones['desde'],
			$opciones['cuantas'] > 0 ? (int) $opciones['cuantas'] : null
		);
	}

	foreach ( $citas as $cita ) {

		$resultado = pwcal_importar_una_cita( $cita, $resueltos, $en_seco );

		if ( is_wp_error( $resultado ) ) {
			$informe['fallidas']++;
			if ( count( $informe['avisos'] ) < 20 ) {
				$informe['avisos'][] = $resultado->get_error_message();
			}
			continue;
		}

		if ( 'omitida' === $resultado['estado'] ) {
			$informe['omitidas']++;
			continue;
		}

		$informe['creadas']++;
		$informe['personas'] += $resultado['personas'];

		if ( $resultado['personas'] < 1 ) {
			$informe['sin_personas']++;
		}

		if ( $resultado['con_pedido'] ) {
			$informe['con_pedido']++;
		}

		foreach ( $resultado['meta_omitida'] as $clave ) {
			if ( ! isset( $informe['meta_omitida'][ $clave ] ) ) {
				$informe['meta_omitida'][ $clave ] = 0;
			}
			$informe['meta_omitida'][ $clave ]++;
		}

		if ( count( $informe['muestra'] ) < 5 ) {
			$informe['muestra'][] = $resultado;
		}
	}

	return $informe;
}

/**
 * Importa una sola cita.
 *
 * @param array $cita      Datos de la cita en el archivo.
 * @param array $resueltos Calendarios ya resueltos: id_origen => id_aquí.
 * @param bool  $en_seco   No escribir nada.
 * @return array|WP_Error
 */
function pwcal_importar_una_cita( $cita, $resueltos, $en_seco ) {

	if ( ! is_array( $cita ) || empty( $cita['ID'] ) ) {
		return new WP_Error( 'pwcal_cita_sin_id', __( 'Hay una cita sin identificador en el archivo.', 'pw-calendario' ) );
	}

	$id_origen = (int) $cita['ID'];
	$meta      = ( isset( $cita['meta'] ) && is_array( $cita['meta'] ) ) ? $cita['meta'] : array();

	// Ya importada: no se toca.
	$existente = pwcal_cita_ya_importada( $id_origen );

	if ( $existente && pwcal_importacion_completa( $existente ) ) {
		return array(
			'estado'       => 'omitida',
			'origen'       => $id_origen,
			'id'           => $existente,
			'personas'     => 0,
			'con_pedido'   => false,
			'meta_omitida' => array(),
		);
	}

	/*
	 * Existe pero sin la marca de completa: viene de una importacion que
	 * se corto a medias. Se retira y se vuelve a crear entera, para no
	 * dejar una cita con la mitad de los datos.
	 */
	if ( $existente && ! $en_seco ) {
		wp_delete_post( $existente, true );
		$existente = 0;
	}

	$marca = isset( $meta['_appointment_timestamp'] ) ? (int) $meta['_appointment_timestamp'] : 0;

	if ( $marca < 1 ) {
		return new WP_Error(
			'pwcal_cita_sin_fecha',
			sprintf(
				/* translators: %d: identificador de la cita en el origen. */
				__( 'La cita %d no tiene fecha y no se puede importar.', 'pw-calendario' ),
				$id_origen
			)
		);
	}

	// Número de personas: lo que detectó el exportador, mínimo 1.
	$personas = 1;

	if ( isset( $cita['analisis']['personas_detectadas'] ) ) {
		$personas = max( 1, (int) $cita['analisis']['personas_detectadas'] );
	}

	// Campos personalizados, sin los marcadores de producto.
	$campos    = isset( $meta['_cf_meta_value'] ) ? $meta['_cf_meta_value'] : '';
	$limpiados = pwcal_retirar_marcadores( is_string( $campos ) ? $campos : '' );

	$pedido_origen = isset( $meta['_booked_wc_appointment_order_id'] )
		? (string) $meta['_booked_wc_appointment_order_id']
		: '';

	$omitida = array();

	if ( $en_seco ) {

		foreach ( $meta as $clave => $valor ) {
			if ( ! pwcal_meta_se_copia( $clave ) ) {
				$omitida[] = $clave;
			}
		}

		return array(
			'estado'       => 'se_creara',
			'origen'       => $id_origen,
			'id'           => 0,
			'titulo'       => isset( $cita['post_title'] ) ? $cita['post_title'] : '',
			'fecha'        => date_i18n( 'Y-m-d H:i', $marca ),
			'intervalo'    => isset( $meta['_appointment_timeslot'] ) ? $meta['_appointment_timeslot'] : '',
			'personas'     => $personas,
			'con_pedido'   => ( '' !== $pedido_origen ),
			'meta_omitida' => $omitida,
		);
	}

	// ------------------------------------------------------------------
	// Creación
	// ------------------------------------------------------------------
	$id_nuevo = wp_insert_post(
		array(
			'post_type'    => 'booked_appointments',
			'post_status'  => isset( $cita['post_status'] ) ? $cita['post_status'] : 'publish',
			'post_title'   => isset( $cita['post_title'] ) ? $cita['post_title'] : '',
			'post_content' => isset( $cita['post_content'] ) ? $cita['post_content'] : '',
			'post_date'    => isset( $cita['post_date'] ) ? $cita['post_date'] : '',
			'post_author'  => 0,
		),
		true
	);

	if ( is_wp_error( $id_nuevo ) ) {
		return $id_nuevo;
	}

	$id_nuevo = (int) $id_nuevo;

	/*
	 * La procedencia, lo primero. Si la peticion se corta a partir de
	 * aqui, la cita queda identificada: al relanzar se reconoce y se
	 * sustituye, y deshacer la importacion se la lleva. Escribirla al
	 * final dejaba citas huerfanas que ni se reconocian ni se podian
	 * deshacer, y que la siguiente pasada duplicaba.
	 */
	update_post_meta( $id_nuevo, PWCAL_META_ORIGEN, (string) $id_origen );

	// Metadatos copiados.
	foreach ( $meta as $clave => $valor ) {

		if ( ! pwcal_meta_se_copia( $clave ) ) {
			$omitida[] = $clave;
			continue;
		}

		if ( '_cf_meta_value' === $clave ) {
			$valor = $limpiados['html'];
		}

		update_post_meta( $id_nuevo, $clave, $valor );
	}

	// Aforo.
	pwcal_guardar_personas( $id_nuevo, $personas );

	if ( ! empty( $limpiados['productos'] ) ) {
		update_post_meta( $id_nuevo, '_pwcal_origen_productos', $limpiados['productos'] );
	}

	if ( isset( $cita['post_author'] ) ) {
		update_post_meta( $id_nuevo, '_pwcal_origen_autor', (string) $cita['post_author'] );
	}

	/*
	 * Estado de pago. Ver la nota de cabecera: copiar el número real
	 * provocaría un error fatal, porque el pedido no existe aquí.
	 */
	if ( '' !== $pedido_origen ) {
		update_post_meta( $id_nuevo, '_booked_wc_appointment_order_id', 'manual' );
		update_post_meta( $id_nuevo, '_pwcal_origen_pedido', $pedido_origen );
	}

	// Recordatorios: nunca para citas importadas.
	update_post_meta( $id_nuevo, '_appointment_user_reminder_sent', true );
	update_post_meta( $id_nuevo, '_appointment_admin_reminder_sent', true );

	// Calendario.
	$calendarios = array();

	if ( ! empty( $cita['calendarios'] ) && is_array( $cita['calendarios'] ) ) {
		foreach ( $cita['calendarios'] as $calendario ) {
			$origen = isset( $calendario['term_id'] ) ? (int) $calendario['term_id'] : 0;
			if ( $origen && ! empty( $resueltos[ $origen ] ) ) {
				$calendarios[] = (int) $resueltos[ $origen ];
			}
		}
	}

	if ( ! empty( $calendarios ) ) {
		wp_set_object_terms( $id_nuevo, $calendarios, 'booked_custom_calendars', false );
	}

	// Y por ultimo, la marca de que no ha quedado nada a medias.
	update_post_meta( $id_nuevo, PWCAL_META_COMPLETA, '1' );

	return array(
		'estado'       => 'creada',
		'origen'       => $id_origen,
		'id'           => $id_nuevo,
		'titulo'       => isset( $cita['post_title'] ) ? $cita['post_title'] : '',
		'fecha'        => date_i18n( 'Y-m-d H:i', $marca ),
		'intervalo'    => isset( $meta['_appointment_timeslot'] ) ? $meta['_appointment_timeslot'] : '',
		'personas'     => $personas,
		'con_pedido'   => ( '' !== $pedido_origen ),
		'meta_omitida' => $omitida,
	);
}

/**
 * Marca como completas las citas importadas que no llevan la marca.
 *
 * Solo hace falta una vez, para las citas que se importaron antes de que
 * existiera `PWCAL_META_COMPLETA`. Sin esto, la primera reejecucion las
 * consideraria a medias y las borraria para recrearlas: el resultado seria
 * el mismo, pero es trabajo y riesgo para nada.
 *
 * @param bool $en_seco No escribir, solo contar.
 * @return array
 */
function pwcal_marcar_importadas_completas( $en_seco = true ) {

	$informe = array(
		'en_seco'    => (bool) $en_seco,
		'candidatas' => 0,
		'marcadas'   => 0,
	);

	$ids = get_posts(
		array(
			'post_type'        => 'booked_appointments',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'no_found_rows'    => true,
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => PWCAL_META_ORIGEN,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => PWCAL_META_COMPLETA,
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	if ( ! is_array( $ids ) ) {
		return $informe;
	}

	$informe['candidatas'] = count( $ids );

	if ( $en_seco ) {
		return $informe;
	}

	foreach ( $ids as $id ) {
		update_post_meta( (int) $id, PWCAL_META_COMPLETA, '1' );
		$informe['marcadas']++;
	}

	return $informe;
}

/**
 * Borra todas las citas importadas.
 *
 * Sirve para deshacer una importación: solo toca las citas que llevan el
 * metadato de procedencia, así que no puede llevarse por delante una cita
 * creada en este sitio.
 *
 * @param bool $en_seco No borrar, solo contar.
 * @return array
 */
function pwcal_deshacer_importacion( $en_seco = true ) {

	$informe = array(
		'en_seco'   => (bool) $en_seco,
		'candidatas' => 0,
		'borradas'  => 0,
	);

	$ids = get_posts(
		array(
			'post_type'        => 'booked_appointments',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'meta_key'         => PWCAL_META_ORIGEN,
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	if ( ! is_array( $ids ) ) {
		return $informe;
	}

	$informe['candidatas'] = count( $ids );

	if ( $en_seco ) {
		return $informe;
	}

	foreach ( $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$informe['borradas']++;
		}
	}

	return $informe;
}
