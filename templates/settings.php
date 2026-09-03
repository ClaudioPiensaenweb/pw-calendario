<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Salir si se accede directamente. ?>
<div class="booked-settings-prewrap">
	<div class="wrap booked-settings-wrap"><?php

	if (get_transient('booked_show_new_tags',false)):
		$show_new_tags = true;
	else:
		$show_new_tags = false;
	endif;

	$calendars = get_terms('booked_custom_calendars','orderby=slug&hide_empty=0');
	$booked_none_assigned = true;
	$default_calendar_id = false;

	if (!empty($calendars)):

		if (!current_user_can('manage_booked_options')):

			$booked_current_user = wp_get_current_user();
			$calendars = booked_filter_agent_calendars($booked_current_user,$calendars);

			if (empty($calendars)):
				$booked_none_assigned = true;
			else:
				$first_calendar = array_slice($calendars, 0, 1);
				$default_calendar_id = array_shift($first_calendar)->term_id;
				$booked_none_assigned = false;
			endif;

		else:
			$booked_none_assigned = false;
		endif;

	endif;

	if (!current_user_can('manage_booked_options') && $booked_none_assigned):

		echo '<div style="text-align:center;">';
			echo '<br><br><h3>'.esc_html__('No tienes ningún calendario asignado.','pw-calendario').'</h3>';
			echo '<p>'.esc_html__('Ponte en contacto con la administración del sitio para que te asignen un calendario.','pw-calendario').'</p>';
		echo '</div>';

	else: ?>

		<div class="topSavingState savingState"><i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i>&nbsp;&nbsp;<?php esc_html_e('Actualizando, espera un momento…','pw-calendario'); ?></div>

		<div class="booked-settings-title"><?php echo esc_html__('Ajustes de Pw Calendario','pw-calendario'); ?></div>

		<div id="booked-admin-panel-container">

			<?php $booked_settings_tabs = [];
			
			$booked_settings_tabs[] = [
				'access' => 'admin',
				'slug' => 'general',
				'content' => '<i class="booked-icon booked-icon-gear"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('General','pw-calendario') . '</span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'agent',
				'slug' => 'defaults',
				'content' => '<i class="booked-icon booked-icon-clock"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Franjas horarias','pw-calendario') . '</span><span class="savingState">&nbsp;&nbsp;&nbsp;<i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i></span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'agent',
				'slug' => 'custom-timeslots',
				'content' => '<i class="booked-icon booked-icon-clock"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Franjas horarias personalizadas','pw-calendario') . '</span><span class="savingState">&nbsp;&nbsp;&nbsp;<i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i></span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'agent',
				'slug' => 'custom-fields',
				'content' => '<i class="booked-icon booked-icon-pencil"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Campos personalizados','pw-calendario') . '</span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'admin',
				'slug' => 'email-settings',
				'content' => '<i class="booked-icon booked-icon-email"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Correos','pw-calendario') . '</span>'
			];
			
			if ( class_exists('woocommerce') ):
			
				$booked_settings_tabs[] = [
					'access' => 'admin',
					'slug' => 'woocommerce-settings',
					'content' => '<i class="booked-icon booked-icon-cart"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('WooCommerce','pw-calendario') . '</span>'
				];
				
			endif;
			
			$booked_settings_tabs[] = [
				'access' => 'admin',
				'slug' => 'calendar-feeds',
				'content' => '<i class="booked-icon booked-icon-calendar"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Feeds de calendario','pw-calendario') . '</span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'admin',
				'slug' => 'export-appointments',
				'content' => '<i class="booked-icon booked-icon-sign-out"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Exportar','pw-calendario') . '</span>'
			];
			
			$booked_settings_tabs[] = [
				'access' => 'admin',
				'slug' => 'shortcodes',
				'content' => '<i class="booked-icon booked-icon-code"></i><span class="booked-tab-text">&nbsp;&nbsp;'.esc_html__('Shortcodes','pw-calendario') . '</span>'
			];
			
			$booked_settings_tabs = apply_filters( 'booked_settings_tabs', $booked_settings_tabs );

			$tab_counter = 1;

			$new_items_in_tabs = array();

			foreach($booked_settings_tabs as $tab_data):
				if ($tab_data['access'] == 'admin' && current_user_can('manage_booked_options') || $tab_data['access'] == 'agent'):
					if ($tab_counter == 1): ?><ul class="booked-admin-tabs bookedClearFix"><?php endif;
					?><li<?php if ($tab_counter == 1): ?> class="active"<?php endif; ?>><a href="#<?php echo $tab_data['slug']; ?>"><?php echo $tab_data['content']; ?><?php if (in_array($tab_data['slug'],$new_items_in_tabs)): booked_new_tag($show_new_tags); endif; ?></a></li><?php
					$tab_counter++;
				endif;
			endforeach;

			?></ul>

			<div class="form-wrapper">
				
				<?php foreach($booked_settings_tabs as $tab_data):

					if ($tab_data['access'] == 'admin' && current_user_can('manage_booked_options') || $tab_data['access'] == 'agent'):

						switch ($tab_data['slug']):

							case 'general': ?>

								<form action="options.php" class="booked-settings-form" method="post">

									<?php settings_fields('booked_plugin-group'); ?>

									<div id="booked-general" class="tab-content">

										<h1 style="display:none;"></h1>

										<?php settings_errors(); ?>

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Tipo de reserva', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Puedes elegir entre reserva «con registro» o «como invitado». Con registro, solo los usuarios registrados pueden reservar (opción predeterminada). Como invitado, cualquiera con un nombre y un correo electrónico puede reservar.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_booking_type';
												$booking_type = get_option($option_name,'registered'); ?>
												<div class="select-box">
													<select data-condition="booking_type" name="<?php echo $option_name; ?>">
														<option value="registered"<?php echo ($booking_type == 'registered' ? ' selected="selected"' : ''); ?>><?php esc_html_e('Reserva con registro','pw-calendario'); ?></option>
														<option value="guest"<?php echo ($booking_type == 'guest' ? ' selected="selected"' : ''); ?>><?php esc_html_e('Reserva como invitado','pw-calendario'); ?></option>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<?php $selected_value = get_option('booked_registration_name_requirements',array('booked_require_name')); $selected_value = (isset($selected_value[0]) ? $selected_value[0] : false); ?>
										<div class="section-row">
											<div class="section-head">

											<?php $section_title = esc_html__('Opciones de reserva', 'pw-calendario'); ?>
											<h3><?php echo esc_attr($section_title); ?></h3>

											<p style="margin:1.2em 0 10px;">
												<input style="margin:-5px 5px 0 0;" id="booked_require_name" name="booked_registration_name_requirements[]" value="require_name"<?php if (!$selected_value || $selected_value == 'require_name'): echo ' checked="checked"'; endif; ?> type="radio">
												<label class="checkbox-radio-label" for="booked_require_name"><strong><?php esc_html_e('Exigir solo el nombre','pw-calendario'); ?></strong> &mdash; <?php esc_html_e('Exige a tus clientes que escriban su nombre en un solo campo.','pw-calendario'); ?></label><br>
											</p>
											<p style="margin:0 0 10px;">
												<input style="margin:-5px 5px 0 0;" id="booked_require_surname" name="booked_registration_name_requirements[]" value="require_surname"<?php if ($selected_value == 'require_surname'): echo ' checked="checked"'; endif; ?> type="radio">
												<label class="checkbox-radio-label" for="booked_require_surname"><strong><?php esc_html_e('Exigir nombre y apellidos','pw-calendario'); ?></strong> &mdash; <?php esc_html_e('Exige a tus clientes que escriban el nombre y los apellidos en dos campos separados.','pw-calendario'); ?></label><br>
											</p>

											</div>
										</div>

										<?php $selected_value = get_option('booked_require_guest_email_address',false); ?>
										<div class="condition-block booking_type" data-condition-val="guest" style="<?php if ($booking_type == 'guest'): ?>display:block; <?php endif; ?>">
											<div class="section-row">
												<div class="section-head">

												<?php $section_title = esc_html__('Opciones de reserva como invitado', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>

												<p style="margin:1.2em 0 10px;">
													<input style="margin:-4px 5px 0 0;" id="booked_require_guest_email_address" name="booked_require_guest_email_address" value="true"<?php if ($selected_value): echo ' checked="checked"'; endif; ?> type="checkbox">
													<label class="checkbox-radio-label" for="booked_require_guest_email_address"><strong><?php esc_html_e('Exigir el correo electrónico','pw-calendario'); ?></strong> &mdash; <?php esc_html_e('Exige a los invitados que indiquen su correo electrónico.','pw-calendario'); ?></label>
												</p>

												</div>
											</div>
										</div>

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Redirección al reservar', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>

												<?php $option_name = 'booked_appointment_redirect_type'; $selected_value = get_option($option_name,false);

												$booked_redirect_type = $selected_value;

												$detected_page_error = false;
												$detected_page = booked_get_profile_page();
												if (!$detected_page):
													$detected_page_error = true;
												endif; ?>

												<p style="margin:1.2em 0 10px;"><input style="margin:-4px 5px 0 0;" data-condition="redirect_type" id="redirect_type_none" name="<?php echo $option_name; ?>" value=""<?php if (!$selected_value): echo ' checked="checked"'; endif; ?> type="radio">
												<label class="checkbox-radio-label" for="redirect_type_none"><?php echo sprintf( esc_html__('%s Recargar el listado del calendario después de reservar.','pw-calendario'), '<strong>' . esc_html__('Sin redirección','pw-calendario') . '</strong> &mdash; ' ); ?></label></p>

												<div class="condition-block booking_type" data-condition-val="registered" style="<?php if ($booking_type == 'registered'): ?>display:block; <?php endif; ?>">
													<p style="margin:0 0 10px;">
														<input style="margin:-4px 5px 0 0;" data-condition="redirect_type" id="redirect_type_detect" name="<?php echo $option_name; ?>" value="booked-profile"<?php if ($selected_value == 'booked-profile'): echo ' checked="checked"'; endif; ?> type="radio">
														<label class="checkbox-radio-label" for="redirect_type_detect"><?php echo sprintf( esc_html__('%s Detectar automáticamente la página que contiene el shortcode [booked-profile].','pw-calendario'), '<strong>' . esc_html__('Detectar la página de perfil','pw-calendario') . '</strong> &mdash; ' ); ?><?php if (!$detected_page_error && $detected_page): ?>&nbsp;&nbsp;&mdash;&nbsp;&nbsp;<strong><?php echo sprintf(esc_html__('Página detectada: %s','pw-calendario'),'<a href="'.get_permalink($detected_page).'">'.get_permalink($detected_page).'</a>'); ?></strong><?php endif; ?></label>
													</p>
												</div>

												<?php if ($detected_page_error): ?>
												<div style="margin:0 0 10px;">
													<div class="condition-block redirect_type" data-condition-val="booked-profile" style="<?php if ($booked_redirect_type == 'booked-profile'): ?>display:block; <?php endif; ?>line-height:30px; padding:0 0 0 30px; margin:-5px 0 10px;"><?php echo sprintf(esc_html__( '%s No se ha podido detectar automáticamente. Tienes que %s con el shortcode %s.','pw-calendario' ),'<strong style="color:#DB5933;">'.esc_html__('Importante:','pw-calendario').'</strong>','<strong><a href="'.get_admin_url().'post-new.php?post_type=page">'.esc_html__('crear una página','pw-calendario').'</a></strong>','<code>[booked-profile]</code>'); ?></div>
												</div>
												<?php endif; ?>

												<p style="margin:0;">
													<input style="margin:-4px 5px 0 0;" data-condition="redirect_type" id="redirect_type_page" name="<?php echo $option_name; ?>" value="page"<?php if ($selected_value == 'page'): echo ' checked="checked"'; endif; ?> type="radio">
													<label class="checkbox-radio-label" for="redirect_type_page"><?php echo sprintf( esc_html__('%s Elegir una página de destino.','pw-calendario'), '<strong>' . esc_html__('Elegir una página concreta','pw-calendario') . '</strong> &mdash; ' ); ?></label>
												</p>

												<?php $option_name = 'booked_appointment_success_redirect_page';

												$pages = get_posts(array(
													'post_type' => 'page',
													'orderby'	=> 'name',
													'order'		=> 'asc',
													'posts_per_page' => 500
												));

												$selected_value = get_option($option_name); ?>
												<div style="padding:15px 0 0 0;" class="condition-block redirect_type select-box<?php if ($booked_redirect_type == 'page'): ?> default<?php endif; ?>" data-condition-val="page">
													<select name="<?php echo $option_name; ?>">
														<option value=""<?php echo (!$selected_value ? ' selected="selected"' : ''); ?>><?php echo esc_html__('Elige una página','pw-calendario').'...'; ?></option>
														<?php if(!empty($pages)) :

															foreach($pages as $p) :
																$entry_id = $p->ID;
																$entry_title = get_the_title($entry_id); ?>
																<option value="<?php echo $entry_id; ?>"<?php echo ($selected_value == $entry_id ? ' selected="selected"' : ''); ?>><?php echo $entry_title; ?></option>
															<?php endforeach;

														endif; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="condition-block booking_type<?php if ($booking_type == 'registered'): ?> default<?php endif; ?>" data-condition-val="registered">

											<div class="section-row">
												<div class="section-head">
													<?php $section_title = esc_html__('Redirección tras el acceso', 'pw-calendario'); ?>
													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Si quieres que el formulario de acceso lleve a otra página en lugar de recargar la actual, elígela aquí.','pw-calendario'); ?></p>

													<?php $option_name = 'booked_login_redirect_page';

													$pages = get_posts(array(
														'post_type' => 'page',
														'orderby'	=> 'name',
														'order'		=> 'asc',
														'posts_per_page' => 500
													));

													$selected_value = get_option($option_name); ?>
													<div class="select-box">
														<select name="<?php echo $option_name; ?>">
															<option value=""><?php esc_html_e('Recargar la misma página','pw-calendario'); ?></option>
															<?php if(!empty($pages)) :
																foreach($pages as $p) :
																	$entry_id = $p->ID;
																	$entry_title = get_the_title($entry_id); ?>
																	<option value="<?php echo $entry_id; ?>"<?php echo ($selected_value == $entry_id ? ' selected="selected"' : ''); ?>><?php echo $entry_title; ?></option>
																<?php endforeach;

															endif; ?>
														</select>
													</div><!-- /.select-box -->
												</div><!-- /.section-body -->
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $section_title = esc_html__('Contenido de la pestaña de acceso', 'pw-calendario'); ?>
													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Si quieres que el formulario de acceso muestre un mensaje personalizado encima, escríbelo aquí.','pw-calendario'); ?></p>

													<?php $option_name = 'booked_custom_login_message';
													$custom_content_value = get_option($option_name);

													wp_editor( $custom_content_value, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 250,'teeny' => true) );

													?>
												</div><!-- /.section-body -->
											</div><!-- /.section-row -->

										</div>

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Intervalos de las franjas horarias', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Elige los intervalos que necesitas para las franjas horarias. Solo afecta a la forma de introducir las franjas predeterminadas.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_timeslot_intervals';
												$selected_value = get_option( $option_name, 5 );

												$interval_options = apply_filters( 'booked_timeslot_interval_sizes', array(
													'120'	=> esc_html__('Cada 2 horas','pw-calendario'),
													'60' 	=> esc_html__('Cada hora','pw-calendario'),
													'30' 	=> esc_html__('Cada 30 minutos','pw-calendario'),
													'15' 	=> esc_html__('Cada 15 minutos','pw-calendario'),
													'10' 	=> esc_html__('Cada 10 minutos','pw-calendario'),
													'5' 	=> esc_html__('Cada 5 minutos','pw-calendario'),
												) ); ?>

												<div class="select-box">
													<select name="<?php echo $option_name; ?>">
														<?php foreach($interval_options as $current_value => $option_title):
															echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
														endforeach; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Margen de antelación', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Para evitar que se reserven citas con muy poca antelación, puedes establecer un margen. Las franjas disponibles se desplazarán según el margen que elijas aquí abajo.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_appointment_buffer';
												$selected_value = get_option($option_name);

												$interval_options = array(
													'0' 				=> esc_html__('Sin margen','pw-calendario'),
													'1' 				=> esc_html__('1 hora','pw-calendario'),
													'2' 				=> esc_html__('2 horas','pw-calendario'),
													'3' 				=> esc_html__('3 horas','pw-calendario'),
													'4' 				=> esc_html__('4 horas','pw-calendario'),
													'5' 				=> esc_html__('5 horas','pw-calendario'),
													'6' 				=> esc_html__('6 horas','pw-calendario'),
													'12' 				=> esc_html__('12 horas','pw-calendario'),
													'24' 				=> esc_html__('24 horas','pw-calendario'),
													'48' 				=> esc_html__('2 días','pw-calendario'),
													'72' 				=> esc_html__('3 días','pw-calendario'),
													'96' 				=> esc_html__('5 días','pw-calendario'),
													'144' 				=> esc_html__('6 días','pw-calendario'),
													'168' 				=> esc_html__('1 semana','pw-calendario'),
													'336' 				=> esc_html__('2 semanas','pw-calendario'),
													'504' 				=> esc_html__('3 semanas','pw-calendario'),
													'672' 				=> esc_html__('4 semanas','pw-calendario'),
													'840' 				=> esc_html__('5 semanas','pw-calendario'),
													'1008' 				=> esc_html__('6 semanas','pw-calendario'),
													'1176' 				=> esc_html__('7 semanas','pw-calendario'),
													'1344' 				=> esc_html__('8 semanas','pw-calendario'),
												); ?>

												<div class="select-box">
													<select name="<?php echo $option_name; ?>">
														<?php foreach($interval_options as $current_value => $option_title):
															echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
														endforeach; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<?php $date_format = get_option('date_format'); ?>

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Impedir citas antes de una fecha', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Para impedir que se reserven citas antes de una fecha concreta, indícala aquí abajo.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_prevent_appointments_before';
												$selected_value = get_option($option_name); ?>

												<div class="select-box">
													<input type="text" placeholder="<?php esc_html_e("Elige una fecha",'pw-calendario'); ?>..." class="booked_prevent_appointments_before" name="<?php echo $option_name; ?>" value="<?php echo $selected_value; ?>">
													<span class="<?php echo $option_name; ?>-formatted" style="padding-left:15px; font-weight:600; font-size:15px;"><?php echo ( $selected_value ? ucwords( date_i18n( $date_format,strtotime($selected_value) ) ) : '' ); ?></span>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Impedir citas después de una fecha', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Para impedir que se reserven citas después de una fecha concreta, indícala aquí abajo.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_prevent_appointments_after';
												$selected_value = get_option($option_name); ?>

												<div class="select-box">
													<input type="text" placeholder="<?php esc_html_e("Elige una fecha",'pw-calendario'); ?>..." class="booked_prevent_appointments_after" name="<?php echo $option_name; ?>" value="<?php echo $selected_value; ?>">
													<span class="<?php echo $option_name; ?>-formatted" style="padding-left:15px; font-weight:600; font-size:15px;"><?php echo ( $selected_value ? ucwords( date_i18n( $date_format,strtotime($selected_value) ) ) : '' ); ?></span>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Margen de cancelación', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Para evitar que se cancelen citas con muy poca antelación, puedes establecer un margen de cancelación.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_cancellation_buffer';
												$selected_value = get_option($option_name);

												$interval_options = array(
													'0' 				=> esc_html__('Sin margen','pw-calendario'),
													'0.25' 				=> esc_html__('15 minutos','pw-calendario'),
													'0.50' 				=> esc_html__('30 minutos','pw-calendario'),
													'0.75' 				=> esc_html__('45 minutos','pw-calendario'),
													'1' 				=> esc_html__('1 hora','pw-calendario'),
													'2' 				=> esc_html__('2 horas','pw-calendario'),
													'3' 				=> esc_html__('3 horas','pw-calendario'),
													'4' 				=> esc_html__('4 horas','pw-calendario'),
													'5' 				=> esc_html__('5 horas','pw-calendario'),
													'6' 				=> esc_html__('6 horas','pw-calendario'),
													'12' 				=> esc_html__('12 horas','pw-calendario'),
													'24' 				=> esc_html__('24 horas','pw-calendario'),
													'48' 				=> esc_html__('2 días','pw-calendario'),
													'72' 				=> esc_html__('3 días','pw-calendario'),
													'96' 				=> esc_html__('5 días','pw-calendario'),
													'144' 				=> esc_html__('6 días','pw-calendario'),
													'168' 				=> esc_html__('1 semana','pw-calendario'),
													'336' 				=> esc_html__('2 semanas','pw-calendario'),
													'504' 				=> esc_html__('3 semanas','pw-calendario'),
													'672' 				=> esc_html__('4 semanas','pw-calendario'),
													'840' 				=> esc_html__('5 semanas','pw-calendario'),
													'1008' 				=> esc_html__('6 semanas','pw-calendario'),
													'1176' 				=> esc_html__('7 semanas','pw-calendario'),
													'1344' 				=> esc_html__('8 semanas','pw-calendario'),
												); ?>

												<div class="select-box">
													<select name="<?php echo $option_name; ?>">
														<?php foreach($interval_options as $current_value => $option_title):
															echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
														endforeach; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Límite de citas', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('Para evitar que un usuario reserve demasiadas citas, puedes establecer un límite.','pw-calendario'); ?></p>

												<?php $option_name = 'booked_appointment_limit';
												$selected_value = get_option($option_name);

												$interval_options = array(
													'0' 				=> esc_html__('Sin límite','pw-calendario'),
													'1' 				=> esc_html__('1 cita','pw-calendario'),
													'2' 				=> esc_html__('2 citas','pw-calendario'),
													'3' 				=> esc_html__('3 citas','pw-calendario'),
													'4' 				=> esc_html__('4 citas','pw-calendario'),
													'5' 				=> esc_html__('5 citas','pw-calendario'),
													'6' 				=> esc_html__('6 citas','pw-calendario'),
													'7' 				=> esc_html__('7 citas','pw-calendario'),
													'8' 				=> esc_html__('8 citas','pw-calendario'),
													'9' 				=> esc_html__('9 citas','pw-calendario'),
													'10' 				=> esc_html__('10 citas','pw-calendario'),
													'15' 				=> esc_html__('15 citas','pw-calendario'),
													'20' 				=> esc_html__('20 citas','pw-calendario'),
													'25' 				=> esc_html__('25 citas','pw-calendario'),
													'50' 				=> esc_html__('50 citas','pw-calendario'),
												); ?>

												<div class="select-box">
													<select name="<?php echo $option_name; ?>">
														<?php foreach($interval_options as $current_value => $option_title):
															echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
														endforeach; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Estado inicial de las citas nuevas', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3>
												<p><?php esc_html_e('¿Prefieres que las solicitudes de cita queden pendientes de aprobación o que se aprueben al instante?','pw-calendario'); ?></p>

												<?php $option_name = 'booked_new_appointment_default';
												$selected_value = get_option($option_name);

												$interval_options = array(
													'draft' 	=> esc_html__('Marcar como pendiente','pw-calendario'),
													'publish' 	=> esc_html__('Aprobar al instante','pw-calendario')
												); ?>

												<div class="select-box">
													<select name="<?php echo $option_name; ?>">
														<?php foreach($interval_options as $current_value => $option_title):
															echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
														endforeach; ?>
													</select>
												</div><!-- /.select-box -->
											</div><!-- /.section-body -->
										</div><!-- /.section-row -->

										<div class="section-row cf">
											<div class="section-head">

												<h3><?php esc_html_e('Opciones de visualización', 'pw-calendario'); ?></h3><?php // TODO - WIP ?>

												<br>

												<?php $option_name = 'booked_hide_default_calendar';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar «Predeterminado» en el selector de calendarios','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_weekends';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar los fines de semana en el calendario','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_google_link';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar el botón «Añadir al calendario» en el listado de citas del perfil','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_show_only_titles';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar las franjas horarias cuando tengan título','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_end_times';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar las horas de fin (mostrar solo las de inicio)','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_available_timeslots';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar el número de plazas disponibles','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_unavailable_timeslots';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar las franjas ya reservadas (no se puede usar con «Citas públicas»)','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_public_appointments';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Citas públicas (mostrar los nombres bajo las citas reservadas)','pw-calendario'); ?></label><br><br>

											</div>
										</div>

										<div class="section-row cf">
											<div class="section-head">

												<h3><?php esc_html_e('Otras opciones', 'pw-calendario'); ?></h3><?php // TODO - WIP ?>

												<br>

												<?php $option_name = 'booked_dont_allow_user_cancellations';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('No permitir que los usuarios cancelen sus propias citas.','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_redirect_non_admins';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Redirigir a los usuarios fuera de «/wp-admin/», salvo administradores y gestores de citas.','pw-calendario'); ?></label><br><br>

												<?php $option_name = 'booked_hide_admin_bar_menu';
												$option_value = get_option($option_name,false); ?>

												<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>"<?php echo $option_value ? ' checked="checked"' : ''; ?> type="checkbox">
												<label class="checkbox-radio-label" for="<?php echo $option_name; ?>"><?php esc_html_e('Ocultar el menú «Citas» de la barra de administración.','pw-calendario'); ?></label>

											</div>
										</div><!-- /.section-row -->

										<div class="section-row">
											<div class="section-head">
												<?php $section_title = esc_html__('Colores del front-end', 'pw-calendario'); ?>
												<h3><?php echo esc_attr($section_title); ?></h3><?php // TODO - WIP ?>
											</div><!-- /.section-head -->
											<div class="section-body">

												<?php
												$color_options = array(
													array(
														'name' => 'booked_light_color',
														'title' => 'Light Color',
														'val' => get_option('booked_light_color','#0073AA'),
														'default' => '#0073AA'
													),
													array(
														'name' => 'booked_dark_color',
														'title' => 'Dark Color',
														'val' => get_option('booked_dark_color','#015e8c'),
														'default' => '#015e8c'

													),
													array(
														'name' => 'booked_button_color',
														'title' => 'Primary Button Color',
														'val' => get_option('booked_button_color','#56C477'),
														'default' => '#56C477'

													),
												);

												foreach($color_options as $color_option):

													echo '<label class="booked-color-label" for="'.$color_option['name'].'">'.$color_option['title'].'</label>';
													echo '<input data-default-color="'.$color_option['default'].'" type="text" name="'.$color_option['name'].'" value="'.$color_option['val'].'" id="'.$color_option['name'].'" class="booked-color-field" />';

												endforeach;
												?>

											</div><!-- /.section-body -->
										</div>

										<div class="section-row submit-section" style="padding:0;">
											<?php @submit_button(); ?>
										</div><!-- /.section-row -->

									</div>

									<div id="booked-email-settings" class="tab-content">

										<div class="section-row">
											<div class="section-head">
												<p style="background:#fff; padding:13px 19px 12px; border-left:3px solid #aaa; -moz-border-radius:3px; -webkit-border-radius:3px; border-radius:3px; box-shadow:0 1px 3px rgba(0,0,0,0.10); margin:0; font-size:15px; line-height:1.6;"><?php esc_html_e('Si NO quieres enviar correo para alguna de las acciones de abajo, deja vacío el asunto o el contenido (o ambos) y no se enviará ese aviso.','pw-calendario'); ?></p>
											</div>
										</div>

										<?php $email_template_tabs = apply_filters( 'booked_admin_email_template_tabs', array(
											'customer-emails' => esc_html__('Correos para clientes','pw-calendario'),
											'admin-emails' => esc_html__('Correos para gestores','pw-calendario'),
											'email-settings' => esc_html__('Ajustes','pw-calendario')
										));

										$tab_counter = 0; ?>

										<?php do_action( 'booked_admin_before_email_tabs' ); ?>

										<ul class="booked-admin-subtabs bookedClearFix">
											<?php foreach( $email_template_tabs as $tab_name => $tab_text ): $tab_counter++; ?>
												<li<?php if ( $tab_counter == 1): ?> class="active"<?php endif; ?>><a href="#<?php echo $tab_name; ?>"><?php echo $tab_text; ?></a></li>
											<?php endforeach; ?>
										</ul>

										<?php do_action( 'booked_admin_after_email_tabs' ); ?>

										<?php do_action( 'booked_admin_before_email_tab_content' ); ?>

										<div id="booked-subtab-email-settings" class="subtab-content">

											<div class="section-row">
												<div class="section-head"><?php

													$option_name = 'booked_email_logo';
													$booked_email_logo = get_option($option_name);
													$section_title = esc_html__('Imagen de cabecera o logotipo', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Elige una imagen para tus correos personalizados. Para que se vea bien, que no supere los 600 px.','pw-calendario'); ?></p>

													<input id="<?php echo $option_name; ?>" name="<?php echo $option_name; ?>" value="<?php echo $booked_email_logo; ?>" type="hidden" />
													<input id="booked_email_logo_button" class="button button-primary" name="booked_email_logo_button" type="button" value="<?php esc_html_e('Subir el logotipo','pw-calendario'); ?>" />

													<input id="booked_email_logo_button_remove"<?php echo ( !$booked_email_logo ? ' style="display:none;"' : '' ); ?> class="button" name="booked_email_logo_button_remove" type="button" value="<?php esc_html_e('Quitar','pw-calendario'); ?>" />
													<img src="<?php echo $booked_email_logo; ?>"<?php echo ( !$booked_email_logo ? ' style="display:none;"' : '' ); ?> id="booked_email_logo-img">

												</div>
											</div>

											<div class="section-row">
												<div class="section-head">
													<?php $section_title = esc_html__('¿Qué administrador o gestor de citas debe recibir los avisos por correo por defecto?', 'pw-calendario'); ?>
													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Por defecto se usa la dirección de «Ajustes > Generales > Dirección de correo electrónico». Además, cada calendario personalizado puede tener su propio destinatario; esto es solo el valor por defecto.','pw-calendario'); ?></p>

													<?php $option_name = 'booked_default_email_user';

													$allowed_users = get_users( array( 'role__in' => array( 'administrator', 'booked_booking_agent' ) ) );

													$selected_value = get_option($option_name); ?>
													<div class="select-box">
														<select name="<?php echo $option_name; ?>">
															<option value=""><?php esc_html_e('Elige el usuario que recibe los avisos por defecto','pw-calendario'); ?> ...</option>
															<?php if(!empty($allowed_users)) :
																foreach($allowed_users as $u) :
																	$user_id = $u->ID;
																	$email = $u->data->user_email;
																	$display_name = ( isset( $u->data->display_name ) && $u->data->display_name ? $u->data->display_name . ' (' . $email .')' : $email ); ?>
																	<option value="<?php echo esc_attr( $email ); ?>"<?php echo ($selected_value == $email ? ' selected="selected"' : ''); ?>><?php echo esc_html( $display_name ); ?></option>
																<?php endforeach;

															endif; ?>
														</select>
													</div><!-- /.select-box -->
												</div><!-- /.section-body -->
											</div><!-- /.section-row -->

											<?php $selected_value = get_option('booked_email_force_sender',false); ?>
											<?php $selected_email = get_option('booked_email_force_sender_from',false); ?>
											<?php $selected_booked_mailer = get_option('booked_emailer_disabled',false); ?>

											<div class="section-row">
												<div class="section-head">

													<h3><?php echo esc_html__('¿Problemas con los correos?', 'pw-calendario'); ?></h3>
													<p style="margin-bottom:2.5em;"><?php echo sprintf( esc_html__('Prueba con un plugin de SMTP como %s o %s','pw-calendario'), '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a>', '<a href="https://wordpress.org/plugins/easy-wp-smtp/" target="_blank">Easy WP SMTP</a>' ); ?></p>

													<h3><?php echo esc_html__('¿Los correos solo fallan al enviarse a administradores y gestores?', 'pw-calendario'); ?></h3>
													<p><?php esc_html_e('Algunos servidores SMTP rechazan el correo enviado «en nombre de» tus clientes. Google, por ejemplo, cambia el nombre del remitente para evitar el rechazo; otros no. Puedes marcar la opción de «Forzar el remitente», pero entonces no podrás responder directamente a los avisos que lleguen de los clientes.','pw-calendario'); ?></p>

													<p style="margin:1.2em 0 15px;">
														<input data-condition="force_sender" style="margin:-4px 5px 0 0;" id="booked_email_force_sender" name="booked_email_force_sender" value="true"<?php if ($selected_value): echo ' checked="checked"'; endif; ?> type="checkbox">
														<label class="checkbox-radio-label" for="booked_email_force_sender"><strong><?php esc_html_e("Forzar el remitente", 'pw-calendario'); ?></strong></label>
													</p>

													<p class="condition-block force_sender"<?php echo ( $selected_value ? ' style="display:block;"' : '' ); ?>>
														<input style="margin:0" name="booked_email_force_sender_from" value="<?php echo ( $selected_email ? $selected_email : get_option('admin_email') ); ?>" type="text" class="field">
													</p>

													<h3 style="margin-top:2em;"><?php echo esc_html__('¿Sigue sin funcionar?', 'pw-calendario'); ?></h3>
													<p><?php esc_html_e('Si sigues teniendo problemas, marca la casilla de abajo para desactivar el envío propio del plugin y que WordPress gestione todo el correo.','pw-calendario'); ?></p>

													<p style="margin:1.2em 0 0;">
														<input style="margin:-4px 5px 0 0;" id="booked_emailer_disabled" name="booked_emailer_disabled" value="true"<?php if ($selected_booked_mailer): echo ' checked="checked"'; endif; ?> type="checkbox">
														<label class="checkbox-radio-label" for="booked_emailer_disabled"><strong><?php esc_html_e("Desactivar el envío propio del plugin y dejar que lo gestione WordPress.", 'pw-calendario'); ?></strong></label>
													</p>

												</div>
											</div>

										</div>
										<div id="booked-subtab-customer-emails" class="subtab-content">

											<div class="section-row">
												<div class="section-head">
													<?php $section_title = esc_html__('Recordatorio para el cliente', 'pw-calendario'); ?>
													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('¿Cuándo quieres enviar los recordatorios de las citas?','pw-calendario'); ?></p>

													<?php $option_name = 'booked_reminder_buffer';
													$selected_value = get_option($option_name,30);

													$interval_options = array(
														'0' 				=> esc_html__('A la hora de la cita','pw-calendario'),
														'5' 				=> esc_html__('5 minutos antes','pw-calendario'),
														'10' 				=> esc_html__('10 minutos antes','pw-calendario'),
														'15' 				=> esc_html__('15 minutos antes','pw-calendario'),
														'30' 				=> esc_html__('30 minutos antes','pw-calendario'),
														'45' 				=> esc_html__('45 minutos antes','pw-calendario'),
														'60' 				=> esc_html__('1 hora antes','pw-calendario'),
														'120' 				=> esc_html__('2 horas antes','pw-calendario'),
														'180' 				=> esc_html__('3 horas antes','pw-calendario'),
														'240' 				=> esc_html__('4 horas antes','pw-calendario'),
														'300' 				=> esc_html__('5 horas antes','pw-calendario'),
														'360' 				=> esc_html__('6 horas antes','pw-calendario'),
														'720' 				=> esc_html__('12 horas antes','pw-calendario'),
														'1440' 				=> esc_html__('24 horas antes','pw-calendario'),
														'2880' 				=> esc_html__('2 días antes','pw-calendario'),
														'4320' 				=> esc_html__('3 días antes','pw-calendario'),
														'5760' 				=> esc_html__('4 días antes','pw-calendario'),
														'7200' 				=> esc_html__('5 días antes','pw-calendario'),
														'8640' 				=> esc_html__('6 días antes','pw-calendario'),
														'10080' 			=> esc_html__('1 semana antes','pw-calendario'),
														'20160' 			=> esc_html__('2 semanas antes','pw-calendario'),
														'30240' 			=> esc_html__('3 semanas antes','pw-calendario'),
														'40320' 			=> esc_html__('4 semanas antes','pw-calendario'),
														'60480' 			=> esc_html__('6 semanas antes','pw-calendario'),
														'80640' 			=> esc_html__('2 meses antes','pw-calendario'),
														'120960' 			=> esc_html__('3 meses antes','pw-calendario'),
													); ?>

													<div class="select-box">
														<select name="<?php echo $option_name; ?>">
															<?php foreach($interval_options as $current_value => $option_title):
																echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
															endforeach; ?>
														</select>
													</div><!-- /.select-box -->

													<p><strong><?php esc_html_e('Ten en cuenta:','pw-calendario'); ?></strong> <?php esc_html_e('Las tareas programadas de WordPress solo se ejecutan cuando alguien visita el sitio, así que puede que algún recordatorio no llegue a enviarse. Para evitarlo, programa la tarea en el servidor con este comando:','pw-calendario'); ?></p>
													<p><code>*/5 * * * * wget -q -O - <?php echo get_site_url(); ?>/wp-cron.php?doing_wp_cron</code></p>

												</div><!-- /.section-body -->
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $option_name = 'booked_reminder_email';

$default_content = 'Just a friendly reminder that you have an appointment coming up soon! Here\'s the appointment information:

<strong>Calendar:</strong> %calendar%
<strong>Date:</strong> %date%
<strong>Time:</strong> %time%

Sincerely,
Your friends at '.get_bloginfo('name');

													$email_content_admin_reminder = get_option($option_name,$default_content);
													$section_title = esc_html__('Contenido del recordatorio para el cliente', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo de los recordatorios de cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_reminder_email_subject';
													$subject_default = 'Reminder: You have an appointment coming up soon!';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_admin_reminder, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 250,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $option_name = 'booked_registration_email_content';

$default_content = 'Hey %name%!

Thanks for registering at '.get_bloginfo('name').'. You can now login to manage your account and appointments using the following credentials:

Email Address: %email%
Password: %password%

Sincerely,
Your friends at '.get_bloginfo('name');

													$email_content_registration = get_option($option_name,$default_content);
													$section_title = esc_html__('Registro de usuarios', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía al usuario cuando se registra desde el formulario del calendario. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<li><strong>%name%</strong> &mdash; <?php esc_html_e("Para mostrar el nombre de la persona.",'pw-calendario'); ?></li>
														<li><strong>%email%</strong> &mdash; <?php esc_html_e("Para mostrar el correo electrónico de la persona.",'pw-calendario'); ?></li>
														<li><strong>%password%</strong> &mdash; <?php esc_html_e("Para mostrar la contraseña de acceso.",'pw-calendario'); ?></li>
													</ul><br>

													<?php

													$subject_var = 'booked_registration_email_subject';
													$subject_default = 'Thank you for registering!';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_registration, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 350,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

											<div class="section-row" data-controller="cp_fes_controller" data-controlled_by="fes_enabled">
												<div class="section-head">
													<?php $option_name = 'booked_appt_confirmation_email_content';

$default_content = 'Hey %name%!

This is just an email to confirm your appointment. For reference, here\'s the appointment information:

Date: %date%
Time: %time%

Sincerely,
Your friends at '.get_bloginfo('name');

													$email_content_approval = get_option($option_name,$default_content);
													$section_title = esc_html__('Confirmación de la cita', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía al cliente cuando se crea su cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_appt_confirmation_email_subject';
													$subject_default = 'Your appointment confirmation from '.get_bloginfo('name').'.';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_approval, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 350,'teeny' => true) ); ?>
												</div>
											</div><!-- /.section-row -->

											<div class="section-row" data-controller="cp_fes_controller" data-controlled_by="fes_enabled">
												<div class="section-head">
													<?php $option_name = 'booked_approval_email_content';

$default_content = 'Hey %name%!

The appointment you requested at '.get_bloginfo('name').' has been approved! Here\'s your appointment information:

Date: %date%
Time: %time%

Sincerely,
Your friends at '.get_bloginfo('name');

													$email_content_approval = get_option($option_name,$default_content);
													$section_title = esc_html__('Aprobación de la cita', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía al cliente cuando se aprueba su cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_approval_email_subject';
													$subject_default = 'Your appointment has been approved!';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_approval, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 350,'teeny' => true) ); ?>
												</div>
											</div><!-- /.section-row -->

											<div class="section-row" data-controller="cp_fes_controller" data-controlled_by="fes_enabled">
												<div class="section-head">
													<?php $option_name = 'booked_cancellation_email_content';

$default_content = 'Hey %name%!

The appointment you requested at '.get_bloginfo('name').' has been cancelled. For reference, here\'s the appointment information:

Date: %date%
Time: %time%

Sincerely,
Your friends at '.get_bloginfo('name');

													$email_content_approval = get_option($option_name,$default_content);
													$section_title = esc_html__('Cancelación de la cita', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía al cliente cuando se cancela su cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_cancellation_email_subject';
													$subject_default = 'Your appointment has been cancelled.';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_approval, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 350,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

										</div>
										<div id="booked-subtab-admin-emails" class="subtab-content">

											<div class="section-row">
												<div class="section-head">
													<?php $section_title = esc_html__('Recordatorio para el gestor', 'pw-calendario'); ?>
													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('¿Cuándo quieres enviar los recordatorios de las citas?','pw-calendario'); ?></p>

													<?php $option_name = 'booked_admin_reminder_buffer';
													$selected_value = get_option($option_name,30);

													$interval_options = array(
														'0' 				=> esc_html__('A la hora de la cita','pw-calendario'),
														'5' 				=> esc_html__('5 minutos antes','pw-calendario'),
														'10' 				=> esc_html__('10 minutos antes','pw-calendario'),
														'15' 				=> esc_html__('15 minutos antes','pw-calendario'),
														'30' 				=> esc_html__('30 minutos antes','pw-calendario'),
														'45' 				=> esc_html__('45 minutos antes','pw-calendario'),
														'60' 				=> esc_html__('1 hora antes','pw-calendario'),
														'120' 				=> esc_html__('2 horas antes','pw-calendario'),
														'180' 				=> esc_html__('3 horas antes','pw-calendario'),
														'240' 				=> esc_html__('4 horas antes','pw-calendario'),
														'300' 				=> esc_html__('5 horas antes','pw-calendario'),
														'360' 				=> esc_html__('6 horas antes','pw-calendario'),
														'720' 				=> esc_html__('12 horas antes','pw-calendario'),
														'1440' 				=> esc_html__('24 horas antes','pw-calendario'),
														'2880' 				=> esc_html__('2 días antes','pw-calendario'),
														'4320' 				=> esc_html__('3 días antes','pw-calendario'),
														'5760' 				=> esc_html__('4 días antes','pw-calendario'),
														'7200' 				=> esc_html__('5 días antes','pw-calendario'),
														'8640' 				=> esc_html__('6 días antes','pw-calendario'),
														'10080' 			=> esc_html__('1 semana antes','pw-calendario'),
														'20160' 			=> esc_html__('2 semanas antes','pw-calendario'),
														'30240' 			=> esc_html__('3 semanas antes','pw-calendario'),
														'40320' 			=> esc_html__('4 semanas antes','pw-calendario'),
														'60480' 			=> esc_html__('6 semanas antes','pw-calendario'),
														'80640' 			=> esc_html__('2 meses antes','pw-calendario'),
														'120960' 			=> esc_html__('3 meses antes','pw-calendario'),
													); ?>

													<div class="select-box">
														<select name="<?php echo $option_name; ?>">
															<?php foreach($interval_options as $current_value => $option_title):
																echo '<option value="'.$current_value.'"' . ($selected_value == $current_value ? ' selected' : ''). '>' . $option_title . '</option>';
															endforeach; ?>
														</select>
													</div><!-- /.select-box -->

													<p><strong><?php esc_html_e('Ten en cuenta:','pw-calendario'); ?></strong> <?php esc_html_e('Las tareas programadas de WordPress solo se ejecutan cuando alguien visita el sitio, así que puede que algún recordatorio no llegue a enviarse. Para evitarlo, programa la tarea en el servidor con este comando:','pw-calendario'); ?></p>
													<p><code>*/5 * * * * wget -q -O - <?php echo get_site_url(); ?>/wp-cron.php?doing_wp_cron</code></p>

												</div><!-- /.section-body -->
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $option_name = 'booked_admin_reminder_email';

$default_content = 'You have an appointment coming up soon! Here\'s the appointment information:

<strong>Customer:</strong> %name%
<strong>Date:</strong> %date%
<strong>Time:</strong> %time%

(Sent via the '.get_bloginfo('name').' website)';

													$email_content_admin_reminder = get_option($option_name,$default_content);
													$section_title = esc_html__('Contenido del recordatorio para el gestor', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo de los recordatorios de cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_admin_reminder_email_subject';
													$subject_default = 'An appointment is coming up soon!';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_admin_reminder, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 250,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $option_name = 'booked_admin_appointment_email_content';

$default_content = 'You have a new appointment request! Here\'s the appointment information:

Customer: %name%
Date: %date%
Time: %time%

Log into your website here: '.get_admin_url().' to approve this appointment.

(Sent via the '.get_bloginfo('name').' website)';

													$email_content_registration = get_option($option_name,$default_content);
													$section_title = esc_html__('Solicitud de cita', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía a los gestores seleccionados arriba cuando se solicita una cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_admin_appointment_email_subject';
													$subject_default = 'You have a new appointment request!';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_registration, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 350,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

											<div class="section-row">
												<div class="section-head">
													<?php $option_name = 'booked_admin_cancellation_email_content';

$default_content = 'One of your customers has cancelled their appointment. Here\'s the appointment information:

Customer: %name%
Date: %date%
Time: %time%

(Sent via the '.get_bloginfo('name').' website)';

													$email_content_registration = get_option($option_name,$default_content);
													$section_title = esc_html__('Cancelación de la cita', 'pw-calendario'); ?>

													<h3><?php echo esc_attr($section_title); ?></h3>
													<p><?php esc_html_e('Contenido del correo que se envía a los gestores seleccionados arriba cuando se cancela una cita. Puedes usar estas etiquetas:','pw-calendario'); ?></p>
													<ul class="cp-list">
														<?php $booked_mailer_tokens = booked_mailer_tokens();
														foreach( $booked_mailer_tokens as $token => $desc ):
															echo '<li><strong>%' . $token . '%</strong> &mdash; ' . $desc . '</li>';
														endforeach; ?>
													</ul><br>

													<?php

													$subject_var = 'booked_admin_cancellation_email_subject';
													$subject_default = 'An appointment has been cancelled.';
													$current_subject_value = get_option($subject_var,$subject_default); ?>

													<input style="margin:0" name="<?php echo $subject_var; ?>" value="<?php echo $current_subject_value; ?>" type="text" class="field">
													<?php wp_editor( $email_content_registration, $option_name, array('textarea_name' => $option_name,'media_buttons' => false,'editor_height' => 250,'teeny' => true) ); ?>

												</div>
											</div><!-- /.section-row -->

										</div>

										<?php do_action( 'booked_admin_after_email_tab_content' ); ?>

										<div class="section-row submit-section" style="padding:0;">
											<?php @submit_button(); ?>
										</div><!-- /.section-row -->

									</div><!-- /templates -->

								</form>

							<?php break;
							
							case 'woocommerce-settings':
							
								if ( class_exists('woocommerce') ):
							
								?><div id="booked-woocommerce-settings" class="booked-payment-settings-wrap tab-content">
									<form action="options.php" method="post">
										<div class="section-row">
											<div class="section-head">
												<?php settings_fields( BOOKED_WC_PLUGIN_PREFIX . 'payment_options' );
												do_settings_sections( BOOKED_WC_PLUGIN_PREFIX . 'payment_options' );
											?></div>
										</div><?php
										submit_button(); ?>
									</form>
								</div><?php
								
								endif;
							
							break;
							
							case 'calendar-feeds': ?>
								
								<div id="booked-calendar-feeds" class="tab-content">
									
									<div class="section-row">
										<div class="section-head">
											<?php $section_title = esc_html__('Feeds de calendario', 'pw-calendario'); ?>
											<h3 style="font-size:17px; margin:0; padding:0 0 5px;"><?php echo $section_title; ?></h3>
									
											<?php $secure_hash = md5( home_url() ); ?>
										
											<p style="width:50%; font-size:14px; margin:0; padding:0 0 20px;"><?php _e('Usa estas URL para descargar un feed estático (que no se actualiza) o pégalas en tu aplicación de calendario (Google Calendar, Calendario de Apple, etc.) como suscripción, para tener un feed de citas de solo lectura que se actualiza solo.','pw-calendario'); ?></p>
											
											<p style="font-size:15px; margin:0; padding:0 0 10px;"><strong><?php _e('Todas las citas','pw-calendario'); ?></strong></p>
											<p style="font-size:15px; margin:0; padding:0 0 20px;"><input readonly="readonly" type="text" style="width:50%;" value="<?php echo get_site_url(); ?>/?booked_ical&sh=<?php echo esc_attr( BOOKEDICAL_SECURE_HASH ); ?>"></p>
											
											<?php $calendars = get_terms('booked_custom_calendars','orderby=slug&hide_empty=0');
												
											if (!empty($calendars)):
												
												foreach($calendars as $calendar):
													
													?><p style="font-size:15px; margin:0; padding:0 0 10px;"><strong><?php echo $calendar->name; ?></strong></p>
													<p style="font-size:15px; margin:0; padding:0 0 20px;"><input readonly="readonly" type="text" style="width:50%;" value="<?php echo get_site_url(); ?>/?booked_ical&calendar=<?php echo $calendar->term_id; ?>&sh=<?php echo esc_attr( BOOKEDICAL_SECURE_HASH ); ?>"></p><?php
												
												endforeach;
															
											endif; ?>
										</div>
									</div>
								
								</div>
							
							<?php break;

							case 'defaults': ?>

								<div id="booked-defaults" class="tab-content">

									<?php if (!$booked_none_assigned && count($calendars) >= 1):

										?><div id="booked-timeslotsSwitcher">
											<p><strong><?php esc_html_e('Editando las franjas horarias del:','pw-calendario'); ?></strong></p>
											<?php

											echo '<select name="bookedTimeslotsDisplayed">';
											if (current_user_can('manage_booked_options')): echo '<option value="">'.esc_html__('Calendario predeterminado','pw-calendario').'</option>'; endif;

											foreach($calendars as $calendar):

												?><option value="<?php echo $calendar->term_id; ?>"><?php echo $calendar->name; ?></option><?php

											endforeach;

											echo '</select>';

										?></div><?php

									endif; ?>

									<div id="bookedTimeslotsWrap">
										<?php if (current_user_can('manage_booked_options')):
											booked_render_timeslots();
										else:
											$first_calendar = reset($calendars);
											booked_render_timeslots($first_calendar->term_id);
										endif; ?>
									</div>

									<?php $timeslot_intervals = get_option('booked_timeslot_intervals',5); ?>

									<div id="timepickerTemplate" class="bookedClearFix">
										<div class="timeslotTabs bookedClearFix">
											<a class="addTimeslotTab active" href="#Single"><?php esc_html_e('Individual','pw-calendario'); ?></a>
											<a class="addTimeslotTab" href="#Bulk"><?php esc_html_e('En bloque','pw-calendario'); ?></a>
										</div>
										<div class="tsTabContent tsSingle">
											<?php echo booked_render_single_timeslot_form($timeslot_intervals); ?>
										</div>
										<div class="tsTabContent tsBulk">
											<?php echo booked_render_bulk_timeslot_form($timeslot_intervals); ?>
										</div>
										<span class="cancel button"><?php esc_html_e('Cerrar','pw-calendario'); ?></span>
									</div>

								</div><!-- /templates -->

							<?php break;

							case 'custom-timeslots': ?>

								<div id="booked-custom-timeslots" class="tab-content">

									<form action="" id="customTimeslots">

										<div id="customTimeslotsWrapper">
											<div id="customTimeslotsContainer">

												<?php

												// Any custom time slots saved already?
												$booked_custom_timeslots_encoded = get_option('booked_custom_timeslots_encoded');
												$booked_custom_timeslots_decoded = json_decode($booked_custom_timeslots_encoded,true);

												$available_calendar_ids = array();

												foreach($calendars as $this_calendar):
													$available_calendar_ids[] = $this_calendar->term_id;
												endforeach;

												if (!empty($booked_custom_timeslots_decoded)):

													$custom_timeslots_array = booked_custom_timeslots_reconfigured($booked_custom_timeslots_decoded);
													foreach($custom_timeslots_array as $key => $timeslot):
														$date_string = date_i18n('Ymd',strtotime($timeslot['booked_custom_start_date']));
														$new_custom_timeslots_array[$date_string.$key] = $timeslot;
													endforeach;

													$custom_timeslots_array = $new_custom_timeslots_array;

													ksort($custom_timeslots_array);
													$current_timeslot_month_year = false;

													foreach($custom_timeslots_array as $pwcal_clave_franja => $this_timeslot):

														$this_timeslot['booked_custom_calendar_id'] = isset($this_timeslot['booked_custom_calendar_id']) ? $this_timeslot['booked_custom_calendar_id'] : false;
														$this_timeslot_month_year = ( $this_timeslot['booked_custom_start_date'] ? date_i18n('F, Y',strtotime($this_timeslot['booked_custom_start_date'])) : '<span style="color:#dd0000;">'.esc_html__('No se ha indicado la fecha de inicio de estas:', 'pw-calendario').'</span>' );

														if (!$current_timeslot_month_year || $current_timeslot_month_year != $this_timeslot_month_year):
															$current_timeslot_month_year = $this_timeslot_month_year;
															echo '<h3 class="booked-ct-date-heading">'.$current_timeslot_month_year.'</h3>';
														endif;

														?><div class="booked-customTimeslot"<?php if (!current_user_can('manage_booked_options') && $this_timeslot['booked_custom_calendar_id'] && !in_array($this_timeslot['booked_custom_calendar_id'],$available_calendar_ids)): echo ' style="display:none;"'; endif; ?>>

															<?php

															if (!empty($calendars)):

															    if (!current_user_can('manage_booked_options') && $this_timeslot['booked_custom_calendar_id'] && !in_array($this_timeslot['booked_custom_calendar_id'],$available_calendar_ids)):

															        ?><input type="hidden" name="booked_custom_calendar_id" value="<?php echo $this_timeslot['booked_custom_calendar_id']; ?>"><?php

															    else:

															        echo '<select name="booked_custom_calendar_id">';

															            if (current_user_can('manage_booked_options')): echo '<option value="">'.__('Calendario predeterminado','pw-calendario').'</option>'; endif;

															            foreach($calendars as $calendar):

															                ?><option<?php if ($this_timeslot['booked_custom_calendar_id'] == $calendar->term_id): echo ' selected="selected"'; endif; ?> value="<?php echo $calendar->term_id; ?>"><?php echo $calendar->name; ?></option><?php

															            endforeach;

															        echo '</select>';

															    endif;

															else:

															    ?><input type="hidden" name="booked_custom_calendar_id" value=""><?php

															endif; ?>

															<input type="text" placeholder="<?php esc_html_e("Fecha de inicio",'pw-calendario'); ?>..." class="booked_custom_start_date" name="booked_custom_start_date" value="<?php echo ( $this_timeslot['booked_custom_start_date'] ? date_i18n( 'Y-m-d', strtotime( $this_timeslot['booked_custom_start_date'] ) ) : '' ); ?>">
															<input type="text" placeholder="<?php esc_html_e("Fecha de fin (opcional)",'pw-calendario'); ?>..." class="booked_custom_end_date" name="booked_custom_end_date" value="<?php echo ( $this_timeslot['booked_custom_end_date'] ? date_i18n( 'Y-m-d', strtotime( $this_timeslot['booked_custom_end_date'] ) ) : '' ); ?>">

															<?php
															/*
															 * Dias de la semana del rango. Solo tiene
															 * sentido cuando hay fecha de fin: en una
															 * fecha unica el dia ya esta determinado.
															 */
															pwcal_casillas_dias_semana(
																isset( $this_timeslot['booked_custom_dias'] ) ? $this_timeslot['booked_custom_dias'] : '',
																(string) $pwcal_clave_franja
															);
															?>

															<?php if (isset($this_timeslot['booked_this_custom_timelots']) && is_array($this_timeslot['booked_this_custom_timelots'])): ?>
																<input type="hidden" name="booked_this_custom_timelots" value="<?php echo esc_attr(json_encode($this_timeslot['booked_this_custom_timelots'])); ?>">
															<?php else : ?>
																<input type="hidden" name="booked_this_custom_timelots" value="<?php echo esc_attr($this_timeslot['booked_this_custom_timelots']); ?>">
															<?php endif; ?>

															<?php if (isset($this_timeslot['booked_this_custom_timelots_details']) && is_array($this_timeslot['booked_this_custom_timelots_details'])): ?>
																<input type="hidden" name="booked_this_custom_timelots_details" value="<?php echo esc_attr(json_encode($this_timeslot['booked_this_custom_timelots_details'])); ?>">
															<?php else : ?>
																<input type="hidden" name="booked_this_custom_timelots_details" value="<?php echo esc_attr($this_timeslot['booked_this_custom_timelots_details']); ?>">
															<?php endif; ?>

															<input id="vacationDayCheckbox" name="vacationDayCheckbox" type="checkbox" value="1"<?php if ($this_timeslot['vacationDayCheckbox']): echo ' checked="checked"'; endif; ?>>
															<label for="vacationDayCheckbox"><?php esc_html_e('Desactivar las citas','pw-calendario'); ?></label>

															<a href="#" class="deleteCustomTimeslot"><i class="booked-icon booked-icon-close"></i></a>

															<?php

															if (is_array($this_timeslot['booked_this_custom_timelots'])):
																$timeslots = $this_timeslot['booked_this_custom_timelots'];
															else:
																$timeslots = json_decode($this_timeslot['booked_this_custom_timelots'],true);
															endif;

															if (isset($this_timeslot['booked_this_custom_timelots_details']) && is_array($this_timeslot['booked_this_custom_timelots_details'])):
																$timeslots_details = $this_timeslot['booked_this_custom_timelots_details'];
															elseif(isset($this_timeslot['booked_this_custom_timelots_details'])):
																$timeslots_details = json_decode($this_timeslot['booked_this_custom_timelots_details'],true);
															endif;

															echo '<div class="customTimeslotsList">';

															if (!empty($timeslots)):

																echo '<div class="cts-header"><span class="slotsTitle">'.esc_html__('plazas libres','pw-calendario').'</span>'.esc_html__('Franja horaria','pw-calendario').'</div>';

																foreach ($timeslots as $timeslot => $count):

																	$time = explode('-',$timeslot);
																	$time_format = get_option('time_format');

																	echo '<span class="timeslot" data-timeslot="'.$timeslot.'">';
																		echo '<span class="slotsBlock"><span class="changeCount minus" data-count="-1"><i class="booked-icon booked-icon-minus-circle"></i></span><span class="count"><em>'.$count.'</em> ' . _n('plaza libre','plazas libres',$count,'pw-calendario') . '</span><span class="changeCount add" data-count="1"><i class="booked-icon booked-icon-plus-circle"></i></span></span>';

																		do_action( 'booked_single_custom_timeslot_start', $this_timeslot, $timeslot, $this_timeslot['booked_custom_calendar_id'] );

																		if ( !empty($timeslots_details[$timeslot]) ) {

																			if ( !empty($timeslots_details[$timeslot]['title']) ) {
																				echo '<span class="title">' . esc_html($timeslots_details[$timeslot]['title']) . '</span>';
																			}
																		}

																		if ($time[0] == '0000' && $time[1] == '2400'):
																			echo '<span class="start"><i class="booked-icon booked-icon-clock"></i>&nbsp;&nbsp;' . strtoupper(esc_html__('Todo el día','pw-calendario')) . '</span>';
																		else :
																			echo '<span class="start"><i class="booked-icon booked-icon-clock"></i>&nbsp;&nbsp;' . date_i18n($time_format,strtotime('2014-01-01 '.$time[0])) . '</span> &ndash; <span class="end">' . date_i18n($time_format,strtotime('2014-01-01 '.$time[1])) . '</span>';
																		endif;

																		do_action( 'booked_single_custom_timeslot_end', $this_timeslot, $timeslot, $this_timeslot['booked_custom_calendar_id'] );

																		echo '<span class="delete"><i class="booked-icon booked-icon-close"></i></span>';
																	echo '</span>';

																endforeach;
															endif;

															echo '</div>';

															?>

															<button class="button addSingleTimeslot"><?php esc_html_e('+ Franja individual','pw-calendario'); ?></button>
															<button class="button addBulkTimeslots"><?php esc_html_e('+ Franjas en bloque','pw-calendario'); ?></button>

														</div><?php

													endforeach;
												endif;

												?>

											</div>
										</div>

										<div class="section-row submit-section bookedClearFix" style="padding:0;">
											<button class="button addCustomTimeslot"><?php esc_html_e('Añadir fecha(s)','pw-calendario'); ?></button>
											<input id="booked-saveCustomTimeslots" type="button" disabled="true" class="button saveCustomTimeslots" value="<?php esc_html_e('Guardar las franjas horarias personalizadas','pw-calendario'); ?>">
											<div class="cts-updater savingState"><i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i>&nbsp;&nbsp;<?php esc_html_e('Guardando','pw-calendario'); ?>...</div>
										</div><!-- /.section-row -->

									</form>

									<input type="hidden" style="width:100%;" id="custom_timeslots_encoded" name="custom_timeslots_encoded" value="<?php echo esc_attr($booked_custom_timeslots_encoded); ?>">

									<div style="border:1px solid #FFBA00;" class="booked-customTimeslotTemplate">

										<?php if (!empty($calendars)):

											echo '<select name="booked_custom_calendar_id">';
												if (current_user_can('manage_booked_options')): echo '<option value="">'.esc_html__('Calendario predeterminado','pw-calendario').'</option>'; endif;

												foreach($calendars as $calendar):

													?><option value="<?php echo $calendar->term_id; ?>"><?php echo $calendar->name; ?></option><?php

												endforeach;

											echo '</select>';

										else: ?>

											<input type="hidden" name="booked_custom_calendar_id" value="">

										<?php endif; ?>

										<input type="text" placeholder="<?php esc_html_e("Fecha de inicio",'pw-calendario'); ?>..." class="booked_custom_start_date" name="booked_custom_start_date" value="">
										<input type="text" placeholder="<?php esc_html_e("Fecha de fin (opcional)",'pw-calendario'); ?>..." class="booked_custom_end_date" name="booked_custom_end_date" value="">
										<?php pwcal_casillas_dias_semana( '', 'plantilla' ); ?>

										<input type="hidden" name="booked_this_custom_timelots" value="">
										<input type="hidden" name="booked_this_custom_timelots_details" value="">

										<input id="vacationDayCheckbox" name="vacationDayCheckbox" type="checkbox" value="1">
										<label for="vacationDayCheckbox"><?php esc_html_e('Desactivar las citas','pw-calendario'); ?></label>

										<a href="#" class="deleteCustomTimeslot"><i class="booked-icon booked-icon-close"></i></a>

										<div class="customTimeslotsList"></div>

										<button class="button addSingleTimeslot"><?php esc_html_e('+ Franja individual','pw-calendario'); ?></button>
										<button class="button addBulkTimeslots"><?php esc_html_e('+ Franjas en bloque','pw-calendario'); ?></button>

									</div>

									<div id="booked-customTimePickerTemplates">
										<div class="customSingle bookedClearFix">
											<?php echo booked_render_single_timeslot_form($timeslot_intervals,'custom'); ?>
											<button class="button-primary addSingleTimeslot_button"><?php esc_html_e('Añadir','pw-calendario'); ?></button>
											<button class="button cancel"><?php esc_html_e('Cerrar','pw-calendario'); ?></button>
										</div>
										<div class="customBulk bookedClearFix">
											<?php echo booked_render_bulk_timeslot_form($timeslot_intervals,'custom'); ?>
											<button class="button-primary addBulkTimeslots_button"><?php esc_html_e('Añadir','pw-calendario'); ?></button>
											<button class="button cancel"><?php esc_html_e('Cerrar','pw-calendario'); ?></button>
										</div>
									</div>

								</div>

							<?php break;

							case 'custom-fields': ?>

								<div id="booked-custom-fields" class="tab-content">

									<div class="section-row">
										<div class="section-head">

											<div class="booked-cf-block">

												<?php if (!empty($calendars)):

													echo '<div id="booked-cfSwitcher" style="margin:0 0 30px;">';
														echo '<select name="bookedCustomFieldsDisplayed">';

															if (current_user_can('manage_booked_options')): echo '<option value="">'.esc_html__('Calendario predeterminado','pw-calendario').'</option>'; endif;

															foreach($calendars as $calendar):

																?><option value="<?php echo $calendar->term_id; ?>"><?php echo $calendar->name; ?></option><?php

															endforeach;

														echo '</select>';
													echo '</div>';

												endif; ?>

												<div id="booked_customFields_Wrap">

													<?php if (current_user_can('manage_booked_options')):
														booked_render_custom_fields();
													else:
														$first_calendar = reset($calendars);
														booked_render_custom_fields($first_calendar->term_id);
													endif; ?>

												</div>

											</div>

											<ul id="booked-cf-sortable-templates">

												<li id="bookedCFTemplate-single-line-text-label" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Texto de una línea','pw-calendario'); ?></small>
													<p><input class="cf-required-checkbox" type="checkbox" name="required" id="required"> <label for="required"><?php esc_html_e('Campo obligatorio','pw-calendario'); ?></label></p>
													<input type="text" name="single-line-text-label" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este campo…','pw-calendario'); ?>" />
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-paragraph-text-label" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Texto de párrafo','pw-calendario'); ?></small>
													<p><input class="cf-required-checkbox" type="checkbox" name="required" id="required"> <label for="required"><?php esc_html_e('Campo obligatorio','pw-calendario'); ?></label></p>
													<input type="text" name="paragraph-text-label" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este campo…','pw-calendario'); ?>" />
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-checkboxes-label" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Casillas de verificación','pw-calendario'); ?></small>
													<p><input class="cf-required-checkbox" type="checkbox" name="required" id="required"> <label for="required"><?php esc_html_e('Campo obligatorio','pw-calendario'); ?></label></p>
													<input type="text" name="checkboxes-label" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este grupo de casillas…','pw-calendario'); ?>" />
													<ul id="booked-cf-checkboxes"></ul>
													<button class="cfButton button" data-type="single-checkbox">+ <?php esc_html_e('Casilla de verificación','pw-calendario'); ?></button>
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-radio-buttons-label" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Botones de opción','pw-calendario'); ?></small>
													<p><input class="cf-required-checkbox" type="checkbox" name="required" id="required"> <label for="required"><?php esc_html_e('Campo obligatorio','pw-calendario'); ?></label></p>
													<input type="text" name="radio-buttons-label" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este grupo de botones de opción…','pw-calendario'); ?>" />
													<ul id="booked-cf-radio-buttons"></ul>
													<button class="cfButton button" data-type="single-radio-button">+ <?php esc_html_e('Opción','pw-calendario'); ?></button>
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-drop-down-label" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Desplegable','pw-calendario'); ?></small>
													<p><input class="cf-required-checkbox" type="checkbox" name="required" id="required"> <label for="required"><?php esc_html_e('Campo obligatorio','pw-calendario'); ?></label></p>
													<input type="text" name="drop-down-label" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este grupo desplegable…','pw-calendario'); ?>" />
													<ul id="booked-cf-drop-down"></ul>
													<button class="cfButton button" data-type="single-drop-down">+ <?php esc_html_e('Opción','pw-calendario'); ?></button>
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-plain-text-content" class="ui-state-default"><i class="main-handle booked-icon booked-icon-bars"></i>
													<small><?php esc_html_e('Contenido de texto','pw-calendario'); ?></small>
													<textarea name="plain-text-content"></textarea>
													<small class="help-text"><?php esc_html_e('Se admite HTML','pw-calendario'); ?></small>
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>

												<li id="bookedCFTemplate-single-checkbox" class="ui-state-default "><i class="sub-handle booked-icon booked-icon-bars"></i>
													<?php do_action('booked_before_custom_checkbox'); ?>
													<input type="text" name="single-checkbox" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para esta casilla…','pw-calendario'); ?>" />
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
													<?php do_action('booked_after_custom_checkbox'); ?>
												</li>
												<li id="bookedCFTemplate-single-radio-button" class="ui-state-default "><i class="sub-handle booked-icon booked-icon-bars"></i>
													<input type="text" name="single-radio-button" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para este botón de opción…','pw-calendario'); ?>" />
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>
												<li id="bookedCFTemplate-single-drop-down" class="ui-state-default "><i class="sub-handle booked-icon booked-icon-bars"></i>
													<input type="text" name="single-drop-down" value="" placeholder="<?php esc_html_e('Escribe una etiqueta para esta opción…','pw-calendario'); ?>" />
													<span class="cf-delete"><i class="booked-icon booked-icon-close"></i></span>
												</li>

												<?php do_action('booked_custom_fields_add_template') ?>
											</ul>

										</div>
									</div>

									<input id="booked_custom_fields" name="booked_custom_fields" value="" type="hidden" class="field" style="width:100%;">

									<div class="section-row submit-section bookedClearFix" style="padding:0;">
										<input id="booked-cf-saveButton" type="button" class="button button-primary" value="<?php esc_html_e('Guardar los campos personalizados','pw-calendario'); ?>">
										<div class="cf-updater savingState"><i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i>&nbsp;&nbsp;<?php esc_html_e('Guardando','pw-calendario'); ?>...</div>
									</div><!-- /.section-row -->

								</div><!-- /templates -->

							<?php break;

							case 'shortcodes': ?>

								<div id="booked-shortcodes" class="tab-content">

									<div class="section-row" style="margin-bottom:-50px;">
										<div class="section-head">

											<h3><?php echo esc_html__('Mostrar el calendario predeterminado', 'pw-calendario'); ?></h3>
											<p><?php esc_html_e('Usa este shortcode para mostrar el calendario de reservas en el front-end. Con el atributo «calendar» muestras un calendario concreto; con «year» y «month», un año o un mes concretos. Con la variable «switcher» añades encima un desplegable para que los usuarios cambien entre los calendarios que hayas creado.','pw-calendario'); ?></p>
											<p><input value="[booked-calendar]" type="text" readonly="readonly" class="field"></p>

										</div>

										<?php

										if (!empty($calendars)):

											?><div class="section-head">
												<h3><?php echo esc_html__('Mostrar un calendario personalizado', 'pw-calendario'); ?></h3>
												<p style="margin:0 0 10px;">&nbsp;</p><?php

												foreach($calendars as $calendar):

													?><p style="margin:0 0 10px;"><strong style="font-size:14px;"><?php echo $calendar->name; ?></strong></p>
													<input value="[booked-calendar calendar=<?php echo $calendar->term_id; ?>]" readonly="readonly" type="text"class="field"><?php

												endforeach;

											?></div><?php

										endif;

										?>

										<div class="section-head">

											<h3><?php echo esc_html__('Mostrar el formulario de acceso y registro', 'pw-calendario'); ?></h3>
											<p><?php esc_html_e("Si no aparece la pestaña de registro, comprueba que has permitido los registros en Ajustes > Generales.",'pw-calendario'); ?></p>
											<p><input value="[booked-login]" type="text" readonly="readonly" class="field"></p>

										</div>

										<div class="section-head">

											<h3><?php echo esc_html__('Mostrar el perfil del usuario', 'pw-calendario'); ?></h3>
											<p><?php esc_html_e("Usa este shortcode para mostrar el contenido del perfil en cualquier página. Si el usuario no ha accedido, verá el formulario de acceso.",'pw-calendario'); ?></p>
											<p><input value="[booked-profile]" type="text" readonly="readonly" class="field"></p>

										</div>

										<div class="section-head">

											<h3><?php echo esc_html__("Mostrar las citas del usuario", 'pw-calendario'); ?></h3>
											<p><?php esc_html_e("Usa este shortcode para mostrar solo las citas próximas del usuario que ha accedido.",'pw-calendario'); ?></p>
											<p><input value="[booked-appointments]" type="text" readonly="readonly" class="field"></p>

										</div>

									</div>

								</div>


							<?php break;

							case 'export-appointments': ?>

								<form action="" class="booked-export-form" method="post">

									<div id="booked-export-appointments" class="tab-content">

										<div class="section-row">
											<div class="section-head">
												<h3><?php esc_html_e('Exportar las citas','pw-calendario'); ?></h3>
												<p><?php esc_html_e('Puedes exportar todas las citas o filtrar lo que necesites con las opciones de abajo.','pw-calendario'); ?></p>
												<br>
												<div class="select-box">
													<label class="booked-color-label" for="appointment_time"><?php esc_html_e('Fechas de la cita','pw-calendario'); ?>:</label>
													<select name="appointment_time">
														<option value="" selected="selected"><?php esc_html_e('Próximas y pasadas','pw-calendario'); ?></option>
														<option value="upcoming"><?php esc_html_e('Solo las próximas','pw-calendario'); ?></option>
														<option value="past"><?php esc_html_e('Solo las pasadas','pw-calendario'); ?></option>
													</select>
												</div>

												<br>
												<div class="select-box">
													<label class="booked-color-label" for="appointment_type"><?php esc_html_e('Aprobadas o pendientes','pw-calendario'); ?>:</label>
													<select name="appointment_type">
														<option value="any" selected="selected"><?php esc_html_e('Aprobadas y pendientes','pw-calendario'); ?></option>
														<option value="publish"><?php esc_html_e('Solo las aprobadas','pw-calendario'); ?></option>
														<option value="draft"><?php esc_html_e('Solo las pendientes','pw-calendario'); ?></option>
													</select>
												</div>

												<?php if (!empty($calendars)): ?>

													<br>
													<div class="select-box">
														<label class="booked-color-label" for="calendar_id"><?php esc_html_e('Calendario','pw-calendario'); ?>:</label>
														<select name="calendar_id">
															<option value="" selected="selected"><?php esc_html_e('Todos los calendarios','pw-calendario'); ?></option>
															<?php
															foreach($calendars as $calendar):
																?><option value="<?php echo $calendar->term_id; ?>"><?php echo $calendar->name; ?></option><?php
															endforeach;
															?>
														</select>
													</div>

												<?php endif; ?>

											</div>
										</div>

										<div class="section-row submit-section" style="padding:0;">
											<p class="submit">
												<button class="button-primary"><i class="booked-icon booked-icon-sign-out"></i>&nbsp;&nbsp;<?php esc_html_e('Exportar las citas a CSV','pw-calendario'); ?></button>
											</p>
										</div>

									</div>

									<input type="hidden" name="booked_export_appointments_csv" value="1">
									<?php
									// La exportación incluye nombres, correos y los datos de los
									// campos personalizados de todas las citas, así que se protege
									// con un nonce además de la comprobación de permisos.
									wp_nonce_field( 'pwcal_exportar_csv', 'pwcal_csv_nonce' );
									?>

								</form>

							<?php break;

						endswitch;

					endif;

				endforeach;

				?>

			</div>

		</div>

	<?php endif; ?>

	</div>
</div>