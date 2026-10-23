<?php

/**
 * Los cuatro bloques de Gutenberg de members.
 *
 * `Block_Members` estaba al 0 % (89 statements). Un bloque sin `render_callback` no da error: sale
 * vacío en el sitio. Y si el bloque del alta o del área de socios se registra sin su estilo, el
 * formulario aparece sin formato justo en la página donde alguien tiene que darse de alta.
 */

namespace Convoca\Members\Tests\Unit;

use Convoca\Members\Block_Members;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/Block_Members.php';

class BlockMembersTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_cm_bloques'] = [];
        $GLOBALS['_cm_estilos'] = [];
        $GLOBALS['_cm_scripts'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_cm_bloques'], $GLOBALS['_cm_estilos'], $GLOBALS['_cm_scripts']);
    }

    private function registrar(): array
    {
        (new Block_Members())->register_blocks();

        return $GLOBALS['_cm_bloques'];
    }

    public function test_se_registran_los_cuatro_bloques(): void
    {
        $bloques = $this->registrar();

        $this->assertSame(
            [
                'convoca-members/formulario-alta',
                'convoca-members/mi-area',
                'convoca-members/formulario-voluntariado',
                'convoca-members/verificar-certificado',
            ],
            array_keys($bloques)
        );
    }

    public function test_cada_bloque_tiene_su_render_callback(): void
    {
        foreach ($this->registrar() as $nombre => $args) {
            $this->assertArrayHasKey('render_callback', $args, "sin render_callback, {$nombre} sale vacío");
            $this->assertIsCallable($args['render_callback']);
            $this->assertSame('convoca-members', $args['category']);
            $this->assertSame(3, $args['apiVersion']);
        }
    }

    public function test_cada_bloque_apunta_a_su_propio_render(): void
    {
        $esperado = [
            'convoca-members/formulario-alta' => 'render_alta',
            'convoca-members/mi-area' => 'render_mi_area',
            'convoca-members/formulario-voluntariado' => 'render_voluntariado',
            'convoca-members/verificar-certificado' => 'render_verificar_certificado',
        ];

        $bloques = $this->registrar();

        foreach ($esperado as $bloque => $metodo) {
            $callback = $bloques[ $bloque ]['render_callback'];
            $this->assertInstanceOf(Block_Members::class, $callback[0]);
            $this->assertSame($metodo, $callback[1], "{$bloque} acaba en {$metodo}");
        }
    }

    public function test_el_area_de_socios_lleva_su_estilo_publico(): void
    {
        $bloques = $this->registrar();

        $this->assertSame('convoca-mi-area', $bloques['convoca-members/mi-area']['style'], 'la página privada necesita su estilo');
        $this->assertSame('convoca-mi-area', $bloques['convoca-members/mi-area']['editor_style'], 'y el editor verlo como queda');
        $this->assertSame('conv-members-public', $bloques['convoca-members/formulario-alta']['style']);
    }

    public function test_el_gancho_heredado_no_revienta(): void
    {
        (new Block_Members())->editor_assets();

        $this->assertTrue(true, 'es un no-op para ganchos antiguos: llamarlo no puede lanzar nada');
    }
}
