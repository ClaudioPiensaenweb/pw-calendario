<?php
/**
 * Clase principal del plugin.
 *
 * Contiene la lógica compartida y la del front-end. Las pantallas del
 * escritorio viven en `Pw_Calendario_Admin`, que solo se instancia dentro
 * de wp-admin. El registro de ganchos se delega en
 * `Pw_Calendario_Loader`.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Núcleo del plugin.
 */
class Pw_Calendario {

	/**
	 * Pantallas del escritorio en las que se cargan los recursos del plugin.
	 *
	 * @var array
	 */
	public $pantallas;

	/**
	 * Cargador de ganchos.
	 *
	 * @var Pw_Calendario_Loader
	 */
	protected $cargador;

	/**
	 * Instancia de la clase del escritorio, o null en el front-end.
	 *
	 * @var Pw_Calendario_Admin|null
	 */
	protected $admin = null;

	/**
	 * Construye el plugin: dependencias, ganchos y arranque.
	 */
	public function __construct() {

		$this->pantallas = apply_filters(
			'booked_admin_booked_screens',
			array( 'pwcal-pending', 'pwcal-appointments', 'pwcal-settings', 'pwcal-welcome' )
		);

		$this->cargar_dependencias();
		$this->cargador = new Pw_Calendario_Loader();

		$this->registrar_ganchos();
		$this->registrar_ganchos_admin();

		$this->cargador->ejecutar();
	}

	/**
	 * Carga los archivos de los que depende el plugin.
	 *
	 * @return void
	 */
	protected function cargar_dependencias() {

		require_once PWCAL_PLUGIN_DIR . '/post-types/tipo-contenido-citas.php';
		new booked_appointments_post_type();

		require_once PWCAL_PLUGIN_DIR . '/includes/general-functions.php';
		require_once PWCAL_PLUGIN_DIR . '/includes/shortcodes.php';
		require_once PWCAL_PLUGIN_DIR . '/includes/widgets.php';
		require_once PWCAL_PLUGIN_DIR . '/includes/ajax/init.php';
		require_once PWCAL_PLUGIN_DIR . '/includes/ajax/init_admin.php';

		new Booked_AJAX();
		new Booked_Admin_AJAX();
	}

	/**
	 * Registra los ganchos comunes y del front-end.
	 *
	 * @return void
	 */
	protected function registrar_ganchos() {

		$c = $this->cargador;

		// El menú de la barra de administración se pinta también en el
		// front-end, así que no puede quedar tras una comprobación de
		// `is_admin()`.
		$c->accion( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 65 );

		$c->filtro( 'user_contactmethods', array( $this, 'campo_telefono' ) );

		$c->accion( 'booked_profile_tabs', array( $this, 'pestanas_perfil' ) );
		$c->accion( 'booked_profile_tab_content', array( $this, 'contenido_pestanas_perfil' ) );
		$c->accion( 'wp_enqueue_scripts', array( $this, 'front_end_scripts' ), 1 );
		$c->accion( 'wp_enqueue_scripts', array( __CLASS__, 'front_end_styles' ) );
		$c->accion( 'wp_enqueue_scripts', array( __CLASS__, 'front_end_color_theme' ) );

		// El guardado del calendario puede dispararse fuera del escritorio
		// (WP-CLI, REST), así que se registra siempre.
		$c->accion( 'create_booked_custom_calendars', array( $this, 'calendarios_guardar_campos' ), 10, 2 );
		$c->accion( 'edited_booked_custom_calendars', array( $this, 'calendarios_guardar_campos' ), 10, 2 );

		$c->accion( 'init', array( $this, 'init' ), 10 );
		$c->accion( 'init', array( $this, 'programar_recordatorios' ), 20 );

		// Evita que WooCommerce redirija a los gestores de citas a "Mi cuenta".
		$c->filtro( 'woocommerce_prevent_admin_access', array( $this, 'permitir_acceso_escritorio' ) );

		// Permite a otros plugins o al tema aplicar las capacidades a otros perfiles.
		$c->filtro( 'booked_user_roles', array( $this, 'filtro_perfiles_usuario' ) );

		// Recordatorios por correo.
		$c->filtro( 'cron_schedules', array( $this, 'cron_schedules' ) );
		$c->accion( 'booked_send_admin_reminders', array( $this, 'recordatorios_gestor' ), 20 );
		$c->accion( 'booked_send_user_reminders', array( $this, 'recordatorios_usuario' ), 20 );
	}

