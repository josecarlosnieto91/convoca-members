<?php
/**
 * Convoca Members
 *
 * Roles acumulativos de la ficha de miembro: una persona puede ser socia y
 * voluntaria a la vez, y cada condición se añade y se quita por separado.
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members;

/**
 * Única pieza que decide quién es socio y quién es voluntario.
 *
 * Antes de esto, la condición de socio vivía en la ficha (`_convoca_estado_miembro`,
 * `_convoca_plan`) y la de voluntario en el usuario de WordPress (rol
 * `voluntario_aprobado` + meta `_convoca_voluntario_aprobado`), y quien las tocaba
 * usaba `set_role()`, que sustituye TODOS los roles del usuario: aprobar a un socio
 * como voluntario le quitaba su condición, y revocarle la dejaba en `subscriber`.
 *
 * Aquí los roles son acumulativos: `anadir()` nunca quita el otro y `quitar()` solo
 * quita el suyo. El estado se guarda en la ficha (meta), y se refleja —sin pisar— en
 * el usuario de WordPress y en la taxonomía `tipo_miembro`, que existía registrada
 * pero vacía.
 */
final class Roles_Miembro {

	/** Condición de socio: cuota al día o ciclo cumplido. */
	public const SOCIO = 'socio';

	/** Condición de voluntario: aprobado por la asociación para acreditar horas. */
	public const VOLUNTARIO = 'voluntario';

	/** Meta de la ficha: es socio. */
	public const META_SOCIO = '_convoca_es_socio';

	/** Meta de la ficha: es voluntario (ya la escribía la aprobación de voluntariado). */
	public const META_VOLUNTARIO = '_convoca_es_voluntario';

	/** Rol de WordPress que acredita para registrar horas (lo consumen Shifts y Enroll). */
	public const ROL_WP_VOLUNTARIO = 'voluntario_aprobado';

	/**
	 * Rol de WordPress que acredita la condición de socio.
	 *
	 * No existía: los socios eran `subscriber` y su condición vivía en una meta, así que no había forma de
	 * conceder nada a «los socios» sin mirar la ficha una a una (issue #30). El rol lleva `read` y poco más:
	 * el panel del socio se abre con la sesión, no por capacidad, y no se le da acceso al escritorio.
	 */
	public const ROL_WP_SOCIO = 'socio';

	/** Rol de WordPress del monitor de actividad (lo crea el plugin y lo usa Enroll). */
	public const ROL_WP_MONITOR = 'monitor_actividad';

	/** Rol con el que se queda un usuario al que se le quitan los roles del ecosistema. */
	public const ROL_WP_POR_DEFECTO = 'subscriber';

	/** Taxonomía donde se reflejan las condiciones, para el listado y los filtros. */
	public const TAXONOMIA = 'tipo_miembro';

	/** Meta: por qué vía se ha cumplido el ciclo en curso (`pago` u `horas`). */
	public const META_CUMPLIMIENTO = '_convoca_cumplimiento_ciclo';

	/** Meta: cuándo se cumplió el ciclo en curso. */
	public const META_CUMPLIMIENTO_FECHA = '_convoca_cumplimiento_fecha';

	/** Condición de monitor de actividad: se puede tener además de las otras dos. */
	public const MONITOR = 'monitor';

	/** Meta de la ficha: es monitor. */
	public const META_MONITOR = '_convoca_es_monitor';

	/** Vías válidas de cumplimiento. */
	public const VIAS = array( 'pago', 'horas' );

	/**
	 * Meta que guarda cada rol.
	 *
	 * @param string $rol Rol del ecosistema.
	 * @return string Meta, o cadena vacía si el rol no existe.
	 */
	private static function meta_de( string $rol ): string {
		if ( self::SOCIO === $rol ) {
			return self::META_SOCIO;
		}
		if ( self::VOLUNTARIO === $rol ) {
			return self::META_VOLUNTARIO;
		}
		if ( self::MONITOR === $rol ) {
			return self::META_MONITOR;
		}
		return '';
	}

	/**
	 * Roles válidos.
	 *
	 * @return string[]
	 */
	public static function roles_validos(): array {
		return array( self::SOCIO, self::VOLUNTARIO, self::MONITOR );
	}

	/**
	 * Condiciones que tiene hoy una ficha.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return string[] Roles activos (`socio`, `voluntario`).
	 */
	public static function roles( int $miembro_id ): array {
		$roles = array();
		foreach ( self::roles_validos() as $rol ) {
			if ( self::tiene( $miembro_id, $rol ) ) {
				$roles[] = $rol;
			}
		}
		return $roles;
	}

