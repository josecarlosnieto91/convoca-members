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
 * El circuito del voluntariado: de la solicitud a la desvinculación.
 *
 * Decidido con JC el 16/10/2026. Cinco pasos: solicitud, entrevista inicial, firma del acuerdo,
 * registro de horas y desvinculación. Antes el sistema sabía si alguien estaba aprobado o no, pero no
 * **en qué paso estaba**: eso se llevaba en la cabeza de quien coordina, y se perdía cuando esa
 * persona cambiaba de tarea.
 *
 * Dos reglas mandan sobre este circuito, y ninguna es negociable:
 *
 * 1. **No existe voluntariado que no sea socio.** El primer año se abona la cuota; a partir del
 *    segundo se puede elegir entre la cuota o las horas aprobadas del ciclo, según el mínimo del plan.
 *    Eso lo gobiernan `Roles_Miembro` y `CPT_Miembro`, no esta clase.
 * 2. **Se puede ser solo voluntario, o voluntario y monitor; nunca monitor solo.** El monitor está
 *    delante de una actividad, y esa posición la da el voluntariado: sin él no significa nada.
 *
 * @since 2.9.0
 */
class Circuito_Voluntariado {

	/** Pedido y aún sin nada más. */
	const ESTADO_SOLICITUD = 'solicitud';

	/** Citado (o con el cuestionario pendiente) para la entrevista inicial. */
	const ESTADO_ENTREVISTA = 'entrevista';

	/** Voluntariado en activo: puede acreditar horas y renovar por ellas. */
	const ESTADO_ACTIVO = 'activo';

	/** Se ha ido, con su causa y su fecha. Sus horas y su certificado se quedan. */
	const ESTADO_DESVINCULADO = 'desvinculado';

	/** Meta de la ficha: en qué paso está. */
	const META_ESTADO = '_convoca_voluntariado_estado';

	/** Meta de la ficha: la cita de la entrevista (fecha, hora, autor). */
	const META_ENTREVISTA = '_convoca_voluntariado_entrevista';

	/** Meta de la ficha: las respuestas del cuestionario, si lo hay. */
	const META_RESPUESTAS = '_convoca_voluntariado_respuestas';

	/** Meta de la ficha: la aceptación del acuerdo (fecha, autor, documento, hash). */
	const META_ACUERDO = '_convoca_voluntariado_acuerdo';

	/** Meta de la ficha: la desvinculación (fecha, causa, motivo, autor). */
	const META_DESVINCULACION = '_convoca_voluntariado_desvinculacion';

	/** Meta de la ficha: la petición de baja del propio voluntario, esperando a la junta. */
	const META_BAJA_SOLICITADA = '_convoca_voluntariado_baja_solicitada';

	/** Meta de la ficha: quién movió cada paso, y cuándo. */
	const META_HISTORIAL = '_convoca_voluntariado_historial';

	/** Causa: lo pide el propio voluntario. */
	const CAUSA_VOLUNTARIO = 'voluntario';

	/** Causa: ha caducado su membresía (automático). */
	const CAUSA_CADUCIDAD = 'caducidad';

	/** Causa: fuerza mayor de la junta. */
	const CAUSA_JUNTA = 'junta';

	/**
	 * Engancha los avisos de cada paso.
	 *
	 * Un aviso por paso, y solo cuando hay algo que hacer: citar (a la persona, con su cita), baja pedida (a
	 * quien la confirma) y desvinculación (a la persona, para que sepa que se ha cerrado).
	 */
	public static function init(): void {
		add_action( 'convoca_voluntariado_citado', array( __CLASS__, 'avisar_cita' ), 10, 2 );
		add_action( 'convoca_voluntariado_baja_solicitada', array( __CLASS__, 'avisar_baja_pedida' ), 10, 2 );
		add_action( 'convoca_voluntariado_desvinculado', array( __CLASS__, 'avisar_desvinculacion' ), 10, 2 );
	}

	/**
	 * Una fecha de la ficha, en el formato que lee una persona.
	 *
	 * Las fechas viven en ISO (Y-m-d) porque así se ordenan y se comparan; en un correo eso se lee fatal. Aquí
	 * se traducen al formato del sitio, y si no se puede leer se devuelve lo que había en vez de inventarse nada.
	 *
	 * @param string $fecha Fecha en ISO.
	 */
	public static function fecha_legible( string $fecha ): string {
		if ( '' === $fecha ) {
			return '';
		}

		// A las doce del mediodía y no a medianoche: `date_i18n` pinta en la zona del sitio, y una fecha a
		// las 00:00 se va al día anterior en cuanto el huso va por detrás (medido: salía «04/11» con una
		// fecha del 5). A mediodía ningún huso del mundo cambia el día.
		$marca = strtotime( $fecha . ' 12:00:00' );
		if ( false === $marca ) {
			return $fecha;
		}

		return (string) date_i18n( (string) get_option( 'date_format', 'd/m/Y' ), $marca );
	}