	/**
	 * Instancia y registra la parte del escritorio, solo en wp-admin.
	 *
	 * @return void
	 */
	protected function registrar_ganchos_admin() {

		if ( ! is_admin() ) {
			return;
		}

		require_once PWCAL_PLUGIN_DIR . '/includes/class-pw-calendario-admin.php';

		$this->admin = new Pw_Calendario_Admin( $this->pantallas );
		$this->admin->registrar( $this->cargador );
	}

		/**
		 * Se ejecuta al activar el plugin.
		 *
		 * Registra el perfil de gestor de citas y sus capacidades una sola vez,
		 * en lugar de en cada carga de página.
		 *
		 * @return void
		 */
		public static function activate() {

			self::registrar_perfiles();
			set_transient( '_booked_welcome_screen_activation_redirect', true, 30 );
		}

		/**
		 * Registra el perfil "Gestor de citas" y asigna las capacidades.
		 *
		 * @return void
		 */
		public static function registrar_perfiles() {

			add_role(
				'booked_booking_agent',
				__( 'Gestor de citas', 'pw-calendario' ),
				array( 'read' => true )
			);

			$perfiles = apply_filters( 'booked_user_roles', array( 'administrator', 'booked_booking_agent' ) );

			foreach ( $perfiles as $nombre_perfil ) {
				$perfil = get_role( $nombre_perfil );

				// `get_role()` devuelve null si el perfil no existe. Sin esta
				// comprobación PHP 8 lanza un error fatal.
				if ( $perfil ) {
					$perfil->add_cap( 'edit_booked_appointments' );
				}
			}

			$administrador = get_role( 'administrator' );

			if ( $administrador ) {
				$administrador->add_cap( 'manage_booked_options' );
			}
		}

		/**
		 * Programa o cancela los eventos de recordatorio según la configuración.
		 *
		 * @return void
		 */
		public function programar_recordatorios() {

			$contenido_usuario = get_option( 'booked_reminder_email', false );
			$asunto_usuario    = get_option( 'booked_reminder_email_subject', false );

			if ( $contenido_usuario && $asunto_usuario ) {
				if ( ! wp_next_scheduled( 'booked_send_user_reminders' ) ) {
					wp_schedule_event( time(), 'booked_everyfive', 'booked_send_user_reminders' );
				}
			} else {
				wp_clear_scheduled_hook( 'booked_send_user_reminders' );
			}

			$contenido_gestor = get_option( 'booked_admin_reminder_email', false );
			$asunto_gestor    = get_option( 'booked_admin_reminder_email_subject', false );

			if ( $contenido_gestor && $asunto_gestor ) {
				if ( ! wp_next_scheduled( 'booked_send_admin_reminders' ) ) {
					wp_schedule_event( time(), 'booked_everyfive', 'booked_send_admin_reminders' );
				}
			} else {
				wp_clear_scheduled_hook( 'booked_send_admin_reminders' );
			}
		}

