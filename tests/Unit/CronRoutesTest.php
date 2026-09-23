<?php
/**
 * Rutas de los correos de renovación y pago.
 *
 * Regresión: los avisos enlazaban a `/renovar/` y `/pagar/` escritas a mano y
 * ninguna de esas páginas la crea un plugin de Convoca. Si el sitio no las tenía
 * (Lugg no las tiene), el socio aterrizaba en un 404. Estas pruebas fijan que la
 * ruta se resuelve contra páginas reales y que nunca se emite una ruta muerta.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

// Los dobles tienen que ser GLOBALES: este fichero está en un namespace, así que
// declararlos aquí los dejaría como Convoca\Members\Tests\get_page_by_path.
require_once __DIR__ . '/../stubs-pages.php';

class CronRoutesTest extends TestCase
{
    private function cargar(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/Cron_Manager.php';
        if (file_exists($path) && !class_exists('\Convoca\Members\Cron_Manager')) {
            require_once $path;
        }
    }

    /**
     * Página de prueba. El WP_Post del bootstrap no tiene constructor: se rellena
     * propiedad a propiedad a propósito.
     */
    private function pagina(int $id, string $estado = 'publish'): object
    {
        $page = new \WP_Post();
        $page->ID = $id;
        $page->post_status = $estado;

        return $page;
    }

    protected function setUp(): void
    {
        $GLOBALS['convoca_test_pages'] = [];
        $this->cargar();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['convoca_test_pages']);
    }

    public function test_sin_paginas_no_emite_rutas_muertas()
    {
        $renovar = \Convoca\Members\Cron_Manager::renewal_page_url();
        $pagar = \Convoca\Members\Cron_Manager::payment_page_url();

        $this->assertStringNotContainsString('renovar', $renovar);
        $this->assertStringNotContainsString('pagar', $pagar);
        // Sin páginas, el destino es la portada (siempre existe).
        $this->assertSame(home_url('/'), $renovar);
        $this->assertSame(home_url('/'), $pagar);
    }

    public function test_usa_la_pagina_de_renovacion_cuando_existe()
    {
        $GLOBALS['convoca_test_pages']['renovar'] = $this->pagina(42);

        $this->assertSame('https://example.com/pagina/42/', \Convoca\Members\Cron_Manager::renewal_page_url());
    }

    public function test_cae_al_panel_del_socio_si_no_hay_pagina_de_renovacion()
    {
        $GLOBALS['convoca_test_pages']['mi-area'] = $this->pagina(7);

        $this->assertSame('https://example.com/pagina/7/', \Convoca\Members\Cron_Manager::renewal_page_url());
        $this->assertSame('https://example.com/pagina/7/', \Convoca\Members\Cron_Manager::payment_page_url());
    }

    public function test_una_pagina_no_publicada_no_cuenta()
    {
        $GLOBALS['convoca_test_pages']['renovar'] = $this->pagina(99, 'draft');

        $this->assertStringNotContainsString('/pagina/99/', \Convoca\Members\Cron_Manager::renewal_page_url());
    }

    public function test_el_codigo_no_vuelve_a_escribir_las_rutas_a_mano()
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/Cron_Manager.php');

        foreach (['renovar', 'pagar'] as $ruta) {
            $this->assertDoesNotMatchRegularExpression(
                "/return\s+home_url\(\s*'\/" . $ruta . "\/'\s*\)/",
                $src,
                "El enlace de /$ruta/ debe resolverse contra una página real, no escribirse a mano."
            );
        }
    }
}
