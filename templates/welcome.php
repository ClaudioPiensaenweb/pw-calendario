<?php
/**
 * Pantalla de novedades del plugin.
 *
 * @package Pw_Calendario
 */

// Salir si se accede directamente.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Los enlaces al servicio de soporte externo que venían aquí se han
// retirado: apuntaban a un tercero ajeno a este plugin. En su lugar se
// enlaza a las propias pantallas de configuración.
?>
<div id="booked-welcome-screen">
	<div class="wrap about-wrap">
		<div id="welcome-panel" class="welcome-panel">

			<img
				src="<?php echo esc_url( PWCAL_PLUGIN_URL . '/assets/images/banner-bienvenida.svg' ); ?>"
				alt="<?php esc_attr_e( 'Pw Calendario', 'pw-calendario' ); ?>"
				class="booked-welcome-banner">

			<div class="welcome-panel-intro">
				<h1>
					<?php
					printf(
						/* translators: %s: nombre del plugin. */
						esc_html__( 'Gracias por elegir %s.', 'pw-calendario' ),
						'Pw Calendario'
					);
					?>
				</h1>
				<p>
					<?php
					printf(
						/* translators: %s: nombre del plugin. */
						esc_html__( 'Si es la primera vez que usas %s, abajo tienes los primeros pasos para dejarlo configurado. Si acabas de actualizarlo, a la derecha verás qué ha cambiado.', 'pw-calendario' ),
						'Pw Calendario'
					);
					?>
				</p>
			</div>

			<div class="welcome-panel-content">
				<div class="welcome-panel-column-container">

					<div class="welcome-panel-column">
						<h3><?php esc_html_e( 'Primeros pasos', 'pw-calendario' ); ?></h3>
						<ol>
							<li>
								<?php
								printf(
									/* translators: %s: shortcode del calendario. */
									esc_html__( 'Crea una página y añade el shortcode %s para mostrar el calendario de reservas.', 'pw-calendario' ),
									'<code>[booked-calendar]</code>'
								);
								?>
							</li>
							<li>
								<?php
								printf(
									/* translators: %s: shortcode del perfil. */
									esc_html__( 'Crea otra página con el shortcode %s para que los clientes consulten y cancelen sus citas.', 'pw-calendario' ),
									'<code>[booked-profile]</code>'
								);
								?>
							</li>
							<li>
								<?php esc_html_e( 'Define las franjas horarias predeterminadas de cada día de la semana en los ajustes.', 'pw-calendario' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Si atiendes en varios espacios o con varias personas, crea un calendario personalizado para cada uno y asígnale su responsable.', 'pw-calendario' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Revisa los textos de los correos de confirmación, aprobación, cancelación y recordatorio.', 'pw-calendario' ); ?>
							</li>
						</ol>

						<p>
							<a class="button button-primary" style="margin-bottom:15px; margin-top:0;"
								href="<?php echo esc_url( admin_url( 'admin.php?page=pwcal-settings' ) ); ?>">
								<?php esc_html_e( 'Ir a los ajustes', 'pw-calendario' ); ?>
							</a>
							&nbsp;
							<a class="button" style="margin-bottom:15px; margin-top:0;"
								href="<?php echo esc_url( admin_url( 'admin.php?page=pwcal-appointments' ) ); ?>">
								<?php esc_html_e( 'Ver el calendario', 'pw-calendario' ); ?>
							</a>
						</p>
					</div>

					<div class="welcome-panel-column welcome-panel-last">
						<?php do_action( 'booked_welcome_before_changelog' ); ?>
						<?php
						// El contenido proviene del readme.txt local del plugin.
						echo wp_kses_post( booked_parse_readme_changelog() );
						?>
						<?php do_action( 'booked_welcome_after_changelog' ); ?>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>