		/**
		 * Envía los recordatorios a los gestores de las citas próximas.
		 *
		 * @return void
		 */
		public static function recordatorios_gestor() {

			$margen        = absint( get_option( 'booked_admin_reminder_buffer', 30 ) );
			$inicio        = current_time( 'timestamp' );
			$fin           = strtotime( '+' . $margen . ' minutes', $inicio );

			$consulta = new WP_Query(
				array(
					'post_type'      => 'booked_appointments',
					'posts_per_page' => 500,
					'post_status'    => array( 'publish', 'future' ),
					'meta_query'     => array(
						array(
							'key'     => '_appointment_timestamp',
							'value'   => array( $inicio, $fin ),
							'compare' => 'BETWEEN',
							'type'    => 'NUMERIC',
						),
					),
				)
			);

			if ( $consulta->have_posts() ) {
				foreach ( $consulta->posts as $publicacion ) {

					$id_cita = $publicacion->ID;
					$enviado = get_post_meta( $id_cita, '_appointment_admin_reminder_sent', true );

					if ( $enviado || ! apply_filters( 'booked_prepare_sending_reminder', true, $id_cita ) ) {
						continue;
					}

					$calendarios   = get_the_terms( $id_cita, 'booked_custom_calendars' );
					$id_calendario = false;

					if ( ! empty( $calendarios ) && ! is_wp_error( $calendarios ) ) {
						$id_calendario = $calendarios[0]->term_id;
					}

					$contenido = get_option( 'booked_admin_reminder_email', false );
					$asunto    = get_option( 'booked_admin_reminder_email_subject', false );

					if ( ! $contenido || ! $asunto ) {
						continue;
					}

					$correo_gestor = booked_which_admin_to_send_email( $id_calendario );
					$sustituciones = booked_get_appointment_tokens( $id_cita );
					$contenido     = booked_token_replacement( $contenido, $sustituciones );
					$asunto        = booked_token_replacement( $asunto, $sustituciones );

					update_post_meta( $id_cita, '_appointment_admin_reminder_sent', true );

					do_action(
						'booked_admin_reminder_email',
						$correo_gestor,
						$asunto,
						$contenido,
						$sustituciones['email'],
						$sustituciones['name']
					);
				}
			}

			wp_reset_postdata();
		}

		/**
		 * Envía los recordatorios a los clientes de las citas próximas.
		 *
		 * @return void
		 */
		public static function recordatorios_usuario() {

			$margen = absint( get_option( 'booked_reminder_buffer', 30 ) );
			$inicio = current_time( 'timestamp' );
			$fin    = strtotime( '+' . $margen . ' minutes', $inicio );

			$consulta = new WP_Query(
				array(
					'post_type'      => 'booked_appointments',
					'posts_per_page' => 500,
					'post_status'    => array( 'publish', 'future' ),
					'meta_query'     => array(
						array(
							'key'     => '_appointment_timestamp',
							'value'   => array( $inicio, $fin ),
							'compare' => 'BETWEEN',
							'type'    => 'NUMERIC',
						),
					),
				)
			);

			if ( $consulta->have_posts() ) {
				foreach ( $consulta->posts as $publicacion ) {

					$id_cita = $publicacion->ID;
					$enviado = get_post_meta( $id_cita, '_appointment_user_reminder_sent', true );

					if ( $enviado || ! apply_filters( 'booked_prepare_sending_reminder', true, $id_cita ) ) {
						continue;
					}

					$contenido = get_option( 'booked_reminder_email', false );
					$asunto    = get_option( 'booked_reminder_email_subject', false );

					if ( ! $contenido || ! $asunto ) {
						continue;
					}

					$sustituciones = booked_get_appointment_tokens( $id_cita );
					$contenido     = booked_token_replacement( $contenido, $sustituciones );
					$asunto        = booked_token_replacement( $asunto, $sustituciones );

					update_post_meta( $id_cita, '_appointment_user_reminder_sent', true );

					do_action( 'booked_reminder_email', $sustituciones['email'], $asunto, $contenido );
				}
			}

			wp_reset_postdata();
		}

