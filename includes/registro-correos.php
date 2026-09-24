<?php
/**
 * Registro de correos.
 *
 * Un listado sencillo de cada correo que el plugin intenta mandar: cuándo,
 * a quién, de qué tipo y qué pasó con él. Sirve para contestar sin acceso
 * al servidor la pregunta que más se repite: «¿le llegó el correo?».
 *
 * Se anota en la misma puerta por la que pasa todo el correo del plugin
 * (`booked_mailer()` y el restablecimiento de contraseña), así que no hay
 * un envío que se quede fuera.
 *
 * Tres estados:
 *
 * - **Enviado**: WordPress lo ha entregado al servidor de correo. No
 *   garantiza que haya llegado a la bandeja, pero sí que ha salido.
 * - **Fallido**: `wp_mail()` ha devuelto un error.
 * - **Detenido**: los envíos están cerrados (ver includes/envios.php) y no
 *   se ha intentado.
 *
 * Se guarda en una opción sin carga automática y se conservan los últimos
 * PWCAL_REGISTRO_CORREOS_MAX. Para el volumen de una bodega sobra, y no
 * hace falta una tabla propia.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cuántos correos se recuerdan.
 */
const PWCAL_REGISTRO_CORREOS_MAX = 500;

/**
 * Tipos de correo que envía el plugin.
 *
 * La clave es el gancho por el que sale cada uno. Los de WooCommerce usan
 * ganchos propios, pero son la misma confirmación.
 *
 * @return array Gancho => array( 'etiqueta' => string, 'gestor' => bool ).
 */
function pwcal_tipos_correo() {

	$confirmacion = __( 'Confirmación de cita', 'pw-calendario' );
	$recordatorio = __( 'Recordatorio', 'pw-calendario' );
	$cancelacion  = __( 'Cancelación de cita', 'pw-calendario' );

	return apply_filters(
		'pwcal_tipos_correo',
		array(
			'booked_confirmation_email'          => array( 'etiqueta' => $confirmacion, 'gestor' => false ),
			'booked_wc_confirmation_email'       => array( 'etiqueta' => $confirmacion, 'gestor' => false ),
			'booked_admin_confirmation_email'    => array( 'etiqueta' => $confirmacion, 'gestor' => true ),
			'booked_wc_admin_confirmation_email' => array( 'etiqueta' => $confirmacion, 'gestor' => true ),
			'booked_reminder_email'              => array( 'etiqueta' => $recordatorio, 'gestor' => false ),
			'booked_admin_reminder_email'        => array( 'etiqueta' => $recordatorio, 'gestor' => true ),
			'booked_cancellation_email'          => array( 'etiqueta' => $cancelacion, 'gestor' => false ),
			'booked_admin_cancellation_email'    => array( 'etiqueta' => $cancelacion, 'gestor' => true ),
			'booked_approved_email'              => array( 'etiqueta' => __( 'Cita aprobada', 'pw-calendario' ), 'gestor' => false ),
			'booked_registration_email'          => array( 'etiqueta' => __( 'Registro', 'pw-calendario' ), 'gestor' => false ),
			'pwcal_contrasena'                   => array( 'etiqueta' => __( 'Restablecer contraseña', 'pw-calendario' ), 'gestor' => false ),
		)
	);
}

/**
 * Anota un correo en el registro.
 *
 * @param string|array $destino Destinatario.
 * @param string       $asunto  Asunto.
 * @param string       $tipo    Clave de pwcal_tipos_correo().
 * @param string       $estado  enviado | fallido | detenido.
 * @return void
 */
function pwcal_registrar_correo( $destino, $asunto, $tipo, $estado ) {

	$registro = get_option( 'pwcal_registro_correos', array() );

	if ( ! is_array( $registro ) ) {
		$registro = array();
	}

	array_unshift(
		$registro,
		array(
			'fecha'   => time(),
			'destino' => is_array( $destino ) ? implode( ', ', $destino ) : (string) $destino,
			'tipo'    => (string) $tipo,
			'asunto'  => wp_strip_all_tags( (string) $asunto ),
			'estado'  => (string) $estado,
		)
	);

	$registro = array_slice( $registro, 0, PWCAL_REGISTRO_CORREOS_MAX );

	update_option( 'pwcal_registro_correos', $registro, false );
}

/**
 * Registra la pantalla bajo el menú Citas, delante de «Novedades».
 *
 * @return void
 */
function pwcal_menu_registro_correos() {

	add_submenu_page(
		'pwcal-appointments',
		__( 'Registro de correos', 'pw-calendario' ),
		__( 'Registro de correos', 'pw-calendario' ),
		'edit_booked_appointments',
		'pwcal-registro-correos',
		'pwcal_pantalla_registro_correos',
		4
	);
}
add_action( 'admin_menu', 'pwcal_menu_registro_correos', 11 );

