<?php
/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Volunteer management (approval/revocation) under the Members admin menu.
 *
 * Owned by Convoca Members since 2026-09-10 (moved from convoca-shifts).
 * Convoca Shifts only consumes the volunteer state (role voluntario_aprobado /
 * cap gestionar_mis_turnos) for shifts and hours; it listens to the
 * convoca_voluntario_revocado hook to release future shifts.
 */
class Admin_Voluntariado {

	/**
	 * Página única de voluntariado (registrada desde Admin_Page bajo el menú
	 * «Miembros»). Antes tenía su propio submenú, duplicando «Voluntarios».
	 */
	const SLUG = 'conv-members-voluntarios';

	const META_APROBADO = '_convoca_voluntario_aprobado'; // 0 = pending, 1 = approved, -1 = revoked.

	/**
	 * Regla única: ¿este usuario puede acreditar horas de voluntariado?
	 *
	 * El compromiso del alta es una **solicitud**; las horas —que valen para renovar sin cuota y
	 * para los certificados— exigen la **aprobación** de la asociación. La fuente de verdad es el
	 * usuario de WordPress, que es donde escribe la aprobación (rol y meta) y donde ya miran Enroll
	 * y Shifts.
	 *
	 * Sin cuenta de WordPress no hay permiso posible: no hay dónde registrar la aprobación.
	 *
	 * @param int $user_id Usuario de WordPress.
	 */
	public static function puede_acreditar_horas( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles    = (array) $user->roles;
		$aprobado = '1' === (string) get_user_meta( $user_id, self::META_APROBADO, true );

		// Quién acredita horas (decisión de JC, 15/10/2026: «aprobación de monitor o administrador»):
		// el voluntariado aprobado —lo de siempre—, el **monitor de actividad**, que es quien está delante
		// de la actividad, y quien administra el sitio. Un socio no acredita horas de otros por ser socio:
		// su condición no le da ese permiso.
		return in_array( 'voluntario_aprobado', $roles, true )
			|| in_array( 'monitor_actividad', $roles, true )
			|| ( function_exists( 'user_can' ) && user_can( $user, 'manage_options' ) )
			|| $aprobado;
	}

	/**
	 * Ficha de socio vinculada a un usuario (por vínculo directo o por email).
	 *
	 * @param int $user_id Usuario de WordPress.
	 * @return int ID de la ficha, o 0.
	 */
	private static function ficha_de_usuario( int $user_id ): int {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 0;
		}

