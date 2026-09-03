<?php
/**
 * Pantallas y lógica del escritorio.
 *
 * Esta clase se instancia únicamente cuando `is_admin()` es cierto, así
 * que su código no se carga en las peticiones del front-end. Todos sus
 * ganchos (`admin_init`, `admin_menu`, `admin_notices`, `parent_file`,
 * `manage_users_*`, `admin_enqueue_scripts`) se disparan solo dentro de
 * wp-admin, por lo que aislarlos no cambia el comportamiento.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agrupa las pantallas del escritorio.
 */
class Pw_Calendario_Admin {

	/**
	 * Pantallas del escritorio en las que se cargan los recursos.
	 *
	 * @var array
	 */
	public $pantallas;

	/**
	 * @param array $pantallas Listado de slugs de pantalla ya filtrado.
	 */
	public function __construct( $pantallas ) {

		$this->pantallas = $pantallas;
	}

	/**
	 * Registra los ganchos del escritorio en el cargador.
	 *
	 * @param Pw_Calendario_Loader $cargador Cargador de ganchos.
	 * @return void
	 */
	public function registrar( $cargador ) {

		$cargador->accion( 'admin_init', array( $this, 'admin_init' ), 9 );
		$cargador->accion( 'admin_menu', array( $this, 'add_menu' ) );
		$cargador->accion( 'admin_menu', array( $this, 'burbuja_citas_pendientes' ) );
		$cargador->accion( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		$cargador->accion( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		$cargador->accion( 'admin_notices', array( $this, 'aviso_citas_pendientes' ) );
		$cargador->accion( 'admin_notices', array( $this, 'aviso_sin_pagina_perfil' ) );
		$cargador->accion( 'parent_file', array( $this, 'correccion_menu_taxonomia' ) );
		$cargador->accion( 'manage_users_custom_column', array( $this, 'columna_usuario_contenido' ), 15, 3 );
		$cargador->filtro( 'manage_users_columns', array( $this, 'columna_usuario_registrar' ), 15, 1 );
		$cargador->accion( 'booked_custom_calendars_add_form_fields', array( $this, 'calendarios_campos_nuevo' ), 10, 2 );
		$cargador->accion( 'booked_custom_calendars_edit_form_fields', array( $this, 'calendarios_campos_editar' ), 10, 2 );
		$cargador->filtro( 'plugin_action_links_' . plugin_basename( PWCAL_PLUGIN_FILE ), array( $this, 'enlaces_plugin' ) );
	}

	/**
	 * Añade los enlaces del plugin en la pantalla de plugins.
	 *
	 * @param array $enlaces Enlaces existentes.
	 * @return array
	 */
	public function enlaces_plugin( $enlaces ) {

		$propios = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=pwcal-settings' ) ) . '">' . esc_html__( 'Ajustes', 'pw-calendario' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=pwcal-welcome' ) ) . '">' . esc_html__( 'Novedades', 'pw-calendario' ) . '</a>',
		);

		return array_merge( $propios, $enlaces );
	}

		/**
		 * Inicialización del escritorio.
		 *
		 * @return void
		 */
		public function admin_init() {

			// Exportación a CSV. Requiere permiso y nonce: expone nombres,
			// correos y datos de los campos personalizados de todas las citas.
			if ( isset( $_POST['booked_export_appointments_csv'] ) ) {

				if ( ! current_user_can( 'edit_booked_appointments' ) ) {
					wp_die( esc_html__( 'No tienes permisos suficientes para exportar las citas.', 'pw-calendario' ), 403 );
				}

				check_admin_referer( 'pwcal_exportar_csv', 'pwcal_csv_nonce' );

				require PWCAL_PLUGIN_DIR . '/includes/export-csv.php';
			}

			// Redirigir a los usuarios sin permisos de gestión.
			if ( get_option( 'booked_redirect_non_admins', false ) ) {
				if ( ! current_user_can( 'edit_booked_appointments' ) && ! wp_doing_ajax() ) {

					$pagina_perfil = booked_get_profile_page();
					$url_destino   = $pagina_perfil ? get_permalink( $pagina_perfil ) : home_url();

					wp_safe_redirect( $url_destino );
					exit;
				}
			}

			require_once sprintf( '%s/includes/admin-functions.php', PWCAL_PLUGIN_DIR );
			require_once sprintf( '%s/includes/dashboard-widget.php', PWCAL_PLUGIN_DIR );

			$this->init_settings();

			// Redirección a la pantalla de bienvenida tras activar o actualizar.
			if ( ! get_transient( '_booked_welcome_screen_activation_redirect' ) ) {
				return;
			}

			delete_transient( '_booked_welcome_screen_activation_redirect' );

			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
				return;
			}

			// La opcion se consulta aqui y no en la carga del plugin: asi no
			// se hace una consulta a la base de datos en cada peticion del
			// front-end. El valor y el comportamiento son los mismos.
			if ( get_option( 'booked_welcome_screen', true ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'pwcal-welcome' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		/**
		 * Registra las opciones del plugin con su función de saneado.
		 *
		 * @return void
		 */
		public function init_settings() {

			// Opciones cuyo contenido admite HTML básico (plantillas de correo).
			$opciones_html = array(
				'booked_custom_login_message',
				'booked_reminder_email',
				'booked_admin_reminder_email',
				'booked_registration_email_content',
				'booked_approval_email_content',
				'booked_cancellation_email_content',
				'booked_appt_confirmation_email_content',
				'booked_admin_appointment_email_content',
				'booked_admin_cancellation_email_content',
			);

			foreach ( Pw_Calendario::plugin_settings() as $nombre_opcion ) {

				$saneado = in_array( $nombre_opcion, $opciones_html, true )
					? 'pwcal_sanear_opcion_html'
					: 'pwcal_sanear_opcion_texto';

				register_setting(
					'booked_plugin-group',
					$nombre_opcion,
					array( 'sanitize_callback' => $saneado )
				);
			}
		}

		/**
		 * Registra los menús del escritorio.
		 *
		 * @return void
		 */
		public function add_menu() {

			add_menu_page(
				__( 'Citas', 'pw-calendario' ),
				__( 'Citas', 'pw-calendario' ),
				'edit_booked_appointments',
				'pwcal-appointments',
				array( $this, 'admin_calendar' ),
				'dashicons-calendar-alt',
				58
			);

			add_submenu_page(
				'pwcal-appointments',
				__( 'Pendientes', 'pw-calendario' ),
				__( 'Pendientes', 'pw-calendario' ),
				'edit_booked_appointments',
				'pwcal-pending',
				array( $this, 'admin_pending_list' )
			);

			add_submenu_page(
				'pwcal-appointments',
				__( 'Calendarios', 'pw-calendario' ),
				__( 'Calendarios', 'pw-calendario' ),
				'manage_booked_options',
				'edit-tags.php?taxonomy=booked_custom_calendars'
			);

			add_submenu_page(
				'pwcal-appointments',
				__( 'Ajustes', 'pw-calendario' ),
				__( 'Ajustes', 'pw-calendario' ),
				'edit_booked_appointments',
				'pwcal-settings',
				array( $this, 'plugin_settings_page' )
			);

			add_submenu_page(
				'pwcal-appointments',
				__( 'Novedades', 'pw-calendario' ),
				__( 'Novedades', 'pw-calendario' ),
				'manage_booked_options',
				'pwcal-welcome',
				array( $this, 'contenido_bienvenida' )
			);
		}

		/**
		 * Añade la burbuja con el número de citas pendientes al menú.
		 *
		 * @return void
		 */
		public function burbuja_citas_pendientes() {

			global $submenu;

			$pendientes = absint( booked_pending_appts_count() );

			if ( ! $pendientes || ! isset( $submenu['pwcal-appointments'][1][0] ) ) {
				return;
			}

			$submenu['pwcal-appointments'][1][0] .= sprintf(
				'&nbsp;<span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%1$d</span><span class="comments-in-moderation-text screen-reader-text">%2$s</span></span>',
				$pendientes,
				esc_html(
					sprintf(
						/* translators: %d: número de citas pendientes. */
						_n( '%d cita pendiente', '%d citas pendientes', $pendientes, 'pw-calendario' ),
						$pendientes
					)
				)
			);
		}

		/**
		 * Muestra la pantalla de novedades.
		 *
		 * @return void
		 */
		public function contenido_bienvenida() {

			if ( ! current_user_can( 'manage_booked_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
			}

			require sprintf( '%s/templates/welcome.php', PWCAL_PLUGIN_DIR );
		}

		/**
		 * Muestra la página de ajustes.
		 *
		 * @return void
		 */
		public function plugin_settings_page() {

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
			}

			require sprintf( '%s/templates/settings.php', PWCAL_PLUGIN_DIR );
		}

		/**
		 * Muestra el listado de citas pendientes.
		 *
		 * @return void
		 */
		public function admin_pending_list() {

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
			}

			require sprintf( '%s/templates/pending-list.php', PWCAL_PLUGIN_DIR );
		}

		/**
		 * Muestra el calendario del escritorio.
		 *
		 * @return void
		 */
		public function admin_calendar() {

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
			}

			require sprintf( '%s/templates/admin-calendar.php', PWCAL_PLUGIN_DIR );
		}

		/**
		 * Mantiene el menú "Citas" abierto en la pantalla de la taxonomía.
		 *
		 * @param string $archivo_padre Archivo padre del menú.
		 * @return string
		 */
		public function correccion_menu_taxonomia( $archivo_padre ) {

			$pantalla = get_current_screen();

			if ( $pantalla && isset( $pantalla->taxonomy ) && 'booked_custom_calendars' === $pantalla->taxonomy ) {
				$archivo_padre = 'pwcal-appointments';
			}

			return $archivo_padre;
		}

		/**
		 * Avisa si falta la página con el shortcode de perfil.
		 *
		 * @return void
		 */
		public function aviso_sin_pagina_perfil() {

			if ( ! current_user_can( 'manage_booked_options' ) ) {
				return;
			}

			$tipo_reserva   = get_option( 'booked_booking_type', 'registered' );
			$tipo_redireccion = get_option( 'booked_appointment_redirect_type', false );
			$pagina_perfil  = booked_get_profile_page();
			$pagina_actual  = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

			if ( 'registered' !== $tipo_reserva || 'booked-profile' !== $tipo_redireccion ) {
				return;
			}

			if ( $pagina_perfil || 'pwcal-welcome' === $pagina_actual ) {
				return;
			}

			echo '<div class="notice notice-warning" style="line-height:37px; border-left-color:#DB5933;">';
			printf(
				/* translators: %s: shortcode necesario. */
				esc_html__( 'Necesitas crear una página con el shortcode %s. Es obligatorio con la configuración actual.', 'pw-calendario' ),
				'<code>[booked-profile]</code>'
			);
			echo '&nbsp;&nbsp;&nbsp;<a href="' . esc_url( admin_url( 'post-new.php?post_type=page' ) ) . '">' . esc_html__( 'Crear una página', 'pw-calendario' ) . '</a>';
			echo '&nbsp;&nbsp;|&nbsp;&nbsp;<a href="' . esc_url( admin_url( 'admin.php?page=pwcal-settings' ) ) . '">' . esc_html__( 'Cambiar los ajustes', 'pw-calendario' ) . '</a>';
			echo '</div>';
		}

		/**
		 * Avisa de las citas pendientes de aprobación.
		 *
		 * @return void
		 */
		public function aviso_citas_pendientes() {

			if ( ! current_user_can( 'edit_booked_appointments' ) ) {
				return;
			}

			$pendientes    = absint( booked_pending_appts_count() );
			$pagina_actual = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

			if ( ! $pendientes || 'pwcal-pending' === $pagina_actual || 'pwcal-welcome' === $pagina_actual ) {
				return;
			}

			echo '<div class="notice notice-warning" style="line-height:37px">';
			printf(
				/* translators: %d: número de citas pendientes. */
				esc_html( _n( 'Hay %d cita pendiente.', 'Hay %d citas pendientes.', $pendientes, 'pw-calendario' ) ),
				$pendientes
			);
			echo '&nbsp;&nbsp;<a href="' . esc_url( admin_url( 'admin.php?page=pwcal-pending' ) ) . '">';
			echo esc_html( _n( 'Ver la cita pendiente', 'Ver las citas pendientes', $pendientes, 'pw-calendario' ) ) . ' &rarr;';
			echo '</a>';
			echo '</div>';
		}

		/**
		 * Campo de asignación al crear un calendario.
		 *
		 * @return void
		 */
		public function calendarios_campos_nuevo() {

			?>
			<div class="form-field">
				<label for="pwcal_notifications_user_id"><?php esc_html_e( 'Asignar este calendario a', 'pw-calendario' ); ?>:</label>
				<select name="term_meta[notifications_user_id]" id="pwcal_notifications_user_id">
					<option value=""><?php esc_html_e( 'Predeterminado', 'pw-calendario' ); ?></option>
					<?php foreach ( Pw_Calendario::usuarios_asignables() as $usuario ) : ?>
						<?php
						$correo = $usuario->user_email;
						$nombre = $usuario->display_name ? $usuario->display_name . ' (' . $correo . ')' : $correo;
						?>
						<option value="<?php echo esc_attr( $correo ); ?>"><?php echo esc_html( $nombre ); ?></option>
					<?php endforeach; ?>
				</select>
				<p><?php esc_html_e( 'Por defecto se usará el ajuste del panel de Pw Calendario.', 'pw-calendario' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Campo de asignación al editar un calendario.
		 *
		 * @param WP_Term $termino Término del calendario.
		 * @return void
		 */
		public function calendarios_campos_editar( $termino ) {

			$id_termino    = $termino->term_id;
			$meta_termino  = get_option( "taxonomy_$id_termino" );
			$valor_actual  = is_array( $meta_termino ) && isset( $meta_termino['notifications_user_id'] )
				? $meta_termino['notifications_user_id']
				: '';

			?>
			<tr class="form-field">
				<th scope="row">
					<label for="pwcal_notifications_user_id"><?php esc_html_e( 'Asignar este calendario a', 'pw-calendario' ); ?>:</label>
				</th>
				<td>
					<select name="term_meta[notifications_user_id]" id="pwcal_notifications_user_id">
						<option value=""><?php esc_html_e( 'Predeterminado', 'pw-calendario' ); ?></option>
						<?php foreach ( Pw_Calendario::usuarios_asignables() as $usuario ) : ?>
							<?php
							$correo = $usuario->user_email;
							$nombre = $usuario->display_name ? $usuario->display_name . ' (' . $correo . ')' : $correo;
							?>
							<option value="<?php echo esc_attr( $correo ); ?>" <?php selected( $valor_actual, $correo ); ?>>
								<?php echo esc_html( $nombre ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<br>
					<span class="description"><?php esc_html_e( 'Por defecto se usará el ajuste del panel de Pw Calendario.', 'pw-calendario' ); ?></span>
				</td>
			</tr>
			<?php
		}

		/**
		 * Registra la columna de citas en el listado de usuarios.
		 *
		 * @param array $columnas Columnas existentes.
		 * @return array
		 */
		public function columna_usuario_registrar( $columnas ) {

			$columnas['booked_appointments'] = __( 'Citas', 'pw-calendario' );

			return $columnas;
		}

		/**
		 * Rellena la columna de citas del listado de usuarios.
		 *
		 * @param string $valor      Valor actual de la columna.
		 * @param string $columna    Nombre de la columna.
		 * @param int    $id_usuario ID del usuario.
		 * @return string
		 */
		public function columna_usuario_contenido( $valor, $columna, $id_usuario ) {

			if ( 'booked_appointments' !== $columna ) {
				return $valor;
			}

			$citas = get_posts(
				array(
					'posts_per_page'   => 100,
					'meta_key'         => '_appointment_timestamp',
					'orderby'          => 'meta_value_num',
					'order'            => 'ASC',
					'meta_query'       => array(
						array(
							'key'     => '_appointment_timestamp',
							'value'   => current_time( 'timestamp' ),
							'compare' => '>=',
							'type'    => 'NUMERIC',
						),
					),
					'author'           => absint( $id_usuario ),
					'post_type'        => 'booked_appointments',
					'post_status'      => array( 'publish', 'future' ),
					'suppress_filters' => true,
				)
			);

			$total = count( $citas );

			if ( ! $total ) {
				return '';
			}

			$formato_hora  = get_option( 'time_format' );
			$formato_fecha = get_option( 'date_format' );
			$mostradas     = array_slice( $citas, 0, 5 );

			ob_start();

			echo '<strong>' . esc_html(
				sprintf(
					/* translators: %d: número de citas próximas. */
					_n( '%d cita próxima', '%d citas próximas', $total, 'pw-calendario' ),
					$total
				)
			) . ':</strong>';

			echo '<span style="font-size:12px;">';

			foreach ( $mostradas as $cita ) {

				$intervalo    = get_post_meta( $cita->ID, '_appointment_timeslot', true );
				$partes       = explode( '-', (string) $intervalo );
				$marca_tiempo = get_post_meta( $cita->ID, '_appointment_timestamp', true );

				$inicio = isset( $partes[0] ) ? date_i18n( $formato_hora, strtotime( $partes[0] ) ) : '';
				$fin    = isset( $partes[1] ) ? date_i18n( $formato_hora, strtotime( $partes[1] ) ) : '';

				echo '<br>' . esc_html( date_i18n( $formato_fecha, $marca_tiempo ) . ' · ' . $inicio . '–' . $fin );
			}

			if ( $total > 5 ) {
				echo '<br>' . esc_html(
					sprintf(
						/* translators: %d: número de citas adicionales. */
						_n( '…y %d más', '…y %d más', $total - 5, 'pw-calendario' ),
						$total - 5
					)
				);
			}

			echo '</span>';

			return ob_get_clean();
		}

		/**
		 * Recursos JavaScript del escritorio.
		 *
		 * @return void
		 */
		public function admin_scripts() {

			$pagina_actual = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			$pantalla      = get_current_screen();
			$es_escritorio = $pantalla && 'dashboard' === $pantalla->id;

			wp_enqueue_script( 'jquery' );

			if ( 'pwcal-settings' === $pagina_actual || $es_escritorio ) {
				wp_enqueue_script( 'pwcal-serialize', PWCAL_PLUGIN_URL . '/assets/js/jquery.serialize.js', array( 'jquery' ), PWCAL_VERSION, true );
			}

			if ( ! in_array( $pagina_actual, $this->pantallas, true ) && ! $es_escritorio ) {
				return;
			}

			wp_enqueue_media();
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'pwcal-spin-js', PWCAL_PLUGIN_URL . '/assets/js/spin.min.js', array(), '2.0.1', true );
			wp_enqueue_script( 'pwcal-spin-jquery', PWCAL_PLUGIN_URL . '/assets/js/spin.jquery.js', array( 'jquery' ), '2.0.1', true );
			wp_enqueue_script( 'pwcal-chosen', PWCAL_PLUGIN_URL . '/assets/js/chosen/chosen.jquery.min.js', array( 'jquery' ), '1.2.0', true );
			wp_enqueue_script( 'pwcal-fitvids', PWCAL_PLUGIN_URL . '/assets/js/fitvids.js', array( 'jquery' ), '1.1', true );
			wp_enqueue_script( 'pwcal-tooltipster', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/js/jquery.tooltipster.min.js', array( 'jquery' ), '3.3.0', true );

			wp_register_script(
				'pwcal-admin',
				PWCAL_PLUGIN_URL . '/assets/js/admin-functions.js',
				array( 'jquery' ),
				PWCAL_VERSION,
				true
			);

			wp_localize_script( 'pwcal-admin', 'booked_js_vars', $this->variables_js_admin() );
			wp_enqueue_script( 'pwcal-admin' );
		}

		/**
		 * Recursos CSS del escritorio.
		 *
		 * @return void
		 */
		public function admin_styles() {

			$pagina_actual = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			$pantalla      = get_current_screen();
			$es_escritorio = $pantalla && 'dashboard' === $pantalla->id;

			if ( ! in_array( $pagina_actual, $this->pantallas, true ) && ! $es_escritorio ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'pwcal-icons', PWCAL_PLUGIN_URL . '/assets/css/icons.css', array(), PWCAL_VERSION );
			wp_enqueue_style( 'pwcal-tooltipster', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/css/tooltipster.css', array(), '3.3.0' );
			wp_enqueue_style( 'pwcal-tooltipster-theme', PWCAL_PLUGIN_URL . '/assets/js/tooltipster/css/themes/tooltipster-light.css', array(), '3.3.0' );
			wp_enqueue_style( 'pwcal-chosen', PWCAL_PLUGIN_URL . '/assets/js/chosen/chosen.min.css', array(), '1.2.0' );
			wp_enqueue_style( 'pwcal-animations', PWCAL_PLUGIN_URL . '/assets/css/animations.css', array(), PWCAL_VERSION );
			wp_enqueue_style( 'pwcal-atc', PWCAL_PLUGIN_URL . '/assets/css/anadir-al-calendario.css', array(), PWCAL_VERSION );
			wp_enqueue_style( 'pwcal-admin', PWCAL_PLUGIN_URL . '/dist/pw-calendario-admin.css', array(), PWCAL_VERSION );
		}

		/**
		 * Variables JavaScript del escritorio.
		 *
		 * @return array
		 */
		private function variables_js_admin() {

			return array(
				'ajax_url'                                => Pw_Calendario::url_ajax(),
				'nonce'                                   => wp_create_nonce( PWCAL_NONCE_ADMIN ),
				'ajaxRequests'                            => array(),
				'i18n_slot'                               => _x( 'plaza libre', 'una sola plaza', 'pw-calendario' ),
				'i18n_slots'                              => _x( 'plazas libres', 'varias plazas', 'pw-calendario' ),
				'i18n_add'                                => __( 'Añadir franjas horarias', 'pw-calendario' ),
				'i18n_time_error'                         => __( 'La hora de fin tiene que ser posterior a la hora de inicio.', 'pw-calendario' ),
				'i18n_bulk_add_confirm'                   => __( '¿Seguro que quieres añadir esas franjas horarias en bloque?', 'pw-calendario' ),
				'i18n_all_fields_required'                => __( 'Todos los campos son obligatorios.', 'pw-calendario' ),
				'i18n_single_add_confirm'                 => __( 'Vas a añadir las siguientes franjas horarias', 'pw-calendario' ),
				'i18n_to'                                 => __( 'a', 'pw-calendario' ),
				'i18n_please_wait'                        => __( 'Espera un momento…', 'pw-calendario' ),
				'i18n_update_appointment'                 => __( 'Actualizar la cita', 'pw-calendario' ),
				'i18n_create_appointment'                 => __( 'Crear la cita', 'pw-calendario' ),
				'i18n_all_day'                            => __( 'Todo el día', 'pw-calendario' ),
				'i18n_enable'                             => __( 'Activar', 'pw-calendario' ),
				'i18n_disable'                            => __( 'Desactivar', 'pw-calendario' ),
				'i18n_change_date'                        => __( 'Cambiar la fecha', 'pw-calendario' ),
				'i18n_choose_customer'                    => __( 'Elige un cliente.', 'pw-calendario' ),
				'i18n_fill_out_required_fields'           => __( 'Rellena todos los campos obligatorios.', 'pw-calendario' ),
				'i18n_confirm_ts_delete'                  => __( '¿Seguro que quieres eliminar esta franja horaria?', 'pw-calendario' ),
				'i18n_confirm_cts_delete'                 => __( '¿Seguro que quieres eliminar esta franja horaria personalizada?', 'pw-calendario' ),
				'i18n_confirm_appt_delete'                => __( '¿Seguro que quieres cancelar esta cita?', 'pw-calendario' ),
				'i18n_clear_timeslots_confirm'            => __( '¿Seguro que quieres eliminar todas las franjas horarias de este día?', 'pw-calendario' ),
				'i18n_appt_required_fields'               => __( 'El nombre, el correo electrónico y la contraseña son obligatorios.', 'pw-calendario' ),
				'i18n_appt_required_guest_fields'         => __( 'El nombre es obligatorio.', 'pw-calendario' ),
				'i18n_appt_required_guest_fields_surname' => __( 'El nombre y los apellidos son obligatorios.', 'pw-calendario' ),
				'i18n_appt_required_guest_fields_all'     => __( 'El nombre, los apellidos y el correo electrónico son obligatorios.', 'pw-calendario' ),
				'i18n_appt_required_guest_fields_name_email' => __( 'El nombre y el correo electrónico son obligatorios.', 'pw-calendario' ),
				'i18n_confirm_appt_approve'               => __( '¿Seguro que quieres aprobar esta cita?', 'pw-calendario' ),
				'i18n_confirm_appt_approve_all'           => __( '¿Seguro que quieres aprobar TODAS las citas pendientes?', 'pw-calendario' ),
				'i18n_confirm_appt_delete_all'            => __( '¿Seguro que quieres eliminar TODAS las citas pendientes?', 'pw-calendario' ),
				'i18n_confirm_appt_delete_past'           => __( '¿Seguro que quieres eliminar todas las citas pendientes que ya han pasado?', 'pw-calendario' ),
			);
		}

}