	/**
	 * A quién se avisa cuando hay algo que hacer: al correo del voluntariado y, si no lo hay, al de
	 * administración, que es donde ya llegan los demás avisos del plugin.
	 */
	public static function correo_de_la_junta(): string {
		$ajustes = (array) get_option( 'convoca_members_settings', array() );
		$correo  = sanitize_email( (string) ( $ajustes['voluntariado_email'] ?? '' ) );

		return '' !== $correo ? $correo : (string) get_option( 'admin_email' );
	}

	/**
	 * Avisa a la persona de su cita para la entrevista.
	 *
	 * @param int   $miembro_id Ficha del socio.
	 * @param array $cita       La cita (fecha, hora…).
	 */
	public static function avisar_cita( int $miembro_id, array $cita ): void {
		$usuario = self::usuario_de_ficha( $miembro_id );
		if ( $usuario <= 0 ) {
			return;
		}

		$persona = get_userdata( $usuario );
		if ( ! $persona ) {
			return;
		}

		// El texto vive en la plantilla editable (`voluntariado_cita`), no aquí. La fecha va ya en el
		// formato del sitio: si sale la de la ficha tal cual, el correo dice «2026-11-05» y eso no es lo que
		// lee una persona (medido en la demo).
		\Convoca\Core\Utils::do_action(
			'convoca_members_email_voluntariado_cita',
			'',
			$miembro_id,
			self::fecha_legible( $cita['fecha'] ),
			$cita['hora']
		);
	}

	/**
	 * Avisa a la junta de que alguien ha pedido la baja.
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $motivo     Lo que haya contado la persona.
	 */
	public static function avisar_baja_pedida( int $miembro_id, string $motivo ): void {
		$correo = self::correo_de_la_junta();
		if ( '' === $correo ) {
			return;
		}

		// Plantilla editable (`voluntariado_baja_solicitada`); la dirección la decide el ajuste del
		// correo del voluntariado (o, si está vacío, el de administración).
		\Convoca\Core\Utils::do_action(
			'convoca_members_email_voluntariado_baja_solicitada',
			'',
			$miembro_id,
			$motivo,
			$correo
		);
	}

	/**
	 * Avisa a la persona de que su desvinculación está aplicada.
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $causa      Causa de la desvinculación.
	 */
	public static function avisar_desvinculacion( int $miembro_id, string $causa ): void {
		$usuario = self::usuario_de_ficha( $miembro_id );
		$persona = $usuario > 0 ? get_userdata( $usuario ) : false;
		if ( ! $persona ) {
			return;
		}

		// Plantilla editable (`voluntariado_desvinculacion`).
		\Convoca\Core\Utils::do_action(
			'convoca_members_email_voluntariado_desvinculacion',
			'',
			$miembro_id,
			self::causas()[ $causa ] ?? $causa
		);
	}

	/**
	 * Los pasos, en orden, con su nombre para las pantallas.
	 *
	 * @return array<string,string>
	 */
	public static function pasos(): array {
		return array(
			self::ESTADO_SOLICITUD    => __( 'Solicitud', 'convoca-members' ),
			self::ESTADO_ENTREVISTA   => __( 'Entrevista inicial', 'convoca-members' ),
			self::ESTADO_ACTIVO       => __( 'Voluntariado en activo', 'convoca-members' ),
			self::ESTADO_DESVINCULADO => __( 'Desvinculación', 'convoca-members' ),
		);
	}

	/**
	 * Las causas de desvinculación, con su nombre para las pantallas.
	 *
	 * @return array<string,string>
	 */
	public static function causas(): array {
		return array(
			self::CAUSA_VOLUNTARIO => __( 'Lo pide el voluntario', 'convoca-members' ),
			self::CAUSA_CADUCIDAD  => __( 'Caduca su membresía', 'convoca-members' ),
			self::CAUSA_JUNTA      => __( 'Fuerza mayor de la junta', 'convoca-members' ),
		);
	}