		/**
		 * Añade la periodicidad de cinco minutos para los recordatorios.
		 *
		 * @param array $periodicidades Periodicidades registradas.
		 * @return array
		 */
		public static function cron_schedules( $periodicidades ) {

			$periodicidades['booked_everyfive'] = array(
				'interval' => 60 * 5,
				'display'  => __( 'Cada cinco minutos', 'pw-calendario' ),
			);

			return $periodicidades;
		}

		/**
		 * Devuelve la lista de opciones del plugin.
		 *
		 * Los nombres de las opciones se conservan con el prefijo original
		 * `booked_` para no perder la configuración ya guardada.
		 *
		 * @return array
		 */
		public static function plugin_settings() {

			return array(
				'booked_login_redirect_page',
				'booked_custom_login_message',
				'booked_appointment_redirect_type',
				'booked_appointment_success_redirect_page',
				'booked_registration_name_requirements',
				'booked_hide_admin_bar_menu',
				'booked_timeslot_intervals',
				'booked_appointment_buffer',
				'booked_appointment_limit',
				'booked_cancellation_buffer',
				'booked_new_appointment_default',
				'booked_prevent_appointments_before',
				'booked_prevent_appointments_after',
				'booked_booking_type',
				'booked_require_guest_email_address',
				'booked_hide_default_calendar',
				'booked_hide_unavailable_timeslots',
				'booked_hide_google_link',
				'booked_hide_weekends',
				'booked_dont_allow_user_cancellations',
				'booked_show_only_titles',
				'booked_hide_end_times',
				'booked_hide_available_timeslots',
				'booked_public_appointments',
				'booked_redirect_non_admins',
				'booked_light_color',
				'booked_dark_color',
				'booked_button_color',
				'booked_email_logo',
				'booked_default_email_user',
				'booked_email_force_sender',
				'booked_email_force_sender_from',
				'booked_emailer_disabled',
				// Control de envios: ver includes/envios.php.
				'pwcal_dominio_envios',
				'pwcal_envios_pausados',
				'booked_reminder_buffer',
				'booked_admin_reminder_buffer',
				'booked_reminder_email',
				'booked_admin_reminder_email',
				'booked_reminder_email_subject',
				'booked_admin_reminder_email_subject',
				'booked_registration_email_subject',
				'booked_registration_email_content',
				'booked_approval_email_content',
				'booked_approval_email_subject',
				'booked_cancellation_email_content',
				'booked_cancellation_email_subject',
				'booked_appt_confirmation_email_content',
				'booked_appt_confirmation_email_subject',
				'booked_admin_appointment_email_content',
				'booked_admin_appointment_email_subject',
				'booked_admin_cancellation_email_content',
				'booked_admin_cancellation_email_subject',
			);
		}

		/**
		 * Devuelve los usuarios que pueden tener un calendario asignado.
		 *
		 * @return array
		 */
		public static function usuarios_asignables() {

			return get_users( array( 'role__in' => array( 'administrator', 'booked_booking_agent' ) ) );
		}

		/**
		 * Devuelve la URL de admin-ajax teniendo en cuenta WPML.
		 *
		 * @return string
		 */
		public static function url_ajax() {

			$url    = admin_url( 'admin-ajax.php' );
			$idioma = apply_filters( 'wpml_current_language', null );

			if ( $idioma ) {
				$url = add_query_arg( 'wpml_lang', $idioma, $url );
			}

			return $url;
		}

