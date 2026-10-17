<?php
/**
 * Contrato del acuerdo de voluntariado (members#58).
 *
 * La generación del acuerdo busca antes si ya existe uno del socio. Esa búsqueda filtraba sólo por
 * `_convoca_usuario_id`, así que devolvía **cualquier** documento del usuario —el primero que
 * apareciera— y el acuerdo no se generaba nunca: la ficha acabó apuntando a un DNI caducado de otra
 * prueba. Esta prueba vigila que las dos consultas (la previa y la que va con bloqueo) exijan la marca
 * de tipo, y que al crear el documento se ponga.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class AcuerdoDeVoluntariadoTest extends TestCase {

	private function fuente(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/PDF_Document.php' );
	}

	public function test_las_consultas_de_ya_existe_exigen_la_marca_de_acuerdo(): void {
		$fuente = $this->fuente();
		$cuenta = substr_count( $fuente, "_convoca_es_acuerdo_voluntariado' AND pm_acuerdo.meta_value = '1'" );
		$this->assertSame( 2, $cuenta, 'Las dos consultas (previa y con bloqueo) deben filtrar por la marca de acuerdo.' );
	}

	public function test_al_crear_el_documento_se_marca_como_acuerdo(): void {
		$fuente = $this->fuente();
		$this->assertStringContainsString( 'update_post_meta( $temp_post_id, \'_convoca_es_acuerdo_voluntariado\', \'1\' );', $fuente );
	}

	public function test_no_basta_con_el_usuario_para_decidir_que_ya_existe(): void {
		$fuente = $this->fuente();
		// Cada consulta del acuerdo tiene que llevar SU join con la marca: si vuelve a faltar, es el defecto.
		$this->assertSame( 2, substr_count( $fuente, 'INNER JOIN {$wpdb->postmeta} pm_acuerdo' ) );
	}
}
