<?php
/**
 * Guarda: ninguna capacidad propia puede ocupar a la vez el hueco de capacidad primitiva y el de
 * capacidad meta de un tipo de contenido.
 *
 * **Defecto que sujeta esta prueba** (pasada 10 del gauntlet, intento de arreglo revertido):
 *
 * Para que un `editor` no pudiera listar ni borrar fichas de socios se mapearon todas las capacidades
 * del tipo de contenido —incluidas las meta `edit_post`, `read_post` y `delete_post`— a la capacidad
 * propia `gestionar_miembros`. Efecto medido en el sitio, **no previsto**:
 *
 *     map_meta_cap( 'gestionar_miembros', 2 )  ->  do_not_allow
 *     map_meta_cap( 'manage_options', 2 )      ->  manage_options
 *     map_meta_cap( 'edit_posts', 2 )          ->  edit_posts
 *
 * Es decir: WordPress, al ver que esa cadena es la capacidad meta `edit_post` de un tipo de
 * contenido, intenta resolverla contra una entrada concreta; al comprobarla sin entrada (que es como
 * la comprueban las pantallas propias del plugin) devuelve `do_not_allow`. Consecuencia: la capacidad
 * dejaba de valer para **todo el mundo, administrador incluido**, y el gobierno de socios quedaba
 * inaccesible. Se revirtió y el defecto original (un `editor` puede tocar fichas de socios) queda
 * abierto en su issue, con esta medición como punto de partida.
 *
 * La prueba no dice «no toques las capacidades»: dice **no uses una capacidad propia en el hueco de
 * las meta de WordPress**, que es lo que rompe el mecanismo.
 *
 * **Prueba en negativo**: devolviendo `'edit_post' => 'gestionar_miembros'` al mapa de cualquiera de
 * los tres ficheros, la prueba falla y lo nombra.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CapacidadesSinColisionTest extends TestCase {

	/** Capacidades meta del núcleo: en su hueco no va una capacidad propia. */
	private const META_DEL_NUCLEO = array( 'edit_post', 'read_post', 'delete_post' );

	/**
	 * Código de un fichero, sin comentarios (los de estos ficheros nombran justo lo que se busca).
	 *
	 * @param string $rel Ruta relativa al repo.
	 * @return string
	 */
	private function fuente( string $rel ): string {
		$p = dirname( __DIR__, 2 ) . '/' . $rel;
		$this->assertFileExists( $p );

		$codigo = '';
		foreach ( token_get_all( (string) file_get_contents( $p ) ) as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$codigo .= is_array( $t ) ? $t[1] : $t;
		}
		return $codigo;
	}

	public function test_ninguna_capacidad_propia_ocupa_el_hueco_de_una_meta_del_nucleo(): void {
		$ficheros = array(
			'includes/CPT_Miembro.php',
			'includes/CPT_Registro_Hora.php',
			'includes/CPT_Proyecto.php',
			'includes/CPT_Documento.php',
		);

		foreach ( $ficheros as $f ) {
			$codigo = $this->fuente( $f );

			foreach ( self::META_DEL_NUCLEO as $meta ) {
				$this->assertDoesNotMatchRegularExpression(
					"/'" . $meta . "'\s*=>/",
					$codigo,
					"{$f} asigna la capacidad meta '{$meta}' del núcleo a una capacidad propia: WordPress "
					. 'la intentará resolver contra una entrada y, al comprobarla sin entrada, devolverá '
					. 'do_not_allow para todo el mundo (medido en la pasada 10).'
				);
			}
		}
	}

	public function test_la_capacidad_del_plugin_sigue_siendo_la_misma_que_comprueban_sus_pantallas(): void {
		// Si el sitio donde se concede la capacidad y el que la comprueba dejan de coincidir, una de
		// las dos partes se queda sin acceso sin que nada avise.
		$principal = $this->fuente( 'convoca-members.php' );
		$pantallas = $this->fuente( 'admin/class-admin-page.php' );

		$this->assertStringContainsString( 'gestionar_miembros', $principal, 'El plugin ya no concede gestionar_miembros.' );
		$this->assertStringContainsString( 'gestionar_miembros', $pantallas, 'Las pantallas ya no comprueban gestionar_miembros.' );
	}
}
