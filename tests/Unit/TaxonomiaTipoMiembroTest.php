<?php
/**
 * La taxonomia `tipo_miembro` tiene que ser gestionable desde el panel.
 *
 * Regresion que caza: se registraba con `public => false` y SIN `show_ui`. En una
 * taxonomia `show_ui` hereda de `public`, asi que quedaba sin interfaz, y WordPress 7
 * rechaza `edit-tags.php` de una taxonomia sin interfaz con un `wp_die` **sin estado**:
 * al navegador le llega un HTTP 500 (y no un 403) con «no tienes permisos», con el
 * administrador delante. Medido en la demo y en un sitio de cliente: daban 500 los dos.
 *
 * Se miran los argumentos del registro (los guarda el doble de convoca-core), porque el
 * defecto esta en COMO se declara, no en si se declara.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\CPT_Miembro;

class TaxonomiaTipoMiembroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_taxonomias_registradas'] = [];
        require_once dirname(__DIR__, 2) . '/includes/CPT_Miembro.php';
        CPT_Miembro::register();
    }

    private function args(): array
    {
        $registradas = $GLOBALS['_wp_taxonomias_registradas'] ?? [];
        $this->assertArrayHasKey('tipo_miembro', $registradas, 'no se registro la taxonomia tipo_miembro');
        return $registradas['tipo_miembro']['args'];
    }

    public function test_declara_show_ui(): void
    {
        $this->assertTrue(
            ! empty($this->args()['show_ui']),
            'sin show_ui, edit-tags.php de esta taxonomia responde HTTP 500 (y no 403) en WordPress 7'
        );
    }

    public function test_no_expone_urls_publicas(): void
    {
        $this->assertFalse(
            ! empty($this->args()['public']),
            'los tipos de miembro son datos internos: no deben tener URLs publicas'
        );
    }

    public function test_cuelga_del_tipo_de_contenido_miembro(): void
    {
        $registradas = $GLOBALS['_wp_taxonomias_registradas'] ?? [];
        $this->assertArrayHasKey('tipo_miembro', $registradas, 'no se registro la taxonomia tipo_miembro');
        $this->assertContains('miembro', (array) $registradas['tipo_miembro']['object_type']);
    }

    public function test_es_jerarquica_y_va_en_rest(): void
    {
        $args = $this->args();
        $this->assertTrue(! empty($args['hierarchical']));
        $this->assertTrue(! empty($args['show_in_rest']));
    }
}
