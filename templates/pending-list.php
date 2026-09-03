<?php if ( ! defined( 'ABSPATH' ) ) { exit; } // Salir si se accede directamente. ?>
<div class="booked-settings-prewrap">
	<div class="booked-settings-wrap wrap"><?php

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

	else:

		?><div class="topSavingState savingState"><i class="booked-icon booked-icon-spinner-clock booked-icon-spin"></i>&nbsp;&nbsp;<?php esc_html_e('Actualizando, espera un momento…','pw-calendario'); ?></div>
		<div class="booked-settings-title"><?php esc_html_e('Citas pendientes','pw-calendario'); ?></div>

		<div class="booked-pending-cap bookedClearFix">
			<button class="delete-past button"><?php esc_html_e('Eliminar las citas pasadas','pw-calendario'); ?></button>
			<button style="float:right; margin-left:10px;" class="approve-all button button-primary"><?php esc_html_e('Aprobar todas','pw-calendario'); ?></button>
			<button style="float:right;" class="delete-all button"><?php esc_html_e('Eliminar todas','pw-calendario'); ?></button>
		</div><?php

		echo '<div class="booked-pending-appt-list">';

			$time_format = get_option('time_format');
			$date_format = get_option('date_format');
			$pending_statuses = apply_filters( 'booked_admin_pending_post_status',array('draft') );

			if (empty($calendars) && !current_user_can('manage_booked_options')):

				$args = false;

			elseif(current_user_can('manage_booked_options')):

				$args = array(
					'post_type' => 'booked_appointments',
					'posts_per_page' => 500,
					'post_status' => $pending_statuses,
					'meta_key' => '_appointment_timestamp',
					'orderby' => 'meta_value_num',
					'order' => 'ASC'
				);

			else:

				$calendar_ids = array();

				if (!empty($calendars)):
					foreach($calendars as $cal):
						$calendar_ids[] = $cal->term_id;
					endforeach;
				endif;

				$args = array(
					'post_type' => 'booked_appointments',
					'posts_per_page' => 500,
					'post_status' => $pending_statuses,
					'meta_key' => '_appointment_timestamp',
					'orderby' => 'meta_value_num',
					'order' => 'ASC'
				);

				if (!empty($calendar_ids)):
					$args['tax_query'] = array(
						array(
							'taxonomy' => 'booked_custom_calendars',
							'field'    => 'term_id',
							'terms'    => $calendar_ids,
						)
					);
				endif;

			endif;

			$appointments_array = array();

			if ($args):
				$bookedAppointments = new WP_Query($args);
				if($bookedAppointments->have_posts()):
					while ($bookedAppointments->have_posts()):
						$bookedAppointments->the_post();
						global $post;

						$calendars = array();

						$calendar_terms = get_the_terms($post->ID,'booked_custom_calendars');
						if (!empty($calendar_terms)):
							$calendar_name = $calendar_terms[0]->name;
							$calendar_id = $calendar_terms[0]->term_id;
						else:
							$calendar_name = false;
							$calendar_id = false;
						endif;

						$guest_name = get_post_meta($post->ID, '_appointment_guest_name',true);
						$guest_surname = get_post_meta($post->ID, '_appointment_guest_surname',true);
						$guest_email = get_post_meta($post->ID, '_appointment_guest_email',true);

						$timestamp = get_post_meta($post->ID, '_appointment_timestamp',true);
						$timeslot = get_post_meta($post->ID, '_appointment_timeslot',true);
						$ts_title = get_post_meta($post->ID, '_appointment_title',true);

						$day = date_i18n('d',$timestamp);
						$appointments_array[$post->ID]['post_id'] = $post->ID;
						$appointments_array[$post->ID]['timestamp'] = $timestamp;
						$appointments_array[$post->ID]['timeslot'] = $timeslot;
						$appointments_array[$post->ID]['title'] = $ts_title;
						$appointments_array[$post->ID]['status'] = $post->post_status;
						$appointments_array[$post->ID]['calendar'] = $calendar_name;
						$appointments_array[$post->ID]['calendar_id'] = $calendar_id;

						if (!$guest_name):
							$user_id = get_post_meta($post->ID, '_appointment_user',true);
							$appointments_array[$post->ID]['user'] = $user_id;
						else:
							$appointments_array[$post->ID]['guest_name'] = $guest_name;
							$appointments_array[$post->ID]['guest_surname'] = $guest_surname;
							$appointments_array[$post->ID]['guest_email'] = $guest_email;
						endif;

					endwhile;
					$appointments_array = apply_filters('booked_appointments_array', $appointments_array);
				endif;
			endif;

			/*
			Let's loop through the pending appointments
			*/

			if (!empty($appointments_array)):

				foreach($appointments_array as $appt):

					$date_display = date_i18n($date_format,$appt['timestamp']);
					$day_name = date_i18n('l',$appt['timestamp']);

					$timeslots = explode('-',$appt['timeslot']);
					$time_start = date_i18n($time_format,strtotime($timeslots[0]));
					$time_end = date_i18n($time_format,strtotime($timeslots[1]));

					$title = $appt['title'];

					$date_to_compare = strtotime(date_i18n('Y-m-d',$appt['timestamp']).' '.date_i18n('H:i:s',strtotime($timeslots[0])));
					$late_date = current_time('timestamp');

					if ($timeslots[0] == '0000' && $timeslots[1] == '2400'):
						$timeslotText = esc_html__('Todo el día','pw-calendario');
					else :
						$timeslotText = $time_start.'&ndash;'.$time_end;
					endif;

					$pending_statuses = apply_filters('booked_admin_pending_post_status',array('draft'));

					$status = (in_array($appt['status'],$pending_statuses) ? 'pending' : 'approved');

					echo '<div class="pending-appt bookedClearFix'.($late_date > $date_to_compare ? ' passed' : '').'" data-appt-id="'.esc_attr($appt['post_id']).'">';

						if (!isset($appt['guest_name'])):
							$user_info = get_userdata($appt['user']);
							if (isset($user_info->ID)):
								if ($user_info->user_firstname):
									$user_display = '<a href="#" class="user" data-user-id="'.esc_attr($appt['user']).'">'.esc_html($user_info->user_firstname.($user_info->user_lastname ? ' '.$user_info->user_lastname : '')).'</a>';
								elseif ($user_info->display_name):
									$user_display = '<a href="#" class="user" data-user-id="'.esc_attr($appt['user']).'">'.esc_html($user_info->display_name).'</a>';
								else :
									$user_display = '<a href="#" class="user" data-user-id="'.esc_attr($appt['user']).'">'.esc_html($user_info->user_login).'</a>';
								endif;
							else :
								$user_display = esc_html__('(este usuario ya no existe)','pw-calendario');
							endif;
						else :
							$user_display = '<a href="#" class="user" data-user-id="0">'.$appt['guest_name'].' '.$appt['guest_surname'].'</a>';
						endif;

						if (current_user_can('manage_booked_options') && $appt['calendar']):

							$term_meta = get_option( "taxonomy_".$appt['calendar_id'] );
							if ( isset( $term_meta['notifications_user_id'] ) && $term_meta['notifications_user_id'] ):
								$calendar_owner_email = $term_meta['notifications_user_id'];
								$calendar_owner = get_user_by('email',$calendar_owner_email);
								$calendar_owner_name = booked_get_name($calendar_owner->ID);
							else:
								$calendar_owner_name = false;
							endif;

						else:

							$calendar_owner_name = false;

						endif;

						echo '<span class="appt-block" data-appt-id="'.esc_attr($appt['post_id']).'">';

							echo '<button data-appt-id="'.esc_attr($appt['post_id']).'" class="approve button button-primary">'.esc_html__('Aprobar','pw-calendario').'</button>';
							echo '<button class="delete button">'.esc_html__('Eliminar','pw-calendario').'</button>';

							echo $user_display;

							// Tamano del grupo, si es de mas de una persona.
							$pwcal_etiqueta = pwcal_etiqueta_personas( $appt['post_id'] );
							if ( $pwcal_etiqueta ) {
								echo ' <span class="pwcal-personas-etiqueta">'
									. esc_html( $pwcal_etiqueta ) . '</span>';
							}

							echo '<br>';
							if ($late_date > $date_to_compare): echo '<span class="late-appt">' . esc_html__('Esta cita ya ha pasado.','pw-calendario') . '</span><br>'; endif;
							if ($calendar_owner_name): echo '<i class="booked-icon booked-icon-user"></i>&nbsp;&nbsp;'.sprintf(esc_html__('Asignada a %s','pw-calendario'),esc_html($calendar_owner_name)).'<br>'; endif;
							if ($appt['calendar']): echo '<i class="booked-icon booked-icon-calendar"></i>&nbsp;&nbsp;<strong>'.esc_html($appt['calendar']).'</strong>: '; endif;
							echo esc_html($day_name.', '.$date_display);
							echo '<br><i class="booked-icon booked-icon-clock"></i>&nbsp;&nbsp;'.($title ? '<strong>'.esc_html($title).':</strong>&nbsp;&nbsp;' : '').$timeslotText;

						echo '</span>';

					echo '</div>';

				endforeach;

			endif;

			echo '<div class="pending-appt'.(!empty($appointments_array) ? ' no-pending-message' : '').'">';
				echo '<p style="text-align:center; margin:3em 0 2em">'.esc_html__('No hay citas pendientes.','pw-calendario').'</p>';
			echo '</div>';

		echo '</div>';

	endif;

	?>

	</div>
</div>