		/**
		 * Inicialización general del plugin.
		 *
		 * @return void
		 */
		public function init() {

			// Ocultar la barra de administración a los suscriptores.
			$usuario = wp_get_current_user();

			if ( ! empty( $usuario->roles ) && in_array( 'subscriber', $usuario->roles, true ) ) {
				add_filter( 'show_admin_bar', '__return_false' );
			}

			require_once sprintf( '%s/includes/functions.php', PWCAL_PLUGIN_DIR );

			// Iniciar la sesión solo si hace falta y si todavía se pueden
			// enviar cabeceras, para no provocar avisos de PHP.
			if ( apply_filters( 'booked_sessions_enabled', true ) && ! session_id() && ! headers_sent() && ! wp_doing_cron() ) {
				session_start();
			}

			// Comprobar si el plugin se ha actualizado.
			$version_guardada = get_option( 'booked_version_check', '1.6.20' );

			if ( version_compare( $version_guardada, PWCAL_VERSION, '<' ) ) {
				update_option( 'booked_version_check', PWCAL_VERSION );
				set_transient( '_booked_welcome_screen_activation_redirect', true, 60 );
				set_transient( 'booked_show_new_tags', true, 60 * 60 * 24 * 15 );

				// Reasegurar las capacidades tras una actualización.
				self::registrar_perfiles();
			}
		}

