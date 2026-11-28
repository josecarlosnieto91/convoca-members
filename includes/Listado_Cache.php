<?php
/**
 * La versión de la caché del listado de socios.
 *
 * Vive aparte de `Admin_List` a propósito: esa clase está en el límite de métodos que vigila PHPMD,
 * y el listado cachea la página cinco minutos. Subir la versión invalida todas las claves de golpe
 * sin recorrer transients, y hay que hacerlo en cada cambio que altere qué fichas existen.
 *
 * @package ConvocaMembers
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versión de la caché del listado.
 */
final class Listado_Cache {

	/**
	 * Nombre de la opción que guarda la versión.
	 *
	 * @var string
	 */
	public const OPCION = 'convoca_members_list_version';

	/**
	 * La versión actual, para meterla en la clave del transient.
	 *
	 * @return string
	 */
	public static function version(): string {
		return (string) get_option( self::OPCION, '1' );
	}

	/**
	 * Engancha la invalidación a los cambios de ficha.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'save_post_miembro', array( __CLASS__, 'invalidar' ) );
		add_action( 'deleted_post', array( __CLASS__, 'invalidar_si_miembro' ) );
	}

	/**
	 * Invalida solo si el borrado era una ficha de socio.
	 *
	 * @param int $post_id Ficha.
	 * @return void
	 */
	public static function invalidar_si_miembro( int $post_id ): void {
		if ( 'miembro' === get_post_type( $post_id ) ) {
			self::invalidar();
		}
	}

	/**
	 * Tira la caché del listado subiendo su versión.
	 *
	 * @return void
	 */
	public static function invalidar(): void {
		update_option( self::OPCION, (string) ( time() . wp_rand( 100, 999 ) ), false );
	}
}