	/**
	 * ¿Tiene esta condición?
	 *
	 * @param int    $miembro_id ID de la ficha.
	 * @param string $rol        Rol del ecosistema.
	 * @return bool
	 */
	public static function tiene( int $miembro_id, string $rol ): bool {
		if ( $miembro_id <= 0 || '' === self::meta_de( $rol ) ) {
			return false;
		}
		return '1' === (string) get_post_meta( $miembro_id, self::meta_de( $rol ), true );
	}

	/**
	 * ¿Es socio?
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function es_socio( int $miembro_id ): bool {
		return self::tiene( $miembro_id, self::SOCIO );
	}

	/**
	 * ¿Es voluntario?
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function es_voluntario( int $miembro_id ): bool {
		return self::tiene( $miembro_id, self::VOLUNTARIO );
	}

	/**
	 * Etiqueta legible de una condición, traducida.
	 *
	 * Vive aquí y no en cada pantalla que la pinta: el listado de socios y el panel del socio tienen que
	 * decir lo mismo de la misma condición.
	 *
	 * @param string $rol Condición (`socio`, `voluntario` o `monitor`).
	 * @return string
	 */
	public static function etiqueta( string $rol ): string {
		switch ( $rol ) {
			case self::SOCIO:
				return __( 'Socio', 'convoca-members' );
			case self::VOLUNTARIO:
				return __( 'Voluntario', 'convoca-members' );
			case self::MONITOR:
				return __( 'Monitor', 'convoca-members' );
			default:
				return $rol;
		}
	}

	/**
	 * ¿Es monitor de actividad?
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function es_monitor( int $miembro_id ): bool {
		return self::tiene( $miembro_id, self::MONITOR );
	}

	/**
	 * Rol de WordPress que refleja cada condición.
	 *
	 * @param string $rol Rol del ecosistema.
	 * @return string Nombre del rol de WordPress, o cadena vacía.
	 */
	public static function rol_wp_de( string $rol ): string {
		switch ( $rol ) {
			case self::SOCIO:
				return self::ROL_WP_SOCIO;
			case self::VOLUNTARIO:
				return self::ROL_WP_VOLUNTARIO;
			case self::MONITOR:
				return self::ROL_WP_MONITOR;
			default:
				return '';
		}
	}

	/**
	 * Crea el rol de WordPress del socio si no existe.
	 *
	 * Se llama al cargar el plugin. Idempotente: si ya está, no hace nada. Lleva `read` y poco más: el panel
	 * del socio se abre con la sesión, no por capacidad, y no se le da acceso al escritorio.
	 *
	 * @return void
	 */
	/**
	 * Capacidades derivadas de los tipos de contenido propios (issue #15).
	 *
	 * WordPress compone trece capacidades a partir del par singular/plural de `capability_type`. Se derivan aquí
	 * en vez de escribirlas a mano para que no falte ninguna: una sola que se quede fuera es una puerta abierta.
	 *
	 * Vive en la clase y no como función suelta por un motivo aprendido a golpes: este fichero declara espacio de
	 * nombres, así que una función declarada en el bootstrap no existe para nadie que la llame por su nombre sin
	 * cualificar — y el fallo es silencioso, porque `function_exists()` devuelve `false` sin avisar.
	 *
	 * @return array<int, string> Capacidades derivadas de socios, horas y proyectos.
	 */
	public static function capacidades_de_los_tipos(): array {
		$capacidades = array();
		foreach ( array( array( 'convoca_miembro', 'convoca_miembros' ), array( 'convoca_hora', 'convoca_horas' ), array( 'convoca_proyecto', 'convoca_proyectos' ) ) as $par ) {
			list( $singular, $plural ) = $par;
			$capacidades               = array_merge(
				$capacidades,
				array(
					"edit_$singular",
					"read_$singular",
					"delete_$singular",
					"edit_$plural",
					"edit_others_$plural",
					"delete_$plural",
					"publish_$plural",
					"read_private_$plural",
					"delete_private_$plural",
					"delete_published_$plural",
					"delete_others_$plural",
					"edit_private_$plural",
					"edit_published_$plural",
				)
			);
		}
		return $capacidades;
	}