	/**
	 * En qué paso está una persona.
	 *
	 * La verdad de «puede acreditar horas» no vive aquí: es la aprobación de siempre
	 * (`Admin_Voluntariado::META_APROBADO` en el usuario), que es lo que leen Enroll y Shifts. Esto
	 * **acompaña** a esa verdad y dice en qué punto del camino está la persona. Si las dos no
	 * cuadraran, manda la aprobación, y el paso se corrige con `sincronizar()`.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return string Uno de los estados.
	 */
	public static function estado( int $miembro_id ): string {
		if ( $miembro_id <= 0 ) {
			return self::ESTADO_SOLICITUD;
		}

		$guardado = (string) get_post_meta( $miembro_id, self::META_ESTADO, true );

		if ( self::ESTADO_DESVINCULADO === $guardado ) {
			return self::ESTADO_DESVINCULADO;
		}

		if ( Roles_Miembro::voluntario_aprobado( $miembro_id ) ) {
			return self::ESTADO_ACTIVO;
		}

		if ( '' !== $guardado && isset( self::pasos()[ $guardado ] ) ) {
			return $guardado;
		}

		return self::ESTADO_SOLICITUD;
	}

	/**
	 * Nombre del paso, para las pantallas.
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function etiqueta_estado( int $miembro_id ): string {
		$estado = self::estado( $miembro_id );
		$pasos  = self::pasos();

		return $pasos[ $estado ] ?? $estado;
	}

	/**
	 * La cita de la entrevista, si la hay.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array{fecha:string,hora:string,autor:int,cuando:string}
	 */
	public static function entrevista( int $miembro_id ): array {
		$cita = get_post_meta( $miembro_id, self::META_ENTREVISTA, true );
		$cita = is_array( $cita ) ? $cita : array();

		return array(
			'fecha'  => (string) ( $cita['fecha'] ?? '' ),
			'hora'   => (string) ( $cita['hora'] ?? '' ),
			'autor'  => (int) ( $cita['autor'] ?? 0 ),
			'cuando' => (string) ( $cita['cuando'] ?? '' ),
		);
	}

	/**
	 * Las respuestas del cuestionario de la entrevista.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array<string,string> Pregunta => respuesta.
	 */
	public static function respuestas( int $miembro_id ): array {
		$guardadas = get_post_meta( $miembro_id, self::META_RESPUESTAS, true );

		return is_array( $guardadas ) ? $guardadas : array();
	}

	/**
	 * La aceptación del acuerdo, si la hay.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array{fecha:string,autor:int,documento:int,huella:string}
	 */
	public static function acuerdo( int $miembro_id ): array {
		$aceptado = get_post_meta( $miembro_id, self::META_ACUERDO, true );
		$aceptado = is_array( $aceptado ) ? $aceptado : array();

		return array(
			'fecha'     => (string) ( $aceptado['fecha'] ?? '' ),
			'autor'     => (int) ( $aceptado['autor'] ?? 0 ),
			'documento' => (int) ( $aceptado['documento'] ?? 0 ),
			'huella'    => (string) ( $aceptado['huella'] ?? '' ),
		);
	}

	/**
	 * La desvinculación, si la hay.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array{fecha:string,causa:string,motivo:string,autor:int}
	 */
	public static function desvinculacion( int $miembro_id ): array {
		$datos = get_post_meta( $miembro_id, self::META_DESVINCULACION, true );
		$datos = is_array( $datos ) ? $datos : array();

		return array(
			'fecha'  => (string) ( $datos['fecha'] ?? '' ),
			'causa'  => (string) ( $datos['causa'] ?? '' ),
			'motivo' => (string) ( $datos['motivo'] ?? '' ),
			'autor'  => (int) ( $datos['autor'] ?? 0 ),
		);
	}

	/**
	 * ¿El acuerdo está aceptado?
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function acuerdo_aceptado( int $miembro_id ): bool {
		return '' !== self::acuerdo( $miembro_id )['fecha'];
	}

	/**
	 * ¿Ha pedido la baja y está esperando a la junta?
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function baja_solicitada( int $miembro_id ): bool {
		return '' !== (string) get_post_meta( $miembro_id, self::META_BAJA_SOLICITADA, true );
	}

	/**
	 * El historial de pasos: qué se movió, cuándo y quién lo movió.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array<int,array{paso:string,fecha:string,autor:int,nota:string}>
	 */
	public static function historial( int $miembro_id ): array {
		$historial = get_post_meta( $miembro_id, self::META_HISTORIAL, true );

		return is_array( $historial ) ? $historial : array();
	}

