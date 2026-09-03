<?php
/**
 * Puntos de entrada AJAX del complemento de agentes en front-end.
 *
 * Nota de seguridad: en la versión original estos tres puntos de entrada
 * estaban registrados en `wp_ajax_` sin nonce y sin ninguna comprobación
 * de permisos, así que cualquier usuario identificado —incluido un simple
 * suscriptor— podía:
 *
 * - borrar de forma permanente cualquier entrada del sitio pasando su ID
 *   a `booked_fea_delete_appt` (llamaba a `wp_delete_post( $id, true )`);
 * - publicar cualquier borrador ajeno con `booked_fea_approve_appt`;
 * - leer el correo y el teléfono de cualquier usuario registrado con
 *   `booked_fea_user_info_modal`.
 *
 * Ahora los tres exigen nonce, la capacidad `edit_booked_appointments`,
 * que el ID corresponda de verdad a una cita y que la cita pertenezca a
 * un calendario asignado al agente.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BookedFEA_Ajax' ) ) {

	/**
	 * Atiende las peticiones AJAX de los agentes desde el front-end.
	 */
	class BookedFEA_Ajax {

		/**
		 * Registra los puntos de entrada.
		 */
		public function __construct() {

			add_action( 'wp_ajax_booked_fea_delete_appt', array( $this, 'booked_fea_delete_appt' ) );
			add_action( 'wp_ajax_booked_fea_approve_appt', array( $this, 'booked_fea_approve_appt' ) );
			add_action( 'wp_ajax_booked_fea_user_info_modal', array( $this, 'booked_fea_user_info_modal' ) );
		}

		/**
		 * Comprueba los permisos y devuelve el ID de cita validado.
		 *
		 * @return int ID de la cita. Termina la petición si algo no cuadra.
		 */
		private function comprobar_y_obtener_cita() {

			pwcal_verificar_nonce_ajax( PWCAL_NONCE_FRONT );

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				wp_send_json_error(
					array( 'message' => __( 'No tienes permisos para gestionar citas.', 'pw-calendario' ) ),
					403
				);
			}

			$id_cita = pwcal_validar_id_cita( pwcal_post_entero( 'appt_id' ) );

			if ( ! $id_cita ) {
				wp_send_json_error(
					array( 'message' => __( 'La cita indicada no existe.', 'pw-calendario' ) ),
					404
				);
			}

			if ( ! $this->agente_gestiona_cita( $id_cita ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Esta cita no pertenece a ninguno de tus calendarios.', 'pw-calendario' ) ),
					403
				);
			}

			return $id_cita;
		}

		/**
		 * Comprueba si el agente actual tiene asignado el calendario de la cita.
		 *
		 * Quien tiene `manage_booked_options` gestiona todos los calendarios.
		 *
		 * @param int $id_cita ID de la cita, ya validado.
		 * @return bool
		 */
		private function agente_gestiona_cita( $id_cita ) {

			if ( current_user_can( 'manage_booked_options' ) ) {
				return true;
			}

			$calendarios_cita = wp_get_post_terms( $id_cita, 'booked_custom_calendars', array( 'fields' => 'ids' ) );

			if ( is_wp_error( $calendarios_cita ) ) {
				return false;
			}

			$usuario = wp_get_current_user();
			$correo  = $usuario->user_email;

			// Una cita sin calendario pertenece al calendario predeterminado,
			// que solo gestiona quien administra las opciones del plugin.
			if ( empty( $calendarios_cita ) ) {
				return false;
			}

			foreach ( $calendarios_cita as $id_calendario ) {

				$meta = get_option( 'taxonomy_' . $id_calendario );

				if ( ! is_array( $meta ) || empty( $meta['notifications_user_id'] ) ) {
					continue;
				}

				if ( strtolower( $meta['notifications_user_id'] ) === strtolower( $correo ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Cancela una cita.
		 *
		 * @return void
		 */
		public function booked_fea_delete_appt() {

			$id_cita = $this->comprobar_y_obtener_cita();

			$contenido = get_option( 'booked_cancellation_email_content' );
			$asunto    = get_option( 'booked_cancellation_email_subject' );

			if ( $contenido && $asunto ) {

				$sustituciones = booked_get_appointment_tokens( $id_cita );
				$contenido     = booked_token_replacement( $contenido, $sustituciones );
				$asunto        = booked_token_replacement( $asunto, $sustituciones );

				booked_mailer( $sustituciones['email'], $asunto, $contenido );
			}

			do_action( 'booked_appointment_cancelled', $id_cita );

			wp_delete_post( $id_cita, true );

			wp_die();
		}

		/**
		 * Aprueba una cita pendiente.
		 *
		 * @return void
		 */
		public function booked_fea_approve_appt() {

			$id_cita = $this->comprobar_y_obtener_cita();

			$contenido = get_option( 'booked_approval_email_content' );
			$asunto    = get_option( 'booked_approval_email_subject' );

			$sustituciones = booked_get_appointment_tokens( $id_cita );

			if ( $contenido && $asunto ) {
				$contenido = booked_token_replacement( $contenido, $sustituciones );
				$asunto    = booked_token_replacement( $asunto, $sustituciones );

				booked_mailer( $sustituciones['email'], $asunto, $contenido );
			}

			wp_publish_post( $id_cita );

			wp_die();
		}

		/**
		 * Muestra la ficha con los datos de la cita y del cliente.
		 *
		 * @return void
		 */
		public function booked_fea_user_info_modal() {

			$id_cita = $this->comprobar_y_obtener_cita();

			$formato_hora  = get_option( 'time_format' );
			$formato_fecha = get_option( 'date_format' );

			// El usuario se toma del metadato de la cita, no de $_POST: así
			// no se puede pedir la ficha de un usuario cualquiera.
			$id_usuario = (int) get_post_meta( $id_cita, '_appointment_user', true );

			if ( ! $id_usuario ) {
				$publicacion = get_post( $id_cita );
				$id_usuario  = $publicacion ? (int) $publicacion->post_author : 0;
			}

			echo '<div class="booked-scrollable">';
			echo '<p class="booked-title-bar"><small>' . esc_html__( 'Datos de la cita', 'pw-calendario' ) . '</small></p>';

			echo '<p class="fea-modal-title">' . esc_html__( 'Datos de contacto', 'pw-calendario' ) . '</p>';

			if ( ! $id_usuario ) {

				$nombre_invitado = get_post_meta( $id_cita, '_appointment_guest_name', true );
				$correo_invitado = get_post_meta( $id_cita, '_appointment_guest_email', true );

				echo '<p><strong class="booked-left-title">' . esc_html__( 'Nombre', 'pw-calendario' ) . ':</strong> ' . esc_html( $nombre_invitado ) . '<br>';

				if ( $correo_invitado ) {
					echo '<strong class="booked-left-title">' . esc_html__( 'Correo electrónico', 'pw-calendario' ) . ':</strong> ';
					echo '<a href="' . esc_url( 'mailto:' . $correo_invitado ) . '">' . esc_html( $correo_invitado ) . '</a>';
				}

				echo '</p>';

			} else {

				$datos_usuario = get_userdata( $id_usuario );

				if ( $datos_usuario ) {

					$nombre   = booked_get_name( $id_usuario );
					$correo   = $datos_usuario->user_email;
					$telefono = get_user_meta( $id_usuario, 'booked_phone', true );

					echo '<p><strong class="booked-left-title">' . esc_html__( 'Nombre', 'pw-calendario' ) . ':</strong> ' . esc_html( $nombre ) . '<br>';

					if ( $correo ) {
						echo '<strong class="booked-left-title">' . esc_html__( 'Correo electrónico', 'pw-calendario' ) . ':</strong> ';
						echo '<a href="' . esc_url( 'mailto:' . $correo ) . '">' . esc_html( $correo ) . '</a><br>';
					}

					if ( $telefono ) {
						$telefono_limpio = preg_replace( '/[^0-9+]/', '', $telefono );
						echo '<strong class="booked-left-title">' . esc_html__( 'Teléfono', 'pw-calendario' ) . ':</strong> ';
						echo '<a href="' . esc_url( 'tel:' . $telefono_limpio ) . '">' . esc_html( $telefono ) . '</a>';
					}

					echo '</p>';
				}
			}

			// ----- Datos de la cita -----

			$marca_tiempo = get_post_meta( $id_cita, '_appointment_timestamp', true );
			$intervalo    = get_post_meta( $id_cita, '_appointment_timeslot', true );
			$campos       = get_post_meta( $id_cita, '_cf_meta_value', true );

			$fecha_texto = date_i18n( $formato_fecha, $marca_tiempo );
			$dia_semana  = date_i18n( 'l', $marca_tiempo );

			$partes = explode( '-', (string) $intervalo );
			$inicio = isset( $partes[0] ) ? $partes[0] : '0000';
			$fin    = isset( $partes[1] ) ? $partes[1] : '2400';

			if ( '0000' === $inicio && '2400' === $fin ) {
				$intervalo_texto = __( 'Todo el día', 'pw-calendario' );
			} else {
				$intervalo_texto = sprintf(
					/* translators: 1: hora de inicio, 2: hora de fin. */
					__( 'de %1$s a %2$s', 'pw-calendario' ),
					date_i18n( $formato_hora, strtotime( $inicio ) ),
					date_i18n( $formato_hora, strtotime( $fin ) )
				);
			}

			$campos = apply_filters( 'booked_fea_cf_metavalue', $campos );

			echo '<p class="fea-modal-title fea-bordered">' . esc_html__( 'Datos de la cita', 'pw-calendario' ) . '</p>';

			do_action( 'booked_before_appointment_information_admin' );

			echo '<p><strong class="booked-left-title">' . esc_html__( 'Fecha', 'pw-calendario' ) . ':</strong> ';
			echo esc_html( $dia_semana . ', ' . $fecha_texto ) . '<br>';
			echo '<strong class="booked-left-title">' . esc_html__( 'Hora', 'pw-calendario' ) . ':</strong> ';
			echo esc_html( $intervalo_texto ) . '</p>';

			if ( $campos ) {
				echo '<div class="cf-meta-values">' . wp_kses_post( $campos ) . '</div>';
			}

			do_action( 'booked_after_appointment_information_admin' );

			echo '<a href="#" class="close"><i class="booked-icon booked-icon-close"></i></a>';
			echo '</div>';

			wp_die();
		}
	}
}
