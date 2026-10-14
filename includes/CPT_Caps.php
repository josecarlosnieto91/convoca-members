<?php
/**
 * Convoca Members — capacidades de los tipos de contenido con datos personales.
 *
 * **Por qué existe** (auditoría del gauntlet, pasada 10):
 *
 * Los tipos `miembro`, `registro_hora` y `proyecto` se registraban con `show_ui => true` y, o bien
 * sin `capabilities` (heredando las de `post`), o bien con `capability_type => 'post'`. En WordPress,
 * `map_meta_cap` traduce entonces `edit_posts` / `delete_posts` a las capacidades primitivas del tipo
 * `post`, **que el rol `editor` tiene por defecto**. Consecuencia medida: un usuario con rol `editor`
 * —sin la capacidad del plugin— podía abrir `edit.php?post_type=miembro`, ver el listado completo de
 * socios y mandar fichas a la papelera. Las pantallas propias del plugin sí comprobaban
 * `gestionar_miembros`; las del tipo de contenido (que existen porque `show_ui` está en `true`), no.
 *
 * La capacidad `gestionar_miembros` la concede el plugin al rol de administración
 * (`convoca-members.php`), así que mapear los permisos ahí no quita acceso a nadie que hoy lo tenga:
 * quita el acceso a quien nunca debió tenerlo.
 *
 * Una sola fuente de verdad para los tres tipos: si mañana se cambia la capacidad, se cambia aquí y
 * los tres la siguen (el defecto original fue precisamente tener tres registros cada uno a su aire).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mapa de capacidades para los tipos de contenido que guardan datos personales.
 */
class CPT_Caps {

	/**
	 * Capacidad que gobierna los datos personales de socios y voluntariado.
	 *
	 * Se concede al rol de administración en `convoca-members.php`.
	 */
	public const CAPACIDAD = 'gestionar_miembros';

	/**
	 * Mapa de capacidades para `register_post_type()`.
	 *
	 * Cubre las primitivas y las meta capacidades: con `map_meta_cap => true`, WordPress resuelve
	 * `edit_post` / `delete_post` (que es lo que comprueban las propias pantallas del plugin) por
	 * estas entradas.
	 *
	 * @return array<string, string> Capacidades del tipo de contenido.
	 */
	public static function personales(): array {
		$cap = self::CAPACIDAD;

		return array(
			'edit_post'              => $cap,
			'read_post'              => $cap,
			'delete_post'            => $cap,
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'edit_published_posts'   => $cap,
			'edit_private_posts'     => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_posts'           => $cap,
			'delete_others_posts'    => $cap,
			'delete_published_posts' => $cap,
			'delete_private_posts'   => $cap,
			'create_posts'           => $cap,
		);
	}
}