/**
 * Vacía el registro.
 *
 * @return void
 */
function pwcal_vaciar_registro_correos() {

	if ( ! current_user_can( 'manage_booked_options' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
	}

	check_admin_referer( 'pwcal_vaciar_registro_correos' );

	delete_option( 'pwcal_registro_correos' );

	wp_safe_redirect( admin_url( 'admin.php?page=pwcal-registro-correos&vaciado=1' ) );
	exit;
}
add_action( 'admin_post_pwcal_vaciar_registro_correos', 'pwcal_vaciar_registro_correos' );

/**
 * Muestra la pantalla del registro.
 *
 * @return void
 */
function pwcal_pantalla_registro_correos() {

	if ( ! current_user_can( 'edit_booked_appointments' ) ) {
		wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'pw-calendario' ), 403 );
	}

	$registro = get_option( 'pwcal_registro_correos', array() );
	$registro = is_array( $registro ) ? $registro : array();
	$tipos    = pwcal_tipos_correo();
	$estado   = pwcal_estado_envios();

	$estados = array(
		'enviado'  => array( __( 'Enviado', 'pw-calendario' ), '#00a32a' ),
		'fallido'  => array( __( 'Fallido', 'pw-calendario' ), '#d63638' ),
		'detenido' => array( __( 'Detenido', 'pw-calendario' ), '#996800' ),
	);
	?>
	<div class="wrap">

		<h1><?php esc_html_e( 'Registro de correos', 'pw-calendario' ); ?></h1>

		<?php if ( isset( $_GET['vaciado'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Registro vaciado.', 'pw-calendario' ); ?></p></div>
		<?php endif; ?>

		<p>
			<?php
			printf(
				/* translators: %d: número máximo de correos que se guardan. */
				esc_html__( 'Los correos que ha enviado el plugin, del más reciente al más antiguo. Se guardan los últimos %d.', 'pw-calendario' ),
				(int) PWCAL_REGISTRO_CORREOS_MAX
			);
			?>
			<?php esc_html_e( '«Enviado» significa que ha salido del sitio hacia el servidor de correo, no que haya llegado a la bandeja de entrada.', 'pw-calendario' ); ?>
		</p>

		<?php if ( empty( $estado['permitido'] ) ) : ?>
			<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Los envíos están detenidos ahora mismo.', 'pw-calendario' ); ?></strong> <?php echo esc_html( $estado['texto'] ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped" style="margin-top:1em;">
			<thead>
				<tr>
					<th scope="col" style="width:150px;"><?php esc_html_e( 'Fecha y hora', 'pw-calendario' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Destinatario', 'pw-calendario' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tipo', 'pw-calendario' ); ?></th>
					<th scope="col" style="width:90px;"><?php esc_html_e( 'Estado', 'pw-calendario' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $registro ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Todavía no se ha enviado ningún correo.', 'pw-calendario' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $registro as $fila ) : ?>
					<?php
					$tipo        = isset( $tipos[ $fila['tipo'] ] ) ? $tipos[ $fila['tipo'] ] : array( 'etiqueta' => __( 'Otro', 'pw-calendario' ), 'gestor' => false );
					$estado_fila = isset( $estados[ $fila['estado'] ] ) ? $estados[ $fila['estado'] ] : array( $fila['estado'], '#50575e' );
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) $fila['fecha'] ) ); ?></td>
						<td><?php echo esc_html( $fila['destino'] ); ?></td>
						<td>
							<?php echo esc_html( $tipo['etiqueta'] ); ?>
							<?php if ( $tipo['gestor'] ) : ?>
								<span style="color:#646970;"><?php esc_html_e( '(aviso al gestor)', 'pw-calendario' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $fila['asunto'] ) : ?>
								<br><span style="color:#646970;font-size:12px;"><?php echo esc_html( $fila['asunto'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><strong style="color:<?php echo esc_attr( $estado_fila[1] ); ?>;"><?php echo esc_html( $estado_fila[0] ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $registro && current_user_can( 'manage_booked_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1.5em;" onsubmit="return confirm('<?php echo esc_js( __( '¿Vaciar el registro de correos? No se puede deshacer.', 'pw-calendario' ) ); ?>');">
				<input type="hidden" name="action" value="pwcal_vaciar_registro_correos">
				<?php wp_nonce_field( 'pwcal_vaciar_registro_correos' ); ?>
				<?php submit_button( __( 'Vaciar el registro', 'pw-calendario' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

	</div>
	<?php
}
