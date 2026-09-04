<?php
/**
 * Puntos de entrada AJAX públicos.
 *
 * Criterio de seguridad aplicado:
 *
 * - Los *cargadores* (mes, día y listado) solo devuelven disponibilidad
 *   pública, así que no exigen nonce. Hacerlo rompería el calendario en las
 *   páginas servidas desde caché, donde el nonce llega caducado.
 * - Las *acciones* que escriben en la base de datos o autentican
 *   (reservar, cancelar, acceder, recuperar contraseña) exigen nonce
 *   siempre, y además limitan los intentos de acceso.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Booked_AJAX' ) ) {

	/**
	 * Registra y atiende las peticiones AJAX del front-end.
	 */
	class Booked_AJAX {

		/**
		 * Registra los puntos de entrada.
		 */
		public function __construct() {

			// ---------- Acciones (invitados y usuarios registrados) ---------- //

			add_action( 'wp_ajax_booked_ajax_login', array( $this, 'booked_ajax_login' ) );
			add_action( 'wp_ajax_nopriv_booked_ajax_login', array( $this, 'booked_ajax_login' ) );

			add_action( 'wp_ajax_booked_ajax_forgot', array( $this, 'booked_ajax_forgot' ) );
			add_action( 'wp_ajax_nopriv_booked_ajax_forgot', array( $this, 'booked_ajax_forgot' ) );

			add_action( 'wp_ajax_booked_add_appt', array( $this, 'booked_add_appt' ) );
			add_action( 'wp_ajax_nopriv_booked_add_appt', array( $this, 'booked_add_appt' ) );

			// ---------- Cargadores de solo lectura ---------- //

			add_action( 'wp_ajax_booked_calendar_month', array( $this, 'booked_calendar_month' ) );
			add_action( 'wp_ajax_nopriv_booked_calendar_month', array( $this, 'booked_calendar_month' ) );

			add_action( 'wp_ajax_booked_calendar_date', array( $this, 'booked_calendar_date' ) );
			add_action( 'wp_ajax_nopriv_booked_calendar_date', array( $this, 'booked_calendar_date' ) );

			add_action( 'wp_ajax_booked_appointment_list_date', array( $this, 'booked_appointment_list_date' ) );
			add_action( 'wp_ajax_nopriv_booked_appointment_list_date', array( $this, 'booked_appointment_list_date' ) );

			add_action( 'wp_ajax_booked_new_appointment_form', array( $this, 'booked_new_appointment_form' ) );
			add_action( 'wp_ajax_nopriv_booked_new_appointment_form', array( $this, 'booked_new_appointment_form' ) );

			// ---------- Solo usuarios registrados ---------- //

			add_action( 'wp_ajax_booked_cancel_appt', array( $this, 'booked_cancel_appt' ) );
		}


		// ---------------- CARGADORES ---------------- //

		/**
		 * Devuelve el HTML de un mes del calendario.
		 *
		 * @return void
		 */
		public function booked_calendar_month() {

			booked_wpml_ajax();

			if ( ! isset( $_POST['gotoMonth'] ) ) {
				wp_die();
			}

			$id_calendario = pwcal_post_calendario();

			/*
			 * `force_default` no es un si/no: el JS envia aqui el mes
			 * «de casa» (`Y-m-01`), el que se estaba viendo al cargar la
			 * pagina. `booked_fe_calendar()` lo necesita como cadena para
			 * dos cosas: no volver a saltar al primer mes con hueco, y
			 * saber que se ha navegado, que es lo que hace aparecer la
			 * flecha de volver atras.
			 *
			 * Convertirlo a booleano dejaba $currentMonth valiendo `true`,
			 * y como cualquier cadena no vacia es igual a `true` en una
			 * comparacion flexible, la flecha no se pintaba nunca: se
			 * podia avanzar de mes pero no volver.
			 */
			$mes_de_casa = pwcal_post_texto( 'force_default' );
			$por_defecto = false;

			if ( $mes_de_casa && 'false' !== $mes_de_casa ) {

				$marca_casa = strtotime( $mes_de_casa );

				if ( false !== $marca_casa ) {
					$por_defecto = date_i18n( 'Y-m-01', $marca_casa );
				}
			}

			$mes_solicitado = pwcal_post_texto( 'gotoMonth' );
			$marca_tiempo   = ( $mes_solicitado && 'false' !== $mes_solicitado )
				? strtotime( $mes_solicitado )
				: current_time( 'timestamp' );

			if ( false === $marca_tiempo ) {
				$marca_tiempo = current_time( 'timestamp' );
			}

			booked_fe_calendar(
				date_i18n( 'Y', $marca_tiempo ),
				date_i18n( 'm', $marca_tiempo ),
				$id_calendario,
				$por_defecto
			);

			wp_die();
		}

		/**
		 * Devuelve el contenido de un día del calendario.
		 *
		 * @return void
		 */
		public function booked_calendar_date() {

			booked_wpml_ajax();

			$fecha = pwcal_post_fecha( 'date' );

			if ( ! $fecha ) {
				wp_die();
			}

			booked_fe_calendar_date_content( $fecha, pwcal_post_calendario() );

			wp_die();
		}

		/**
		 * Devuelve el listado de citas de un día.
		 *
		 * @return void
		 */
		public function booked_appointment_list_date() {

			booked_wpml_ajax();

			$fecha = pwcal_post_fecha( 'date' );

			if ( ! $fecha ) {
				wp_die();
			}

			/*
			 * Aqui si es un si/no: booked_fe_appointment_list_content()
			 * solo comprueba `if (!$force_day)` y la fecha la toma de su
			 * primer argumento. No confundir con el navegador de meses,
			 * donde este mismo campo lleva el mes y hace falta la cadena.
			 */
			$por_defecto = ! empty( $_POST['force_default'] ) && 'false' !== $_POST['force_default'];

			booked_fe_appointment_list_content(
				gmdate( 'Ymd', strtotime( $fecha ) ),
				pwcal_post_calendario(),
				$por_defecto
			);

			wp_die();
		}

		/**
		 * Devuelve el formulario de nueva cita.
		 *
		 * @return void
		 */
		public function booked_new_appointment_form() {

			booked_wpml_ajax();

			if ( apply_filters( 'booked_show_new_appointment_form', true ) ) {
				require PWCAL_AJAX_INCLUDES_DIR . 'front/appointment-form.php';
			}

			wp_die();
		}


		// ---------------- ACCIONES ---------------- //

		/**
		 * Devuelve la clave del contador de intentos de acceso de esta IP.
		 *
		 * @return string
		 */
		private function clave_intentos() {

			$ip = isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
				: 'desconocida';

			return 'pwcal_intentos_' . md5( $ip );
		}

		/**
		 * Atiende el acceso mediante el formulario del calendario.
		 *
		 * @return void
		 */
		public function booked_ajax_login() {

			booked_wpml_ajax();

			pwcal_verificar_ajax_front();

			$usuario_enviado = pwcal_post_texto( 'username' );

			// La contraseña no se sanea: `wp_signon` la compara con el hash y
			// cualquier limpieza rompería contraseñas legítimas.
			$contrasena = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

			if ( ! $usuario_enviado || ! $contrasena ) {
				wp_die();
			}

			// Limita los intentos por IP para que el formulario público no
			// sirva como oráculo de fuerza bruta contra las contraseñas.
			$clave    = $this->clave_intentos();
			$intentos = (int) get_transient( $clave );

			if ( $intentos >= 10 ) {
				echo 'error';
				wp_die();
			}

			$credenciales = array(
				'user_login'    => $usuario_enviado,
				'user_password' => $contrasena,
				'remember'      => true,
			);

			$usuario = wp_signon( $credenciales, is_ssl() );

			if ( is_wp_error( $usuario ) ) {
				set_transient( $clave, $intentos + 1, 15 * MINUTE_IN_SECONDS );
				wp_die();
			}

			delete_transient( $clave );

			wp_set_current_user( $usuario->ID );

			echo 'success';

			wp_die();
		}

		/**
		 * Atiende la recuperación de contraseña.
		 *
		 * @return void
		 */
		public function booked_ajax_forgot() {

			booked_wpml_ajax();

			pwcal_verificar_ajax_front();

			$usuario_enviado = pwcal_post_texto( 'username' );

			if ( ! $usuario_enviado ) {
				wp_die();
			}

			// Se limita igualmente: sin límite este punto permite enumerar
			// usuarios y usar el sitio para enviar correo masivo.
			$clave    = $this->clave_intentos();
			$intentos = (int) get_transient( $clave );

			if ( $intentos >= 10 ) {
				echo 'error';
				wp_die();
			}

			set_transient( $clave, $intentos + 1, 15 * MINUTE_IN_SECONDS );

			if ( booked_reset_password( $usuario_enviado ) ) {
				echo 'success';
			}

			wp_die();
		}

		/**
		 * Crea una cita nueva desde el front-end.
		 *
		 * @return void
		 */
		public function booked_add_appt() {

			booked_wpml_ajax();

			pwcal_verificar_ajax_front();

			$puede_reservar = apply_filters(
				'booked_can_add_appt',
				isset( $_POST['date'], $_POST['timestamp'], $_POST['timeslot'], $_POST['customer_type'] )
			);

			if ( $puede_reservar ) {
				require PWCAL_AJAX_INCLUDES_DIR . 'front/book-appointment.php';
			}

			wp_die();
		}

		/**
		 * Cancela una cita del usuario que la ha solicitado.
		 *
		 * @return void
		 */
		public function booked_cancel_appt() {

			booked_wpml_ajax();

			pwcal_verificar_ajax_front();

			if ( ! is_user_logged_in() ) {
				wp_die();
			}

			// Se valida que el ID sea realmente una cita antes de seguir: sin
			// esta comprobación el ID podía apuntar a cualquier entrada o
			// página del sitio y acabar borrada de forma permanente.
			$id_cita = pwcal_validar_id_cita( pwcal_post_entero( 'appt_id' ) );

			if ( ! $id_cita ) {
				wp_die();
			}

			require PWCAL_AJAX_INCLUDES_DIR . 'front/cancel-appointment.php';

			wp_die();
		}
	}
}