	/**
	 * Crea (o asegura) los roles del plugin.
	 */
	public static function crear_roles(): void {
		if ( ! function_exists( 'add_role' ) || ! function_exists( 'get_role' ) ) {
			return;
		}

		if ( ! get_role( self::ROL_WP_SOCIO ) ) {
			add_role(
				self::ROL_WP_SOCIO,
				__( 'Socio', 'convoca-members' ),
				array( 'read' => true )
			);
		}

		// Las capacidades derivadas de los tipos propios (issue #15), SOLO a administracion: las pantallas
		// propias del plugin siguen gobernadas por `gestionar_miembros` y no cambian para nadie.
		//
		// Esto va aqui, en `init`, y NO en el gancho de activacion, por el mismo motivo que los roles: una
		// instalacion que se actualiza no pasa por la activacion. Medido: dejandolo en la activacion, el
		// administrador se quedaba fuera de `edit.php?post_type=miembro` (403), porque esas pantallas miraban
		// las capacidades derivadas y nadie se las habia concedido. Se comprueba antes de escribir.
		$administrador = get_role( 'administrator' );
		if ( $administrador ) {
			foreach ( self::capacidades_de_los_tipos() as $cap_propia ) {
				if ( ! $administrador->has_cap( $cap_propia ) ) {
					$administrador->add_cap( $cap_propia );
				}
			}
		}
	}

	/**
	 * ¿Es socio y voluntario a la vez?
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function tiene_doble_rol( int $miembro_id ): bool {
		return self::es_socio( $miembro_id ) && self::es_voluntario( $miembro_id );
	}

	/**
	 * Añade una condición sin tocar las demás.
	 *
	 * @param int    $miembro_id ID de la ficha.
	 * @param string $rol        Rol del ecosistema.
	 * @return bool `true` si la condición queda puesta.
	 */
	public static function anadir( int $miembro_id, string $rol ): bool {
		if ( $miembro_id <= 0 || '' === self::meta_de( $rol ) ) {
			return false;
		}

		update_post_meta( $miembro_id, self::meta_de( $rol ), '1' );
		self::sincronizar_roles_wp( $miembro_id );
		self::sincronizar_taxonomia( $miembro_id );

		return true;
	}

	/**
	 * Quita una condición sin tocar las demás.
	 *
	 * @param int    $miembro_id ID de la ficha.
	 * @param string $rol        Rol del ecosistema.
	 * @return bool `true` si la condición queda quitada.
	 */
	public static function quitar( int $miembro_id, string $rol ): bool {
		if ( $miembro_id <= 0 || '' === self::meta_de( $rol ) ) {
			return false;
		}

		// Se escribe '0' y no se borra la meta: así se distingue «nunca se le puso» (fichas antiguas, donde
		// el rol de WordPress es la única prueba) de «se le quitó», que es lo que hay que reflejar.
		update_post_meta( $miembro_id, self::meta_de( $rol ), '0' );
		self::sincronizar_roles_wp( $miembro_id );
		self::sincronizar_taxonomia( $miembro_id );

		return true;
	}

	/**
	 * Ficha de miembro vinculada a un usuario (por vínculo directo o por email).
	 *
	 * @param int $user_id Usuario de WordPress.
	 * @return int ID de la ficha, o 0.
	 */
	public static function ficha_de_usuario( int $user_id ): int {
		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return 0;
		}

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

		if ( empty( $fichas ) && isset( $user->user_email ) ) {
			$fichas = get_posts(
				array(
					'post_type'      => 'miembro',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_convoca_email',
					'meta_value'     => (string) $user->user_email,
				)
			);
		}