	/**
	 * Lo que le queda por hacer a esta persona, dicho para una pantalla.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return array<int,string>
	 */
	public static function faltantes( int $miembro_id ): array {
		$estado    = self::estado( $miembro_id );
		$faltantes = array();

		if ( self::ESTADO_DESVINCULADO === $estado ) {
			return array();
		}

		if ( self::ESTADO_SOLICITUD === $estado ) {
			$faltantes[] = __( 'Citar para la entrevista inicial', 'convoca-members' );
		}

		if ( self::ESTADO_ENTREVISTA === $estado ) {
			$cita        = self::entrevista( $miembro_id );
			$faltantes[] = '' !== $cita['fecha']
				? sprintf(
					/* translators: %s: interview date */
					__( 'Hacer la entrevista del %s (o marcar el cuestionario como recibido)', 'convoca-members' ),
					$cita['fecha']
				)
				: __( 'Hacer la entrevista inicial', 'convoca-members' );
		}

		if ( self::ESTADO_ACTIVO === $estado && ! self::acuerdo_aceptado( $miembro_id ) ) {
			$faltantes[] = __( 'Firmar el acuerdo de voluntariado', 'convoca-members' );
		}

		if ( self::baja_solicitada( $miembro_id ) ) {
			$faltantes[] = __( 'Confirmar la baja que ha pedido', 'convoca-members' );
		}

		return $faltantes;
	}