		$fichas = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_convoca_user_id',
				'meta_value'     => (string) $user_id,
			)
		);

		if ( empty( $fichas ) ) {
			$fichas = get_posts(
				array(
					'post_type'      => 'miembro',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_convoca_email',
					'meta_value'     => $user->user_email,
				)
			);
		}

		return empty( $fichas ) ? 0 : (int) $fichas[0];
	}

	/**
	 * Render the volunteer page (standalone: contenedor + título).
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'convoca-members' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Voluntarios', 'convoca-members' ) . '</h1>';
		self::render_management();
		echo '</div>';
	}

	/**
	 * Sección de gestión (solicitudes pendientes + voluntarios activos), sin
	 * contenedor ni título: se incrusta en la página unificada de Voluntarios,
	 * junto al listado de voluntarios.
	 */
	public static function render_management(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_actions();

		$pending_users = self::get_pending_users();
		$active_users  = self::get_active_users();

		echo '<p>' . esc_html__( 'Solicitudes de personas que quieren colaborar como voluntarias en el centro. Al aprobar se activa su ficha de miembro y puede gestionar turnos.', 'convoca-members' ) . '</p>';

		// ── PENDING ──.
		echo '<h2 class="title">' . esc_html__( 'Solicitudes Pendientes', 'convoca-members' ) . '</h2>';
		if ( ! empty( $pending_users ) ) {
			echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
			echo '<thead><tr><th>' . esc_html__( 'Nombre', 'convoca-members' ) . '</th><th>' . esc_html__( 'Email', 'convoca-members' ) . '</th><th>' . esc_html__( 'Teléfono', 'convoca-members' ) . '</th><th>' . esc_html__( 'Motivación', 'convoca-members' ) . '</th><th>' . esc_html__( 'Acciones', 'convoca-members' ) . '</th></tr></thead><tbody>';
			foreach ( $pending_users as $user ) {
				$telefono   = get_user_meta( $user->ID, '_convoca_telefono', true );
				$motivacion = get_user_meta( $user->ID, '_convoca_voluntario_motivacion', true );
				$approve    = wp_nonce_url(
					admin_url( 'admin.php?page=' . self::SLUG . '&action=approve&user=' . $user->ID ),
					'convoca_volunteer_approve_' . $user->ID
				);

				echo '<tr>';
				echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
				echo '<td>' . esc_html( $telefono ?: '—' ) . '</td>';
				echo '<td>' . nl2br( esc_html( $motivacion ?: '' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $approve ) . '" class="button button-primary">' . esc_html__( 'Aprobar Voluntario', 'convoca-members' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No hay solicitudes pendientes.', 'convoca-members' ) . '</p>';
		}

		echo '<hr style="margin: 40px 0;">';

		self::render_circuito();

		echo '<hr style="margin: 40px 0;">';

		// ── ACTIVE ──.
		echo '<h2 class="title">' . esc_html__( 'Voluntarios Activos', 'convoca-members' ) . '</h2>';
		if ( ! empty( $active_users ) ) {
			echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
			echo '<thead><tr><th>' . esc_html__( 'Nombre', 'convoca-members' ) . '</th><th>' . esc_html__( 'Email', 'convoca-members' ) . '</th><th>' . esc_html__( 'Teléfono', 'convoca-members' ) . '</th><th>' . esc_html__( 'Ficha', 'convoca-members' ) . '</th><th>' . esc_html__( 'Acciones', 'convoca-members' ) . '</th></tr></thead><tbody>';
			foreach ( $active_users as $user ) {
				$telefono = get_user_meta( $user->ID, '_convoca_telefono', true );
				$member   = (int) get_user_meta( $user->ID, '_convoca_member_id', true );
				$edit     = $member ? admin_url( 'post.php?post=' . $member . '&action=edit' ) : '';
				$revoke   = wp_nonce_url(
					admin_url( 'admin.php?page=' . self::SLUG . '&action=revoke&user=' . $user->ID ),
					'convoca_volunteer_revoke_' . $user->ID
				);

				echo '<tr>';
				echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
				echo '<td>' . esc_html( $telefono ?: '—' ) . '</td>';
				echo '<td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Ver ficha', 'convoca-members' ) . '</a>' : '—' ) . '</td>';
				echo '<td><a href="' . esc_url( $revoke ) . '" class="button" onclick="return confirm(\'' . esc_js( esc_html__( '¿Revocar los permisos de voluntario? Sus turnos futuros quedarán liberados.', 'convoca-members' ) ) . '\');">' . esc_html__( 'Revocar Permisos', 'convoca-members' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No hay voluntarios activos registrados.', 'convoca-members' ) . '</p>';
		}
	}

	/**
	 * El tablero del circuito: en qué paso está cada persona y qué queda por hacer.
	 *
	 * Es la pantalla donde la junta deja de llevar el circuito en la cabeza. Cada fila trae las acciones
	 * del paso en el que está la persona, y nada más: lo que no toca no se ofrece.
	 */
	public static function render_circuito(): void {
		$personas = \Convoca\Members\Circuito_Voluntariado::personas();
		$pasos    = \Convoca\Members\Circuito_Voluntariado::pasos();
		$causas   = \Convoca\Members\Circuito_Voluntariado::causas();

		$por_paso = array();
		foreach ( $personas as $persona ) {
			$por_paso[ $persona['estado'] ][] = $persona;
		}

		echo '<h2 class="title">' . esc_html__( 'El circuito del voluntariado', 'convoca-members' ) . '</h2>';
		echo '<p>' . esc_html__( 'En qué punto está cada persona: solicitud, entrevista inicial, voluntariado en activo y desvinculación. Lo que queda pendiente sale marcado con ⚠.', 'convoca-members' ) . '</p>';

		foreach ( $pasos as $estado => $etiqueta ) {
			$gente = $por_paso[ $estado ] ?? array();
			printf(
				'<h3>%s <span class="count">(%d)</span></h3>',
				esc_html( $etiqueta ),
				count( $gente )
			);

			if ( empty( $gente ) ) {
				echo '<p>' . esc_html__( 'Nadie en este paso.', 'convoca-members' ) . '</p>';
				continue;
			}

			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr><th>' . esc_html__( 'Nombre', 'convoca-members' ) . '</th><th>' . esc_html__( 'Email', 'convoca-members' ) . '</th><th>' . esc_html__( 'Pendiente', 'convoca-members' ) . '</th><th>' . esc_html__( 'Acciones', 'convoca-members' ) . '</th></tr></thead><tbody>';

			foreach ( $gente as $persona ) {
				echo '<tr>';
				echo '<td><strong>' . esc_html( $persona['nombre'] ) . '</strong>' . ( $persona['monitor'] ? ' <span class="convoca-badge">' . esc_html__( 'monitor', 'convoca-members' ) . '</span>' : '' ) . '</td>';
				echo '<td>' . esc_html( $persona['email'] ) . '</td>';
				echo '<td>' . ( $persona['pendiente'] ? '⚠ ' . esc_html( implode( ' · ', \Convoca\Members\Circuito_Voluntariado::faltantes( $persona['ficha'] ) ) ) : '—' ) . '</td>';
				echo '<td>' . self::acciones_de( $persona, $causas ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Las acciones que se ofrecen para una persona, según el paso en el que está.
	 *
	 * @param array<string,mixed>  $persona Datos de la persona (ficha, estado…).
	 * @param array<string,string> $causas  Causas de desvinculación.
	 */
	private static function acciones_de( array $persona, array $causas ): string {
		$ficha = (int) $persona['ficha'];
		$html  = array();

		if ( \Convoca\Members\Circuito_Voluntariado::ESTADO_DESVINCULADO === $persona['estado'] ) {
			$motivo = \Convoca\Members\Circuito_Voluntariado::desvinculacion( $ficha );
			return '<em>' . esc_html(
				sprintf(
				/* translators: 1: date, 2: reason */
					__( '%1$s · %2$s', 'convoca-members' ),
					substr( $motivo['fecha'], 0, 10 ),
					$causas[ $motivo['causa'] ] ?? $motivo['causa']
				) 
			) . '</em>';
		}

		$nonce = static fn( string $accion ): string => wp_nonce_url(
			admin_url( 'admin.php?page=' . self::SLUG . '&action=' . $accion . '&ficha=' . $ficha ),
			'convoca_circuito_' . $accion . '_' . $ficha
		);

		if ( in_array( $persona['estado'], array( \Convoca\Members\Circuito_Voluntariado::ESTADO_SOLICITUD, \Convoca\Members\Circuito_Voluntariado::ESTADO_ENTREVISTA ), true ) ) {
			$hoy    = current_time( 'Y-m-d' );
			$html[] = '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:inline-flex;gap:4px;align-items:center;">'
				. '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">'
				. '<input type="hidden" name="action" value="citar">'
				. '<input type="hidden" name="ficha" value="' . esc_attr( (string) $ficha ) . '">'
				. wp_nonce_field( 'convoca_circuito_citar_' . $ficha, '_wpnonce', true, false )
				. '<input type="date" name="fecha" value="' . esc_attr( $hoy ) . '" required>'
				. '<input type="time" name="hora" value="">'
				. '<button class="button">' . esc_html__( 'Citar entrevista', 'convoca-members' ) . '</button>'
				. '</form> ';
			$html[] = self::boton_post( 'entrevista_hecha', __( 'Entrevista hecha', 'convoca-members' ), $ficha, 'button-primary' );
			$html[] = self::boton_post( 'entrevista_monitor', __( 'Entrevista hecha + monitor', 'convoca-members' ), $ficha, 'button' );
		}

		if ( \Convoca\Members\Circuito_Voluntariado::ESTADO_ACTIVO === $persona['estado'] ) {
			if ( \Convoca\Members\Circuito_Voluntariado::baja_solicitada( $ficha ) ) {
				$html[] = self::boton_post( 'confirmar_baja', __( '⚠ Confirmar la baja que pide', 'convoca-members' ), $ficha, 'button-primary' );
			}
			$html[] = '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:inline-flex;gap:4px;align-items:center;">'
				. '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">'
				. '<input type="hidden" name="action" value="desvincular">'
				. '<input type="hidden" name="ficha" value="' . esc_attr( (string) $ficha ) . '">'
				. wp_nonce_field( 'convoca_circuito_desvincular_' . $ficha, '_wpnonce', true, false )
				. '<select name="causa">';
			foreach ( $causas as $clave => $nombre ) {
				$html[] = '<option value="' . esc_attr( $clave ) . '">' . esc_html( $nombre ) . '</option>';
			}
			$html[] = '</select><input type="text" name="motivo" placeholder="' . esc_attr__( 'Motivo (opcional)', 'convoca-members' ) . '">'
				. '<button class="button">' . esc_html__( 'Desvincular', 'convoca-members' ) . '</button></form>';
		}

		return implode( ' ', $html );
	}

	/**
	 * Un botón que manda una acción por POST, con su nonce.
	 *
	 * Antes eran enlaces `<a href>`: la acción viajaba en la URL y se ejecutaba con un GET (members#63).
	 *
	 * @param string $accion Acción del circuito.
	 * @param string $texto  Rótulo del botón.
	 * @param int    $ficha  Ficha del socio.
	 * @param string $clase  Clase CSS del botón.
	 * @return string HTML del formulario.
	 */
	private static function boton_post( string $accion, string $texto, int $ficha, string $clase ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:inline;">'
			. '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( $accion ) . '">'
			. '<input type="hidden" name="ficha" value="' . esc_attr( (string) $ficha ) . '">'
			. wp_nonce_field( 'convoca_circuito_' . $accion . '_' . $ficha, '_wpnonce', true, false )
			. '<button class="button ' . esc_attr( $clase ) . '">' . esc_html( $texto ) . '</button>'
			. '</form> ';
	}

	/**
	 * Un parámetro de la petición: POST primero, GET como respaldo.
	 *
	 * La lectura se hace con respaldo para que un enlace antiguo no se rompa; lo que **no** se admite por
	 * GET es la acción, que es la que cambia el estado (members#63).
	 *
	 * @param string $clave Nombre del parámetro.
	 * @return string Valor sin escapar (lo sanean los llamadores).
	 */
	private static function param( string $clave ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- la acción ya se comprueba con check_admin_referer.
		return (string) wp_unslash( $_POST[ $clave ] ?? $_GET[ $clave ] ?? '' );
	}

	/**
	 * Handle approve/revoke actions.
	 */
	private static function handle_actions(): void {
		// La acción se lee SOLO de POST: estas acciones desvinculan, confirman una baja o mueven el
		// circuito, y con GET basta un prefetch del navegador, un escáner o un clic accidental en un
		// enlace para dispararlas (members#63). GET para leer, POST para cambiar.
		$action = sanitize_key( (string) wp_unslash( $_POST['action'] ?? '' ) );
		$user   = (int) ( wp_unslash( $_POST['user'] ?? $_GET['user'] ?? 0 ) );

		$circuito = array( 'citar', 'entrevista_hecha', 'entrevista_monitor', 'desvincular', 'confirmar_baja' );

		if ( in_array( $action, $circuito, true ) ) {
			self::handle_circuito( $action );
			return;
		}

		if ( ! in_array( $action, array( 'approve', 'revoke' ), true ) || ! $user ) {
			return;
		}

		if ( 'approve' === $action ) {
			check_admin_referer( 'convoca_volunteer_approve_' . $user );
			self::approve_volunteer( $user );
		} else {
			check_admin_referer( 'convoca_volunteer_revoke_' . $user );
			self::revoke_volunteer( $user );
		}
	}

	/**
	 * Mueve un paso del circuito desde la administración.
	 *
	 * @param string $action Acción pedida.
	 */
	private static function handle_circuito( string $action ): void {
		$ficha = (int) self::param( 'ficha' );
		if ( $ficha <= 0 ) {
			return;
		}

		check_admin_referer( 'convoca_circuito_' . $action . '_' . $ficha );

		switch ( $action ) {
			case 'citar':
				$fecha = sanitize_text_field( self::param( 'fecha' ) );
				$hora  = sanitize_text_field( self::param( 'hora' ) );
				\Convoca\Members\Circuito_Voluntariado::citar_entrevista( $ficha, $fecha, $hora );
				break;

			case 'entrevista_hecha':
				// La junta puede dar la entrevista por hecha sin cuestionario: es su decisión, no un envío en
				// blanco (members#52).
				\Convoca\Members\Circuito_Voluntariado::resolver_entrevista( $ficha, array(), false, true );
				break;

			case 'entrevista_monitor':
				\Convoca\Members\Circuito_Voluntariado::resolver_entrevista( $ficha, array(), true, true );
				break;

			case 'confirmar_baja':
				\Convoca\Members\Circuito_Voluntariado::desvincular( $ficha, \Convoca\Members\Circuito_Voluntariado::CAUSA_VOLUNTARIO );
				break;

			case 'desvincular':
				$causa  = sanitize_key( self::param( 'causa' ) );
				$motivo = sanitize_text_field( self::param( 'motivo' ) );
				if ( ! isset( \Convoca\Members\Circuito_Voluntariado::causas()[ $causa ] ) ) {
					$causa = \Convoca\Members\Circuito_Voluntariado::CAUSA_JUNTA;
				}
				\Convoca\Members\Circuito_Voluntariado::desvincular( $ficha, $causa, $motivo );
				break;
		}
	}

	/**
	 * Approve a volunteer: grant role, mark approved, notify (email + PDF attachment).
	 *
	 * @param int $user_id WP user ID.
	 */
	public static function approve_volunteer( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// La condición de voluntario se SUMA a las que ya tenga: quien además es socio sigue
		// siéndolo. Antes se usaba set_role(), que sustituye todos los roles del usuario.
		$ficha = self::ficha_de_usuario( $user_id );

		// La aprobación se marca ANTES de conceder la condición: desde members#30 la marca de voluntariado
		// sólo vale si está aprobada, así que al revés la propia aprobación no concedería nada.
		update_user_meta( $user_id, self::META_APROBADO, '1' );

		if ( $ficha ) {
			\Convoca\Members\Roles_Miembro::anadir( $ficha, \Convoca\Members\Roles_Miembro::VOLUNTARIO );
		}

		// El rol de WordPress lo consumen Enroll y Shifts: se garantiza sin tocar los demás.
		if ( ! in_array( 'voluntario_aprobado', (array) $user->roles, true ) ) {
			$user->add_role( 'voluntario_aprobado' );
		}

		// Mirror legacy members activation: activate member, PDF, role guarantee.
		do_action( 'convoca_voluntario_aprobado', $user_id );

		$attachments = apply_filters( 'convoca_voluntario_aprobado_attachments', array(), $user_id );
		wp_mail(
			$user->user_email,
			__( '¡Solicitud de voluntariado aprobada!', 'convoca-members' ),
			__( 'Hola, ya puedes acceder y gestionar turnos en el centro social. Adjunto a este correo encontrarás tu Acuerdo de Incorporación si procede.', 'convoca-members' ),
			'',
			$attachments
		);

		\Convoca\Core\Logger::info(
			sprintf( 'Voluntario aprobado: %s (#%d)', $user->display_name, $user_id ),
			'Members/Voluntariado'
		);
	}

	/**
	 * Revoke a volunteer: remove role, mark revoked, notify Shifts to release future shifts.
	 *
	 * @param int $user_id WP user ID.
	 */
	public static function revoke_volunteer( int $user_id ): void {
		if ( $user_id === get_current_user_id() ) {
			\Convoca\Core\Utils::admin_notice( esc_html__( 'No puedes revocarte tus propios permisos.', 'convoca-members' ), 'warning' );
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Se quita solo el permiso de voluntariado: si la persona además es socia, sigue
		// siéndolo. Antes, set_role( 'subscriber' ) borraba cualquier otro rol.
		$ficha = self::ficha_de_usuario( $user_id );
		if ( $ficha ) {
			\Convoca\Members\Roles_Miembro::quitar( $ficha, \Convoca\Members\Roles_Miembro::VOLUNTARIO );
		}

		$user->remove_role( 'voluntario_aprobado' );
		if ( empty( array_diff( (array) $user->roles, array( 'voluntario_aprobado' ) ) ) ) {
			$user->add_role( 'subscriber' );
		}

		update_user_meta( $user_id, self::META_APROBADO, '-1' );

		// Let Shifts (and any other consumer) release future shifts for this volunteer.
		do_action( 'convoca_voluntario_revocado', $user_id );

		\Convoca\Core\Logger::info(
			sprintf( 'Permisos de voluntario revocados: %s (#%d)', $user->display_name, $user_id ),
			'Members/Voluntariado'
		);
	}

	/**
	 * Pending volunteers: users with approval meta = 0.
	 *
	 * @return \WP_User[]
	 */
	private static function get_pending_users(): array {
		$q = new \WP_User_Query(
			array(
				'meta_key'   => self::META_APROBADO,
				'meta_value' => '0',
			)
		);
		return $q->get_results();
	}

	/**
	 * Active volunteers: users with the volunteer role.
	 *
	 * @return \WP_User[]
	 */
	private static function get_active_users(): array {
		$q = new \WP_User_Query(
			array(
				'role' => 'voluntario_aprobado',
			)
		);
		return $q->get_results();
	}
}