		return empty( $fichas ) ? 0 : (int) $fichas[0];
	}

	/**
	 * Refleja las condiciones de la ficha en el usuario de WordPress.
	 *
	 * Usa `add_role()` y `remove_role()`, que suman y restan sobre el resto de roles
	 * del usuario; nunca `set_role()`, que los sustituye todos. Si al quitar el
	 * voluntariado el usuario se queda sin ningún rol, se le deja el rol por defecto
	 * para que no pierda capacidades básicas.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return void
	 */
	public static function sincronizar_roles_wp( int $miembro_id ): void {
		$user_id = (int) get_post_meta( $miembro_id, '_convoca_user_id', true );
		if ( $user_id <= 0 ) {
			return;
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return;
		}

		$ecosistema = array();

		foreach ( self::roles_validos() as $rol ) {
			$wp = self::rol_wp_de( $rol );
			if ( '' === $wp ) {
				continue;
			}

			$ecosistema[] = $wp;
			$marca        = (string) get_post_meta( $miembro_id, self::meta_de( $rol ), true );
			$tiene        = in_array( $wp, (array) $user->roles, true );

			if ( '1' === $marca ) {
				if ( ! $tiene ) {
					// `add_role`, nunca `set_role`: las condiciones se SUMAN. Con `set_role` aprobar el
					// voluntariado le quitaba al socio su condición, que era el bloque de partida.
					$user->add_role( $wp );
				}
			} elseif ( '0' === $marca ) {
				if ( $tiene ) {
					$user->remove_role( $wp );
				}
			}
			// Sin marca (`''`) no se toca: son las fichas anteriores a este cambio, donde el rol de
			// WordPress es la única prueba que hay. Quitarlo ahí dejaría sin permisos —Enroll y Shifts
			// miran ese rol— a voluntariado que sí está aprobado.
		}

		// Si se queda sin ninguna condición del ecosistema, no se le deja sin rol.
		$revisado = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		if ( $revisado && array() === array_intersect( (array) $revisado->roles, $ecosistema ) && array() === (array) $revisado->roles ) {
			$revisado->add_role( self::ROL_WP_POR_DEFECTO );
		}
	}

	/**
	 * Refleja las condiciones en la taxonomía `tipo_miembro`.
	 *
	 * La taxonomía estaba registrada y vacía: el listado de socios la usaba para la
	 * columna «Tipo» y salía en blanco siempre. Si el sitio no tiene la función de
	 * términos disponible (tests, entornos mínimos), no se hace nada.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return void
	 */
	public static function sincronizar_taxonomia( int $miembro_id ): void {
		if ( ! function_exists( 'wp_set_object_terms' ) || ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			return;
		}
		if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( self::TAXONOMIA ) ) {
			return;
		}

		$ids = array();
		foreach ( self::roles( $miembro_id ) as $rol ) {
			$term = term_exists( $rol, self::TAXONOMIA );
			if ( ! $term ) {
				$term = wp_insert_term( $rol, self::TAXONOMIA );
			}
			if ( is_array( $term ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}

		wp_set_object_terms( $miembro_id, $ids, self::TAXONOMIA, false );
	}

	/**
	 * Deja constancia de cómo se ha cumplido el ciclo en curso.
	 *
	 * @param int    $miembro_id ID de la ficha.
	 * @param string $via        `pago` u `horas`.
	 * @return bool
	 */
	public static function marcar_cumplimiento( int $miembro_id, string $via ): bool {
		if ( $miembro_id <= 0 || ! in_array( $via, self::VIAS, true ) ) {
			return false;
		}

		update_post_meta( $miembro_id, self::META_CUMPLIMIENTO, $via );
		update_post_meta( $miembro_id, self::META_CUMPLIMIENTO_FECHA, current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Vía por la que se cumplió el ciclo en curso.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return string `pago`, `horas` o cadena vacía si el ciclo está pendiente.
	 */
	public static function cumplimiento( int $miembro_id ): string {
		if ( $miembro_id <= 0 ) {
			return '';
		}

		$via = (string) get_post_meta( $miembro_id, self::META_CUMPLIMIENTO, true );

		return in_array( $via, self::VIAS, true ) ? $via : '';
	}

	/**
	 * ¿Ha cumplido ya el ciclo (por la vía que sea)?
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function ciclo_cumplido( int $miembro_id ): bool {
		return '' !== self::cumplimiento( $miembro_id );
	}

	/**
	 * Abre un ciclo nuevo: las horas del ciclo anterior no cuentan para el siguiente.
	 *
	 * Se llama cuando la renovación avanza la fecha (pago) o cuando se reactiva una
	 * ficha, nunca antes: reiniciar el ciclo sin haberlo cerrado borraría el trabajo
	 * de quien está a medio camino.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return void
	 */
	public static function reiniciar_ciclo( int $miembro_id ): void {
		if ( $miembro_id <= 0 ) {
			return;
		}

		delete_post_meta( $miembro_id, self::META_CUMPLIMIENTO );
		delete_post_meta( $miembro_id, self::META_CUMPLIMIENTO_FECHA );
		delete_post_meta( $miembro_id, '_convoca_objetivo_horas_completado' );

		update_post_meta( $miembro_id, '_convoca_fecha_inicio_periodo', current_time( 'Y-m-d' ) );
	}

	/**
	 * Registra la condición de socio al completarse el pago de una cuota.
	 *
	 * Es el camino "voluntario que se hace socio abonando la cuota": se le añade la
	 * condición de socio y **conserva** la de voluntario. Si ya era socio, solo se
	 * marca el ciclo como cumplido por pago.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool `true` si la ficha queda como socia.
	 */
	public static function registrar_socio_por_pago( int $miembro_id ): bool {
		if ( $miembro_id <= 0 ) {
			return false;
		}

		self::anadir( $miembro_id, self::SOCIO );
		self::marcar_cumplimiento( $miembro_id, 'pago' );

		return self::es_socio( $miembro_id );
	}

	/**
	 * Registra el cumplimiento del ciclo por horas de voluntariado.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return bool
	 */
	public static function registrar_cumplimiento_por_horas( int $miembro_id ): bool {
		return self::marcar_cumplimiento( $miembro_id, 'horas' );
	}
}
