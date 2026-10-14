<?php
/**
 * Los tipos de contenido con datos personales NO los gobiernan las capacidades de WordPress.
 *
 * **Defecto que sujeta esta prueba** (auditoría del gauntlet, pasada 10):
 *
 * `miembro`, `registro_hora` y `proyecto` se registraban sin mapa de capacidades —o con
 * `capability_type => 'post'`—, así que WordPress los gobernaba con `edit_posts` / `delete_posts`,
 * que **el rol `editor` tiene por defecto**. Medido en el sitio: un `editor` abría
 * `edit.php?post_type=miembro` (HTTP 200), veía el listado completo de socios y podía mandarlos a la
 * papelera. Las pantallas propias del plugin sí comprobaban `gestionar_miembros`; las del tipo de
 * contenido, no.
 *
 * **Prueba en negativo**: quitando el mapa de una de las tres inscripciones, la comprobación de
 * cobertura falla y nombra el fichero; cambiando la capacidad por `edit_posts`, falla la comprobación
 * de que no es una capacidad de WordPress.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CPT_Caps;
use PHPUnit\Framework\TestCase;

class CapacidadesDeDatosPersonalesTest extends TestCase {

	/**
	 * Devuelve el CÓDIGO de un fichero, sin comentarios.
	 *
	 * Los comentarios de estos ficheros explican el defecto y nombran lo que se busca; sin quitarlos,
	 * la prueba fallaría contra su propia explicación.
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

	public function test_la_capacidad_que_gobierna_los_datos_personales_no_es_una_de_wordpress(): void {
		$de_wordpress = array(
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_published_posts',
			'edit_post',
			'read_post',
			'delete_post',
		);

		$this->assertNotContains(
			CPT_Caps::CAPACIDAD,
			$de_wordpress,
			'La capacidad que gobierna los datos de socios y voluntariado es una capacidad que WordPress reparte por defecto: cualquier rol con permiso de edición entraría.'
		);
	}

	public function test_el_mapa_cubre_todas_las_capacidades_del_tipo_de_contenido(): void {
		$mapa = CPT_Caps::personales();

		$esperadas = array(
			'edit_post',
			'read_post',
			'delete_post',
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'edit_private_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_published_posts',
			'delete_private_posts',
			'create_posts',
		);

		foreach ( $esperadas as $cap ) {
			$this->assertArrayHasKey( $cap, $mapa, "El mapa no cubre '{$cap}': WordPress usaría la capacidad por defecto para ese punto." );
			$this->assertSame(
				CPT_Caps::CAPACIDAD,
				$mapa[ $cap ],
				"'{$cap}' apunta a una capacidad distinta de " . CPT_Caps::CAPACIDAD . '.'
			);
		}
	}

	public function test_los_tres_tipos_con_datos_personales_usan_el_mapa(): void {
		$ficheros = array(
			'includes/CPT_Miembro.php'       => 'las fichas de socios',
			'includes/CPT_Registro_Hora.php' => 'las horas de voluntariado',
			'includes/CPT_Proyecto.php'      => 'los proyectos de voluntariado',
		);

		foreach ( $ficheros as $f => $que ) {
			$codigo = $this->fuente( $f );

			$this->assertStringContainsString(
				'CPT_Caps::personales()',
				$codigo,
				"{$f} registra el tipo de contenido sin el mapa de capacidades: {$que} quedarían gobernadas por las capacidades de WordPress."
			);

			$this->assertStringNotContainsString(
				"'capability_type'",
				$codigo,
				"{$f} sigue declarando 'capability_type' además del mapa: es la vía por la que este defecto entró, y deja dos varas para el mismo permiso."
			);
		}
	}

	public function test_la_capacidad_es_la_misma_que_comprueban_las_pantallas_del_plugin(): void {
		// Si la capacidad del tipo de contenido y la que comprueban las pantallas propias dejan de
		// coincidir, un rol podría ver el listado por una vía y recibir un 403 por la otra.
		$admin = $this->fuente( 'admin/class-admin-page.php' );

		$this->assertStringContainsString(
			'gestionar_miembros',
			$admin,
			'Las pantallas del plugin ya no comprueban la capacidad que gobierna el tipo de contenido.'
		);
		$this->assertSame( 'gestionar_miembros', CPT_Caps::CAPACIDAD );
	}
}