	/**
	 * Las preguntas del cuestionario de la entrevista.
	 *
	 * Cada organización tiene las suyas: se configuran en los ajustes, una por línea. Sin preguntas, la
	 * entrevista se resuelve marcándola como hecha desde la administración.
	 *
	 * @return array<int,string>
	 */
	public static function preguntas(): array {
		$ajustes = (array) get_option( 'convoca_members_settings', array() );
		$texto   = (string) ( $ajustes['entrevista_preguntas'] ?? '' );

		if ( '' === trim( $texto ) ) {
			return array();
		}

		$preguntas = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $texto ) as $linea ) {
			$linea = trim( (string) $linea );
			if ( '' !== $linea ) {
				$preguntas[] = $linea;
			}
		}

		return $preguntas;
	}

	/**
	 * Cita a una persona para la entrevista inicial (con fecha y hora).
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $fecha      Fecha de la cita (Y-m-d).
	 * @param string $hora       Hora de la cita (HH:MM), opcional.
	 * @return bool
	 */
	public static function citar_entrevista( int $miembro_id, string $fecha, string $hora = '' ): bool {
		if ( $miembro_id <= 0 || '' === $fecha ) {
			return false;
		}

		$cita = array(
			'fecha'  => $fecha,
			'hora'   => $hora,
			'autor'  => get_current_user_id(),
			'cuando' => current_time( 'mysql' ),
		);

		update_post_meta( $miembro_id, self::META_ENTREVISTA, $cita );
		self::guardar_estado( $miembro_id, self::ESTADO_ENTREVISTA );
		self::registrar( $miembro_id, self::ESTADO_ENTREVISTA, sprintf( '%s %s', $fecha, $hora ) );

		/**
		 * Se ha citado a una persona voluntaria para la entrevista inicial.
		 *
		 * @param int   $miembro_id Ficha del socio.
		 * @param array $cita       La cita (fecha, hora, autor, cuando).
		 */
		do_action( 'convoca_voluntariado_citado', $miembro_id, $cita );

		return true;
	}

	/**
	 * El cuestionario está recibido (o la entrevista hecha): desde aquí la persona **es** voluntaria.
	 *
	 * Decisión de JC (16/10/2026): «Una vez completado el formulario, o marcado como hecho desde el
	 * área de administración, ese socio será voluntario y/o monitor de actividades». La condición de
	 * voluntario la concede el camino de siempre (`Admin_Voluntariado::approve_volunteer()`), que es el
	 * que deja la aprobación donde la leen Enroll y Shifts; el monitor se **suma** aquí, y solo si hay
	 * voluntariado: nunca monitor solo.
	 *
	 * @param int                 $miembro_id Ficha del socio.
	 * @param array<string,string> $respuestas Pregunta => respuesta (vacío si se marca como hecha).
	 * @param bool                $monitor    Si además se le da la condición de monitor.
	 * @return bool
	 */
	public static function resolver_entrevista( int $miembro_id, array $respuestas = array(), bool $monitor = false ): bool {
		if ( $miembro_id <= 0 ) {
			return false;
		}

		if ( ! empty( $respuestas ) ) {
			update_post_meta( $miembro_id, self::META_RESPUESTAS, $respuestas );
		}

		$usuario = self::usuario_de_ficha( $miembro_id );
		if ( $usuario > 0 ) {
			Admin_Voluntariado::approve_volunteer( $usuario );
		} else {
			Roles_Miembro::anadir( $miembro_id, Roles_Miembro::VOLUNTARIO );
		}

		if ( $monitor ) {
			self::conceder_monitor( $miembro_id );
		}

		self::guardar_estado( $miembro_id, self::ESTADO_ACTIVO );
		self::registrar(
			$miembro_id,
			self::ESTADO_ACTIVO,
			empty( $respuestas ) ? __( 'entrevista marcada como hecha', 'convoca-members' ) : __( 'cuestionario contestado', 'convoca-members' )
		);

		return true;
	}

	/**
	 * Concede la condición de monitor, que **exige** la de voluntario.
	 *
	 * «Solo voluntario o voluntario y monitor, pero nunca monitor solo» (JC, 16/10/2026). La comprobación
	 * está en `Roles_Miembro::anadir()` —donde nace la condición— y se refuerza en
	 * `sincronizar_roles_wp()`, el único sitio donde se escriben los roles de WordPress.
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function conceder_monitor( int $miembro_id ): bool {
		if ( $miembro_id <= 0 ) {
			return false;
		}

		// La regla vive en `Roles_Miembro::anadir()`, que es donde nace la condición: aquí no se repite.
		$cambio = Roles_Miembro::anadir( $miembro_id, Roles_Miembro::MONITOR );
		if ( $cambio ) {
			self::registrar( $miembro_id, Roles_Miembro::MONITOR, __( 'condición de monitor', 'convoca-members' ) );
		}

		return $cambio;
	}

	/**
	 * Acepta el acuerdo de voluntariado (lo hace la persona, desde su panel).
	 *
	 * El documento se genera **firmado** con el camino que ya usan los demás documentos de Convoca
	 * (`PDF_Document`, con la huella, la IP y la marca de tiempo de `Convoca\Core\Signature`), y aquí
	 * queda la constancia de quién lo aceptó y cuándo.
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return bool
	 */
	public static function aceptar_acuerdo( int $miembro_id ): bool {
		if ( $miembro_id <= 0 || self::acuerdo_aceptado( $miembro_id ) ) {
			return false;
		}

		$usuario   = self::usuario_de_ficha( $miembro_id );
		$documento = 0;

		if ( $usuario > 0 && class_exists( '\\Convoca\\Members\\PDF_Document' ) ) {
			$documento = (int) ( PDF_Document::generate_volunteer_agreement( $usuario ) ?? 0 );
		}

		$aceptacion = array(
			'fecha'     => current_time( 'mysql' ),
			'autor'     => get_current_user_id(),
			'documento' => $documento,
			'huella'    => md5( $miembro_id . '|' . current_time( 'mysql' ) . '|' . $usuario ),
		);

		update_post_meta( $miembro_id, self::META_ACUERDO, $aceptacion );
		self::registrar( $miembro_id, 'acuerdo', __( 'acuerdo aceptado', 'convoca-members' ) );

		/**
		 * El voluntariado ha aceptado su acuerdo.
		 *
		 * @param int   $miembro_id Ficha del socio.
		 * @param array $aceptacion La constancia (fecha, autor, documento, huella).
		 */
		do_action( 'convoca_voluntariado_acuerdo_aceptado', $miembro_id, $aceptacion );

		return true;
	}

	/**
	 * El propio voluntario pide la baja: queda a la espera de que la junta la confirme.
	 *
	 * JC (16/10/2026): «La desvinculación, la pide el voluntario, o se aplica cuando caduca su membresía,
	 * o bien por causas de fuerza mayor de la junta». La petición no da de baja a nadie por sí sola: la
	 * confirma la junta, que es quien responde de las horas y los turnos comprometidos.
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $motivo     Lo que cuente la persona (opcional).
	 * @return bool
	 */
	public static function solicitar_desvinculacion( int $miembro_id, string $motivo = '' ): bool {
		if ( $miembro_id <= 0 || self::ESTADO_DESVINCULADO === self::estado( $miembro_id ) ) {
			return false;
		}

		update_post_meta(
			$miembro_id,
			self::META_BAJA_SOLICITADA,
			current_time( 'mysql' ) . '|' . get_current_user_id() . '|' . $motivo
		);
		self::registrar( $miembro_id, 'baja_solicitada', $motivo );

		do_action( 'convoca_voluntariado_baja_solicitada', $miembro_id, $motivo );

		return true;
	}

	/**
	 * Aplica la desvinculación, con su causa y su fecha.
	 *
	 * Sus horas **se quedan como están**, su certificado se puede seguir descargando y validando, y lo
	 * aprobado hasta esa fecha sigue contando (JC, 16/10/2026). Lo que se pierde es el permiso de
	 * voluntariado —y con él el de monitor, que sin voluntariado no significa nada—: se usan los
	 * caminos de siempre para que Shifts libere los turnos futuros.
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $causa      Una de las causas.
	 * @param string $motivo     Texto libre (opcional).
	 * @return bool
	 */
	public static function desvincular( int $miembro_id, string $causa = self::CAUSA_JUNTA, string $motivo = '' ): bool {
		if ( $miembro_id <= 0 ) {
			return false;
		}

		$usuario = self::usuario_de_ficha( $miembro_id );

		if ( $usuario > 0 && $usuario !== get_current_user_id() ) {
			Admin_Voluntariado::revoke_volunteer( $usuario );
		}

		// El voluntariado y el monitorado son condiciones de la ficha: se quitan las dos, y en ese
		// orden, para que la sincronización no deje un monitor suelto.
		Roles_Miembro::quitar( $miembro_id, Roles_Miembro::MONITOR );
		Roles_Miembro::quitar( $miembro_id, Roles_Miembro::VOLUNTARIO );

		update_post_meta(
			$miembro_id,
			self::META_DESVINCULACION,
			array(
				'fecha'  => current_time( 'mysql' ),
				'causa'  => $causa,
				'motivo' => $motivo,
				'autor'  => get_current_user_id(),
			)
		);
		delete_post_meta( $miembro_id, self::META_BAJA_SOLICITADA );
		self::guardar_estado( $miembro_id, self::ESTADO_DESVINCULADO );
		self::registrar( $miembro_id, self::ESTADO_DESVINCULADO, $causa . ( '' !== $motivo ? ': ' . $motivo : '' ) );

		/**
		 * Se ha desvinculado a una persona voluntaria.
		 *
		 * @param int    $miembro_id Ficha del socio.
		 * @param string $causa      Causa de la desvinculación.
		 */
		do_action( 'convoca_voluntariado_desvinculado', $miembro_id, $causa );

		return true;
	}

	/**
	 * Revisa las membresías caducadas y desvincula a quien ya no es socio.
	 *
	 * La membresía de un voluntariado puede decaer (cuota sin pagar y sin horas aprobadas): cuando
	 * pasa, la desvinculación se aplica sola, con su causa. Se llama desde el repaso diario.
	 *
	 * @return int Cuántas se han desvinculado.
	 */
	public static function revisar_caducidades(): int {
		$fichas = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => self::META_ESTADO,
						'value'   => self::ESTADO_DESVINCULADO,
						'compare' => '!=',
					),
					array(
						'key'     => Roles_Miembro::META_VOLUNTARIO,
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		$cuantas = 0;
		foreach ( $fichas as $ficha ) {
			$ficha = (int) $ficha;

			// Solo a quien YA era voluntariado aprobado y ha perdido la membresía: una solicitud que
			// nunca llegó a serlo no «se desvincula» de nada, se queda donde está hasta que la junta la
			// mire.
			if ( ! Roles_Miembro::voluntario_aprobado( $ficha ) ) {
				continue;
			}

			if ( ! Roles_Miembro::es_socio( $ficha ) ) {
				self::desvincular( $ficha, self::CAUSA_CADUCIDAD );
				++$cuantas;
			}
		}

		return $cuantas;
	}

	/**
	 * Pone el paso de acuerdo con la aprobación: por si alguien la cambió por su cuenta.
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function sincronizar( int $miembro_id ): void {
		if ( $miembro_id <= 0 || self::ESTADO_DESVINCULADO === self::estado( $miembro_id ) ) {
			return;
		}

		$aprobado = Roles_Miembro::voluntario_aprobado( $miembro_id );
		$estado   = (string) get_post_meta( $miembro_id, self::META_ESTADO, true );

		if ( $aprobado && self::ESTADO_ACTIVO !== $estado ) {
			self::guardar_estado( $miembro_id, self::ESTADO_ACTIVO );
		}

		if ( ! $aprobado && self::ESTADO_ACTIVO === $estado ) {
			self::guardar_estado( $miembro_id, self::ESTADO_ENTREVISTA );
		}
	}

	/**
	 * Todas las personas voluntarias, por paso: la consulta del tablero.
	 *
	 * @return array<int,array{ficha:int,nombre:string,email:string,estado:string,pendiente:bool,monitor:bool}>
	 */
	public static function personas(): array {
		// Quien está o ha estado en el circuito: con la condición puesta, o con un paso apuntado. Sin lo
		// segundo, al desvincular a alguien desaparecía del tablero —y con él su causa y su fecha—, que es
		// justo lo que la junta necesita tener delante.
		$fichas = get_posts(
			array(
				'post_type'      => 'miembro',
				'post_status'    => 'any',
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => Roles_Miembro::META_VOLUNTARIO,
						'value' => '1',
					),
					array(
						'key'     => self::META_ESTADO,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$personas = array();
		foreach ( $fichas as $ficha ) {
			$ficha   = (int) $ficha;
			$usuario = self::usuario_de_ficha( $ficha );
			$datos   = $usuario > 0 ? get_userdata( $usuario ) : false;

			$personas[] = array(
				'ficha'     => $ficha,
				'nombre'    => $datos ? ( $datos->first_name ?: $datos->display_name ) : get_the_title( $ficha ),
				'email'     => $datos ? $datos->user_email : (string) get_post_meta( $ficha, '_convoca_email', true ),
				'estado'    => self::estado( $ficha ),
				'pendiente' => ! empty( self::faltantes( $ficha ) ),
				'monitor'   => Roles_Miembro::es_monitor( $ficha ),
			);
		}

		return $personas;
	}

	/**
	 * El usuario de WordPress de una ficha (por vínculo directo; si no, por correo).
	 *
	 * @param int $miembro_id Ficha del socio.
	 * @return int ID del usuario, o 0.
	 */
	public static function usuario_de_ficha( int $miembro_id ): int {
		$usuario = (int) get_post_meta( $miembro_id, '_convoca_user_id', true );
		if ( $usuario > 0 ) {
			return $usuario;
		}

		$email = (string) get_post_meta( $miembro_id, '_convoca_email', true );
		if ( '' === $email ) {
			return 0;
		}

		$encontrado = get_user_by( 'email', $email );

		return $encontrado ? (int) $encontrado->ID : 0;
	}

	/**
	 * Guarda el paso en la ficha (sin registrar historial: eso lo hace `registrar()`).
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $estado     Paso nuevo.
	 */
	private static function guardar_estado( int $miembro_id, string $estado ): void {
		update_post_meta( $miembro_id, self::META_ESTADO, $estado );
	}

	/**
	 * Apunta en el historial quién movió el paso, cuándo y con qué nota.
	 *
	 * Sirve para lo mismo que la reconciliación de la cuota: poder responder «¿por qué esta persona es
	 * voluntaria hoy y ayer no?» sin depender de la memoria de nadie.
	 *
	 * @param int    $miembro_id Ficha del socio.
	 * @param string $paso       Paso o acción.
	 * @param string $nota       Nota corta.
	 */
	private static function registrar( int $miembro_id, string $paso, string $nota = '' ): void {
		$historial = self::historial( $miembro_id );

		$historial[] = array(
			'paso'  => $paso,
			'fecha' => current_time( 'mysql' ),
			'autor' => get_current_user_id(),
			'nota'  => $nota,
		);

		update_post_meta( $miembro_id, self::META_HISTORIAL, $historial );
	}
	/**
	 * El bloque del panel del socio: en qué paso está su voluntariado y qué le toca a él.
	 *
	 * Solo las acciones que decide la persona: contestar el cuestionario, aceptar el acuerdo y pedir la
	 * desvinculación. Lo que decide la junta —dar el paso a voluntariado, confirmar la baja— no se ofrece
	 * aquí, porque no es suyo.
	 *
	 * @param int $miembro_id Ficha del socio.
	 */
	public static function bloque_panel( int $miembro_id ): string {
		$estado = self::estado( $miembro_id );
		$pasos  = self::pasos();

		$html  = '<div id="conv-bloque-voluntariado" class="conv-card" style="margin-bottom:1.25rem">';
		$html .= '<h3>🧭 ' . esc_html__( 'Tu voluntariado', 'convoca-members' ) . '</h3>';
		$html .= '<p>' . sprintf(
			/* translators: %s: current step */
			esc_html__( 'Estás en: %s', 'convoca-members' ),
			'<strong>' . esc_html( $pasos[ $estado ] ?? $estado ) . '</strong>'
		) . '</p>';

		$faltantes = self::faltantes( $miembro_id );
		if ( ! empty( $faltantes ) ) {
			$html .= '<p>⚠ ' . esc_html( implode( ' · ', $faltantes ) ) . '</p>';
		}

		if ( self::ESTADO_ENTREVISTA === $estado ) {
			$cita = self::entrevista( $miembro_id );
			if ( '' !== $cita['fecha'] ) {
				$html .= '<p>' . sprintf(
					/* translators: %s: interview date and time */
					esc_html__( 'Tu entrevista: %s', 'convoca-members' ),
					esc_html( trim( $cita['fecha'] . ' ' . $cita['hora'] ) )
				) . '</p>';
			}

			$preguntas = self::preguntas();
			if ( ! empty( $preguntas ) ) {
				$html .= '<form data-convoca-voluntariado="cuestionario">';
				foreach ( $preguntas as $indice => $pregunta ) {
					$html .= '<p><label>' . esc_html( $pregunta ) . '<br>'
						. '<textarea name="respuestas[' . (int) $indice . ']" rows="3" style="width:100%"></textarea></label></p>';
				}
				$html .= '<button type="submit" class="conv-btn">' . esc_html__( 'Enviar mis respuestas', 'convoca-members' ) . '</button></form>';
			}
		}

		if ( self::ESTADO_ACTIVO === $estado && ! self::acuerdo_aceptado( $miembro_id ) ) {
			$html .= '<form data-convoca-voluntariado="acuerdo">'
				. '<p>' . esc_html__( 'Te falta aceptar el acuerdo de voluntariado. Al aceptarlo se genera tu acuerdo firmado, con su fecha, y queda guardado.', 'convoca-members' ) . '</p>'
				. '<button type="submit" class="conv-btn">' . esc_html__( 'Acepto el acuerdo de voluntariado', 'convoca-members' ) . '</button></form>';
		}

		if ( self::ESTADO_ACTIVO === $estado ) {
			if ( self::baja_solicitada( $miembro_id ) ) {
				$html .= '<p>' . esc_html__( 'Has pedido desvincularte: la junta lo confirma en cuanto lo vea.', 'convoca-members' ) . '</p>';
			} else {
				$html .= '<details><summary>' . esc_html__( 'Quiero desvincularme', 'convoca-members' ) . '</summary>'
					. '<form data-convoca-voluntariado="baja">'
					. '<p><label>' . esc_html__( '¿Nos cuentas por qué? (opcional)', 'convoca-members' ) . '<br>'
					. '<textarea name="motivo" rows="3" style="width:100%"></textarea></label></p>'
					. '<p>' . esc_html__( 'Tus horas quedan registradas y podrás descargar tu certificado igual.', 'convoca-members' ) . '</p>'
					. '<button type="submit" class="conv-btn">' . esc_html__( 'Pedir la desvinculación', 'convoca-members' ) . '</button></form></details>';
			}
		}

		if ( self::ESTADO_DESVINCULADO === $estado ) {
			$baja  = self::desvinculacion( $miembro_id );
			$html .= '<p>' . sprintf(
				/* translators: 1: date, 2: reason for leaving */
				esc_html__( 'Tu etapa como voluntario se cerró el %1$s (%2$s).', 'convoca-members' ),
				esc_html( substr( $baja['fecha'], 0, 10 ) ),
				esc_html( self::causas()[ $baja['causa'] ] ?? $baja['causa'] )
			) . '</p>';
			$html .= '<p>' . esc_html__( 'Tus horas quedan registradas y tu certificado se puede seguir descargando y validando.', 'convoca-members' ) . '</p>';
		}

		if ( Roles_Miembro::es_monitor( $miembro_id ) ) {
			$html .= '<p>' . esc_html__( 'Además, eres monitor de actividades: puedes acreditar horas de otras personas.', 'convoca-members' ) . '</p>';
		}

		$html .= '</div>';

		/**
		 * El bloque del circuito del voluntariado en el panel del socio.
		 *
		 * @param string $html       El HTML del bloque.
		 * @param int    $miembro_id Ficha del socio.
		 */
		return (string) apply_filters( 'convoca_voluntariado_bloque_panel', $html, $miembro_id );
	}
}
