<?php

/**
 * El carnet digital va en el plan gratuito (issue core#17).
 *
 * La web lo anuncia entre los ganchos del plan gratis («Carnet digital») y el código lo servía detrás de
 * `pdf_memories`, que es la clave de las memorias PDF: quien instalaba la versión gratuita no veía el
 * carnet, justo lo que se le promete. Aquí se fija que se sirve sin licencia y que las memorias PDF
 * —esas sí PRO— siguen con su puerta.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CarnetSinLicenciaTest extends TestCase {

	private function codigo(): string {
		$ruta = dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';
		$this->assertFileExists( $ruta );

		return file_get_contents( $ruta );
	}

	public function test_el_carnet_se_sirve_sin_licencia(): void {
		$codigo = $this->codigo();

		$this->assertStringContainsString( 'action=convoca_pdf_card', $codigo, 'Falta el enlace del carnet' );
		$this->assertStringNotContainsString(
			'Ver Tarjeta (PRO)',
			$codigo,
			'El carnet ha vuelto a quedar detrás de la licencia'
		);
	}

	public function test_las_memorias_pdf_siguen_siendo_pro(): void {
		$this->assertStringContainsString(
			"has_pro( 'pdf_memories' )",
			$this->codigo(),
			'La exportación de informes perdió su puerta de licencia'
		);
	}
}
