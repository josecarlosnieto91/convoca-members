<?php
/**
 * El sitio de cliente no puede ver la marca del proveedor, y un documento sin configurar no puede
 * ser un enlace muerto.
 *
 * Tres defectos vistos con ojos de usuario nuevo (recorrido a ciegas, pasada 9 del gauntlet):
 *
 *  1. La tarjeta de socio decía «🍁 CONVOCA» y el consentimiento «Autorizo a Convoca a enviarme
 *     comunicaciones»: en la web de un sitio de cliente, quien se hace socia veía el nombre del
 *     software en vez del de la asociación.
 *     (Los nombres de entidad de este fichero son genéricos a propósito: el guardián de higiene
 *     prohíbe los datos de clientes reales en los repositorios, y esta prueba no necesita ninguno.)
 *  2. En el alta, «Código Ético» se pintaba con `apply_filters( 'convoca_members_alta_document_url',
 *     '' )`, cuyo respaldo es la cadena vacía: `<a href="">`, un enlace que no lleva a ninguna parte.
 *  3. Los documentos de la entidad estaban como tres URLs de Google Drive metidas a mano en las
 *     plantillas (issue convoca-members#12).
 *
 * Prueba en negativo: devolviendo la plantilla al `href=""` falla la comprobación de enlaces con
 * destino; quitando el respaldo por el nombre del sitio, falla la de la marca.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Identidad;
use PHPUnit\Framework\TestCase;

class IdentidadDelSitioTest extends TestCase {

	/**
	 * Devuelve el CODIGO de un fichero, sin comentarios.
	 *
	 * Sin esto la prueba se cae sola: el comentario que explica el defecto nombra justo lo que se
	 * busca. En PHP se quitan con `token_get_all`; en JS no vale —no hay `<?php`, así que el
	 * fichero entero vuelve como un único bloque de HTML en línea y los `//` se cuelan— y se
	 * quitan con expresión regular.
	 *
	 * @param string $rel Ruta relativa al repo.
	 * @return string
	 */
	private function fuente( string $rel ): string {
		$p = dirname( __DIR__, 2 ) . '/' . $rel;
		$this->assertFileExists( $p );
		$crudo = file_get_contents( $p );

		if ( str_ends_with( $rel, '.js' ) ) {
			$crudo = preg_replace( '#/\*.*?\*/#s', '', $crudo );
			return (string) preg_replace( '#^\s*//.*$#m', '', (string) $crudo );
		}

		$codigo = '';
		foreach ( token_get_all( $crudo ) as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$codigo .= is_array( $t ) ? $t[1] : $t;
		}
		return $codigo;
	}

	public function test_ninguna_plantilla_lleva_enlaces_a_documentos_hardcodeados(): void {
		foreach ( array( 'templates/form-alta.php', 'templates/form-voluntariado.php' ) as $f ) {
			$this->assertStringNotContainsString( 'drive.google', $this->fuente( $f ), "$f lleva un documento de la entidad metido a mano." );
		}
	}

	public function test_los_enlaces_de_documento_no_tienen_destino_vacio(): void {
		// OJO, y esto costó una vuelta de reloj: buscar el href vacío EN EL FUENTE no vale. El
		// href vacío no está en el código, sale en TIEMPO DE EJECUCIÓN porque el respaldo del
		// filtro viejo es la cadena vacía: el fuente pone el atributo con un echo por medio, así
		// que la búsqueda pasa con el defecto puesto. Y ojo con escribir la etiqueta de cierre de
		// PHP dentro de un comentario de línea: el tokenizador cierra el modo ahí y el fichero
		// entero se lee como HTML (el error que aparece después no señala a esa línea).
		// Se comprueba de dos formas que SÍ pueden fallar:
		//   1. que las plantillas ya no llamen al filtro del respaldo vacío;
		//   2. que lo que devuelve el ayudante, con el ajuste vacío, no sea un `<a>`.
		foreach ( array( 'templates/form-alta.php', 'templates/form-voluntariado.php' ) as $f ) {
			$this->assertStringNotContainsString(
				'convoca_members_alta_document_url',
				$this->fuente( $f ),
				"$f sigue usando el filtro cuyo respaldo es la cadena vacía: eso pinta un enlace sin destino."
			);
		}

		delete_option( 'convoca_members_settings' );
		$opciones = array( Identidad::OPCION_DOC_ETICO, Identidad::OPCION_DOC_VOLUNTARIADO, Identidad::OPCION_DOC_PROTOCOLO );
		foreach ( $opciones as $opcion ) {
			$html = Identidad::enlace_documento( 'Documento', $opcion );
			$this->assertStringNotContainsString( '<a ', $html, "El documento sin configurar ({$opcion}) sale como enlace." );
			$this->assertStringNotContainsString( 'href', $html, "El documento sin configurar ({$opcion}) lleva href." );
		}
	}

	public function test_un_documento_sin_configurar_se_muestra_sin_enlace(): void {
		// Con el ajuste vacio no hay `<a>`: se ve el nombre y ya esta.
		$this->assertSame( 'Código Ético', Identidad::enlace_documento( 'Código Ético', Identidad::OPCION_DOC_ETICO ) );
	}

	public function test_un_documento_configurado_se_muestra_como_enlace_escaped(): void {
		update_option( 'convoca_members_settings', array( Identidad::OPCION_DOC_ETICO => 'https://ejemplo.test/etico.pdf' ) );
		$html = Identidad::enlace_documento( 'Código Ético', Identidad::OPCION_DOC_ETICO );
		$this->assertStringContainsString( 'href="https://ejemplo.test/etico.pdf"', $html );
		delete_option( 'convoca_members_settings' );
	}

	public function test_el_nombre_de_la_entidad_cae_al_del_sitio(): void {
		delete_option( 'convoca_members_settings' );
		$this->assertSame( get_bloginfo( 'name' ), Identidad::nombre_entidad() );

		update_option( 'convoca_members_settings', array( Identidad::OPCION_ENTIDAD => 'Asociación de Prueba' ) );
		$this->assertSame( 'Asociación de Prueba', Identidad::nombre_entidad() );
		delete_option( 'convoca_members_settings' );
	}

	public function test_ninguna_plantilla_nombra_al_proveedor(): void {
		foreach ( array( 'templates/form-alta.php', 'templates/form-voluntariado.php' ) as $f ) {
			$this->assertStringNotContainsString( 'Autorizo a Convoca', $this->fuente( $f ), "$f sigue nombrando al proveedor en el consentimiento." );
			$this->assertDoesNotMatchRegularExpression( '/<h3>[^<]*CONVOCA/', $this->fuente( $f ), "$f sigue pintando la marca del proveedor en la tarjeta." );
		}
	}

	public function test_la_casilla_del_rgpd_da_senal_cuando_bloquea(): void {
		$js = $this->fuente( 'assets/js/convoca-members-public.js' );
		$this->assertStringContainsString( "aria-invalid", $js, 'La casilla del RGPD no se marca cuando bloquea el avance.' );
		$this->assertMatchesRegularExpression( '/rgpd\.focus\(\)/', $js, 'El foco no se lleva al campo que bloquea.' );
		$this->assertStringNotContainsString( 'Socio/a #TEMP', $js, 'La tarjeta sigue inventandose un numero de socio.' );
	}
}
