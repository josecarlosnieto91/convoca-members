<?php
/**
 * Convoca Members — identidad del sitio y documentos de la entidad.
 *
 * Por qué existe (hallazgos del recorrido a ciegas con usuario nuevo, pasada 9 del gauntlet):
 *
 *  1. **La marca del proveedor aparecía dentro de la web del cliente.** La tarjeta de socio decía
 *     «🍁 CONVOCA» y el consentimiento «Autorizo a Convoca a enviarme comunicaciones»: en la web de
 *     Los Lugg, quien se hace socia ve el nombre del software en vez del de la asociación.
 *  2. **Dos enlaces con `href=""`**: «Código Ético» en el alta y en el voluntariado se pintaban con
 *     `apply_filters( 'convoca_members_alta_document_url', '' )`, cuyo respaldo es la cadena vacía —
 *     es decir, un enlace que no lleva a ninguna parte, en HTML, y que el usuario pulsa.
 *  3. **Un enlace a un documento de Google Drive hardcodeado** en la plantilla (issue
 *     convoca-members#12): dónde apuntan los documentos de una entidad es decisión suya, no del
 *     plugin.
 *
 * Los tres se resuelven igual: un ajuste en el panel con respaldo sensato. Y si el ajuste está
 * vacío, **no se pinta el enlace** (se pinta el nombre, sin `<a>`): nunca un enlace muerto.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identidad y documentos del sitio.
 */
class Identidad {

	/** Ajuste con el nombre de la entidad. Vacío = nombre del sitio de WordPress. */
	const OPCION_ENTIDAD = 'entity_name';

	/** Ajuste con la URL del Código Ético. */
	const OPCION_DOC_ETICO = 'doc_codigo_etico';

	/** Ajuste con la URL del Programa de Voluntariado. */
	const OPCION_DOC_VOLUNTARIADO = 'doc_voluntariado';

	/** Ajuste con la URL del Protocolo de prevención del acoso. */
	const OPCION_DOC_PROTOCOLO = 'doc_protocolo';

	/**
	 * Nombre de la asociación tal y como debe verlo quien rellena el formulario.
	 *
	 * @return string
	 */
	public static function nombre_entidad(): string {
		$ajustes = get_option( 'convoca_members_settings', array() );
		$nombre  = is_array( $ajustes ) ? (string) ( $ajustes[ self::OPCION_ENTIDAD ] ?? '' ) : '';
		if ( '' === trim( $nombre ) ) {
			$nombre = (string) get_bloginfo( 'name' );
		}
		/**
		 * Filtra el nombre de la entidad que ven los socios.
		 *
		 * @param string $nombre Nombre resuelto.
		 */
		return (string) apply_filters( 'convoca_members_nombre_entidad', $nombre );
	}

	/**
	 * URL de un documento de la entidad, o cadena vacía si no está configurado.
	 *
	 * @param string $opcion Clave del ajuste (OPCION_DOC_*).
	 * @return string
	 */
	public static function documento( string $opcion ): string {
		$ajustes = get_option( 'convoca_members_settings', array() );
		$url     = is_array( $ajustes ) ? (string) ( $ajustes[ $opcion ] ?? '' ) : '';

		/**
		 * Filtra la URL de un documento de la entidad (compatibilidad con los filtros que ya
		 * usaban las plantillas).
		 *
		 * @param string $url    URL configurada.
		 * @param string $opcion Clave del ajuste.
		 */
		$url = (string) apply_filters( 'convoca_members_documento_url', $url, $opcion );

		if ( '' === trim( $url ) ) {
			return '';
		}
		return (string) apply_filters( "convoca_members_documento_url_{$opcion}", $url );
	}

	/**
	 * Pinta el nombre de un documento: enlace si hay URL, texto llano si no.
	 *
	 * Nunca devuelve un `<a href="">`: un enlace que no lleva a ninguna parte es peor que no
	 * tener enlace, porque el usuario lo pulsa y cree que la web está rota.
	 *
	 * @param string $texto  Texto visible.
	 * @param string $opcion Clave del ajuste con la URL.
	 * @return string HTML escapado.
	 */
	public static function enlace_documento( string $texto, string $opcion ): string {
		$url = self::documento( $opcion );
		if ( '' === $url ) {
			return esc_html( $texto );
		}
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $url ),
			esc_html( $texto )
		);
	}
}