		/**
		 * Recursos JavaScript del front-end.
		 *
		 * @return void
		 */
		public function front_end_scripts() {

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-datepicker' );

			wp_enqueue_script( 'pwcal-spin-js', PWCAL_PLUGIN_URL . '/assets/js/spin.min.js', array(), '2.0.1', true );
			wp_enqueue_script( 'pwcal-spin-jquery', PWCAL_PLUGIN_URL . '/assets/js/spin.jquery.js', array( 'jquery' ), '2.0.1', true );
			wp_enqueue_script( 'pwcal-tooltipster', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/js/jquery.tooltipster.min.js', array( 'jquery' ), '3.3.0', true );

			wp_register_script(
				'pwcal-functions',
				PWCAL_PLUGIN_URL . '/assets/js/functions.js',
				array( 'jquery' ),
				PWCAL_VERSION,
				true
			);

			$tipo_redireccion = get_option( 'booked_appointment_redirect_type', 'booked-profile' );

			if ( 'booked-profile' === $tipo_redireccion ) {
				$pagina_perfil = booked_get_profile_page();
			} elseif ( 'page' === $tipo_redireccion ) {
				$pagina_perfil = get_option( 'booked_appointment_success_redirect_page', false );
			} else {
				$pagina_perfil = false;
			}

			$url_perfil = $pagina_perfil ? esc_url_raw( get_permalink( $pagina_perfil ) ) : false;

			$variables = array(
				'ajax_url'                        => self::url_ajax(),
				'nonce'                           => wp_create_nonce( PWCAL_NONCE_FRONT ),
				'profilePage'                     => $url_perfil,
				'publicAppointments'              => get_option( 'booked_public_appointments', false ),
				'i18n_confirm_appt_delete'        => __( '¿Seguro que quieres cancelar esta cita?', 'pw-calendario' ),
				'i18n_please_wait'                => __( 'Espera un momento…', 'pw-calendario' ),
				'i18n_wrong_username_pass'        => __( 'El usuario o la contraseña no son correctos.', 'pw-calendario' ),
				'i18n_fill_out_required_fields'   => __( 'Rellena todos los campos obligatorios.', 'pw-calendario' ),
				'i18n_guest_appt_required_fields' => __( 'Indica tu nombre para reservar una cita.', 'pw-calendario' ),
				'i18n_appt_required_fields'       => __( 'Indica tu nombre, tu correo electrónico y elige una contraseña para reservar una cita.', 'pw-calendario' ),
				'i18n_appt_required_fields_guest' => __( 'Rellena todos los campos de información.', 'pw-calendario' ),
				'i18n_password_reset'             => __( 'Revisa tu correo electrónico: te hemos enviado las instrucciones para restablecer la contraseña.', 'pw-calendario' ),
				'i18n_password_reset_error'       => __( 'Ese usuario o correo electrónico no está registrado.', 'pw-calendario' ),
			);

			wp_localize_script( 'pwcal-functions', 'booked_js_vars', $variables );
			wp_enqueue_script( 'pwcal-functions' );
		}

		/**
		 * Recursos CSS del front-end.
		 *
		 * @return void
		 */
		public static function front_end_styles() {

			wp_enqueue_style( 'pwcal-icons', PWCAL_PLUGIN_URL . '/assets/css/icons.css', array(), PWCAL_VERSION );
			wp_enqueue_style( 'pwcal-tooltipster', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/css/tooltipster.css', array(), '3.3.0' );
			wp_enqueue_style( 'pwcal-tooltipster-theme', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/css/themes/tooltipster-light.css', array(), '3.3.0' );
			wp_enqueue_style( 'pwcal-animations', PWCAL_PLUGIN_URL . '/assets/css/animations.css', array(), PWCAL_VERSION );
			wp_enqueue_style( 'pwcal-css', PWCAL_PLUGIN_URL . '/dist/pw-calendario.css', array(), PWCAL_VERSION );

			if ( defined( 'NECTAR_THEME_NAME' ) && 'salient' === NECTAR_THEME_NAME ) {
				wp_enqueue_style( 'pwcal-salient', PWCAL_PLUGIN_URL . '/assets/css/theme-specific/salient.css', array(), PWCAL_VERSION );
			}
		}

		/**
		 * Imprime los colores configurados como CSS en línea.
		 *
		 * @return void
		 */
		public static function front_end_color_theme() {

			if ( isset( $_GET['print'] ) ) {
				return;
			}

			$archivo_colores = PWCAL_PLUGIN_DIR . '/assets/css/color-theme.php';

			if ( ! file_exists( $archivo_colores ) ) {
				return;
			}

			ob_start();
			require $archivo_colores;
			$css = ob_get_clean();

			$css = booked_compress_css( $css );

			// El CSS se genera a partir de las opciones de color del plugin,
			// que ya se sanean al guardarse. `wp_strip_all_tags` evita que un
			// valor manipulado pueda cerrar la etiqueta <style>.
			echo '<style media="screen">' . wp_strip_all_tags( $css ) . '</style>';
		}

		/**
		 * Pinta las pestañas del perfil público.
		 *
		 * @param array $pestanas Pestañas a mostrar.
		 * @return void
		 */
		public function pestanas_perfil( $pestanas ) {

			foreach ( $pestanas as $slug => $pestana ) {

				$clase = ! empty( $pestana['class'] ) ? ' class="' . esc_attr( $pestana['class'] ) . '"' : '';
				$icono = ! empty( $pestana['booked-icon'] ) ? $pestana['booked-icon'] : '';

				echo '<li' . $clase . '>';
				echo '<a href="#' . esc_attr( $slug ) . '">';
				echo '<i class="booked-icon ' . esc_attr( $icono ) . '"></i>';
				echo esc_html( $pestana['title'] );
				echo '</a></li>';
			}
		}

		/**
		 * Pinta el contenido de las pestañas del perfil público.
		 *
		 * @param array $pestanas Pestañas a mostrar.
		 * @return void
		 */
		public function contenido_pestanas_perfil( $pestanas ) {

			foreach ( $pestanas as $slug => $pestana ) {

				$funcion = 'booked_profile_content_' . $slug;

				echo '<div id="profile-' . esc_attr( $slug ) . '" class="booked-tab-content bookedClearFix">';

				// Solo se invoca si la función existe: evita un error fatal si
				// un complemento registra una pestaña sin su función asociada.
				if ( is_callable( $funcion ) ) {
					call_user_func( $funcion );
				}

				echo '</div>';
			}
		}

		/**
		 * Añade el menú del plugin a la barra de administración.
		 *
		 * @return void
		 */
		public function add_admin_bar_menu() {

			if ( get_option( 'booked_hide_admin_bar_menu', false ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				return;
			}

			global $wp_admin_bar;

			$url_base = trailingslashit( get_admin_url() );

			$wp_admin_bar->add_menu(
				array(
					'id'    => 'pwcal',
					'title' => '<span class="ab-icon"></span>' . esc_html__( 'Citas', 'pw-calendario' ),
					'href'  => $url_base . 'admin.php?page=pwcal-appointments',
				)
			);

			$wp_admin_bar->add_menu(
				array(
					'parent' => 'pwcal',
					'id'     => 'pwcal-appointments',
					'title'  => esc_html__( 'Citas', 'pw-calendario' ),
					'href'   => $url_base . 'admin.php?page=pwcal-appointments',
				)
			);

			$wp_admin_bar->add_menu(
				array(
					'parent' => 'pwcal',
					'id'     => 'pwcal-pending',
					'title'  => esc_html__( 'Pendientes', 'pw-calendario' ),
					'href'   => $url_base . 'admin.php?page=pwcal-pending',
				)
			);

			if ( current_user_can( 'manage_booked_options' ) ) {
				$wp_admin_bar->add_menu(
					array(
						'parent' => 'pwcal',
						'id'     => 'pwcal-calendars',
						'title'  => esc_html__( 'Calendarios', 'pw-calendario' ),
						'href'   => $url_base . 'edit-tags.php?taxonomy=booked_custom_calendars',
					)
				);
			}

			$wp_admin_bar->add_menu(
				array(
					'parent' => 'pwcal',
					'id'     => 'pwcal-settings',
					'title'  => esc_html__( 'Ajustes', 'pw-calendario' ),
					'href'   => $url_base . 'admin.php?page=pwcal-settings',
				)
			);
		}

		/**
		 * Guarda la asignación del calendario.
		 *
		 * Solo se admite la clave conocida `notifications_user_id` y solo se
		 * acepta el correo de un usuario que realmente pueda gestionar citas,
		 * para que no se puedan desviar los avisos a una dirección arbitraria.
		 *
		 * @param int $id_termino ID del término.
		 * @return void
		 */
		public function calendarios_guardar_campos( $id_termino ) {

			if ( ! isset( $_POST['term_meta'] ) || ! is_array( $_POST['term_meta'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_booked_options' ) ) {
				return;
			}

			$meta_termino = get_option( "taxonomy_$id_termino" );
			$meta_termino = is_array( $meta_termino ) ? $meta_termino : array();

			$correo_enviado = isset( $_POST['term_meta']['notifications_user_id'] )
				? sanitize_email( wp_unslash( $_POST['term_meta']['notifications_user_id'] ) )
				: '';

			$correo_valido = '';

			if ( $correo_enviado ) {
				foreach ( self::usuarios_asignables() as $usuario ) {
					if ( strtolower( $usuario->user_email ) === strtolower( $correo_enviado ) ) {
						$correo_valido = $usuario->user_email;
						break;
					}
				}
			}

			$meta_termino['notifications_user_id'] = $correo_valido;

			update_option( "taxonomy_$id_termino", $meta_termino );
		}

		/**
		 * Añade el campo de teléfono al perfil de usuario.
		 *
		 * @param array $campos Campos de contacto.
		 * @return array
		 */
		public function campo_telefono( $campos ) {

			$campos['booked_phone'] = __( 'Teléfono', 'pw-calendario' );

			return $campos;
		}

		/**
		 * Permite a los gestores de citas acceder al escritorio.
		 *
		 * @param bool $redirigir Valor original del filtro de WooCommerce.
		 * @return bool
		 */
		public function permitir_acceso_escritorio( $redirigir ) {

			$usuario = wp_get_current_user();

			if ( is_array( $usuario->roles ) && in_array( 'booked_booking_agent', $usuario->roles, true ) ) {
				return false;
			}

			return $redirigir;
		}

		/**
		 * Punto de extensión para los perfiles con capacidades del plugin.
		 *
		 * @param array $perfiles Perfiles de usuario.
		 * @return array
		 */
		public static function filtro_perfiles_usuario( $perfiles ) {

			return $perfiles;
		}

}
