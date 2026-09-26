<?php
/**
 * Enlaces del panel que aporta cada sitio.
 *
 * El plugin no puede llevar a mano las páginas de un sitio concreto (turnos, perfil público…):
 * no existen en otro. Se pintan desde el filtro `convoca_mi_area_links`, y esto fija ese
 * contrato: sin filtro no aparece nada, con filtro aparecen los enlaces, y una entrada a la que
 * le falta la URL o el texto no se pinta (ni rompe el panel).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Mi_Area;
use PHPUnit\Framework\TestCase;

/**
 * Tests de los enlaces del panel de socio.
 */
class MiAreaLinksTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		remove_all_filters( 'convoca_mi_area_links' );
	}

	/**
	 * Pinta el panel y devuelve su HTML.
	 */
	private function panel(): string
	{
		$area   = new Mi_Area();
		$pintar = new \ReflectionMethod( Mi_Area::class, 'render_dashboard' );

		ob_start();
		$pintar->invoke( $area, 123 );
		return (string) ob_get_clean();
	}

	public function test_el_panel_se_pinta(): void
	{
		// Control: si esto falla, el resto de pruebas no significarían nada.
		$html = $this->panel();

		$this->assertStringContainsString( 'conv-panel-nav', $html );
		$this->assertStringContainsString( 'Mis Datos', $html );
	}

	public function test_sin_enlaces_del_sitio_no_se_pinta_la_lista(): void
	{
		$this->assertStringNotContainsString( 'conv-panel-links', $this->panel() );
	}

	public function test_el_sitio_puede_anadir_sus_propios_enlaces(): void
	{
		add_filter(
			'convoca_mi_area_links',
			static function ( array $enlaces ): array {
				$enlaces[] = array( 'url' => home_url( '/turnos/' ), 'label' => 'Mis turnos', 'icon' => '📅' );
				$enlaces[] = array( 'url' => home_url( '/mi-perfil/' ), 'label' => 'Mi perfil público' );
				return $enlaces;
			}
		);

		$html = $this->panel();

		$this->assertStringContainsString( 'conv-panel-links', $html );
		$this->assertStringContainsString( '/turnos/', $html );
		$this->assertStringContainsString( 'Mis turnos', $html );
		$this->assertStringContainsString( '/mi-perfil/', $html );
		$this->assertStringContainsString( 'Mi perfil público', $html );
	}

	public function test_una_entrada_incompleta_no_se_pinta_ni_rompe_el_panel(): void
	{
		add_filter(
			'convoca_mi_area_links',
			static function ( array $enlaces ): array {
				$enlaces[] = array( 'label' => 'Sin URL' );
				$enlaces[] = array( 'url' => home_url( '/sin-texto/' ) );
				$enlaces[] = 'esto no es un enlace';
				$enlaces[] = array( 'url' => home_url( '/bueno/' ), 'label' => 'Bueno' );
				return $enlaces;
			}
		);

		$html = $this->panel();

		$this->assertStringContainsString( '/bueno/', $html );
		$this->assertStringNotContainsString( 'Sin URL', $html );
		$this->assertStringNotContainsString( '/sin-texto/', $html );
		$this->assertStringContainsString( 'conv-panel-nav', $html );
	}
}
