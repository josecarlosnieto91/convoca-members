<?php
/**
 * Los contadores de la cabecera y la tabla tienen que mirar los mismos estados.
 *
 * El defecto que esto vigila: la tabla lista `publish, pending, draft, private, future` y los
 * contadores filtraban solo `publish`, así que una ficha fuera de `publish` salía en la lista y no
 * sumaba en ningún marcador — Total 0 con una fila delante.
 *
 * @package ConvocaMembers
 */

namespace Convoca\Members\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guardián de la coherencia entre tabla y contadores.
 */
final class ContadoresIgualQueLaTablaTest extends TestCase {

	/**
	 * La página no puede volver a filtrar `publish` a mano.
	 */
	public function test_los_contadores_usan_la_lista_compartida(): void {
		$fuente = (string) file_get_contents( __DIR__ . '/../../admin/class-admin-page.php' );

		$this->assertStringContainsString(
			'Admin_List::ESTADOS_LISTADOS',
			$fuente,
			'Los contadores deben tomar los estados de Admin_List::ESTADOS_LISTADOS, no escribirlos a mano.'
		);

		$this->assertStringNotContainsString(
			"post_type = 'miembro' AND p.post_status = 'publish'",
			$fuente,
			'Queda un contador filtrando solo publish: volvería el Total a cero con filas en la tabla.'
		);
	}

	/**
	 * Y la tabla usa la misma constante.
	 */
	public function test_la_tabla_usa_la_misma_lista(): void {
		$fuente = (string) file_get_contents( __DIR__ . '/../../admin/class-admin-list.php' );

		$this->assertStringContainsString( 'public const ESTADOS_LISTADOS', $fuente );
	}
}
