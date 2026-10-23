<?php

/**
 * El diagnóstico del plugin: las cuatro comprobaciones del panel de estado.
 *
 * `Admin_Status` estaba al 0 % (104 statements). Son comprobaciones que alguien mira cuando algo no
 * funciona —la versión de PHP, la página «Mi Área», las tablas de la base de datos y el cron— y que
 * tienen que distinguir bien entre «todo bien», «esto hay que mirarlo» y «esto está roto»: si todo
 * sale en verde, el panel no sirve para nada.
 */

namespace Convoca\Members\Tests\Unit;

use Convoca\Members\Admin_Status;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/Admin_Status.php';

class AdminStatusTest extends TestCase
{
    protected function setUp(): void
    {
        // Los dobles viven en el bootstrap del núcleo: páginas en convoca_test_pages y cron en
        // _wp_cron. Para las tablas, el doble de $wpdb responde a SHOW TABLES desde una lista.
        $GLOBALS['convoca_test_pages'] = [];
        $GLOBALS['_wp_cron'] = ['programados' => [], 'consultados' => []];
        $GLOBALS['_wp_tablas'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['convoca_test_pages'], $GLOBALS['_wp_cron'], $GLOBALS['_wp_tablas']);
    }

    public function test_la_version_de_php_se_comprueba(): void
    {
        $r = Admin_Status::check_php_version();

        $this->assertSame('php', $r['id']);
        $this->assertSame(Admin_Status::SEVERITY_OK, $r['severity'], 'con PHP 8 o superior está bien');
        $this->assertStringContainsString(PHP_VERSION, $r['message']);
    }

    public function test_la_pagina_mi_area_sin_crear_es_un_error(): void
    {
        $GLOBALS['convoca_test_pages'] = []; // no existe

        $r = Admin_Status::check_pages();

        $this->assertSame('page_mi_area', $r['id']);
        $this->assertSame(Admin_Status::SEVERITY_ERROR, $r['severity'], 'sin la página, el socio no puede entrar');
        $this->assertNotEmpty($r['fix'], 'y hay que decir cómo arreglarlo');
    }

    public function test_la_pagina_sin_el_shortcode_avisa(): void
    {
        $GLOBALS['convoca_test_pages']['mi-area'] = (object) [
            'post_status' => 'publish',
            'post_content' => 'Texto sin el shortcode',
        ];

        $r = Admin_Status::check_pages();

        $this->assertSame(Admin_Status::SEVERITY_WARNING, $r['severity'], 'existe pero no hace nada: aviso, no error');
        $this->assertStringContainsString('sin el shortcode', $r['message']);
    }

    public function test_la_pagina_bien_puesta_esta_en_verde(): void
    {
        $GLOBALS['convoca_test_pages']['mi-area'] = (object) [
            'post_status' => 'publish',
            'post_content' => 'Contenido [convoca_mi_area] aquí',
        ];

        $r = Admin_Status::check_pages();

        $this->assertSame(Admin_Status::SEVERITY_OK, $r['severity']);
    }

    public function test_una_pagina_sin_publicar_no_cuenta_como_buena(): void
    {
        $GLOBALS['convoca_test_pages']['mi-area'] = (object) [
            'post_status' => 'draft',
            'post_content' => '[convoca_mi_area]',
        ];

        $r = Admin_Status::check_pages();

        $this->assertNotSame(Admin_Status::SEVERITY_OK, $r['severity'], 'un borrador no lo ve nadie');
    }

    /*
     * El caso «falta una tabla» NO se prueba: el doble de $wpdb del núcleo responde a
     * `SHOW TABLES LIKE` devolviendo siempre el nombre, porque de eso depende el Logger (si cree que
     * no hay tabla, no escribe y el bloqueo cae al camino de opciones). Cambiarlo afectaría a todos
     * los plugins que comparten ese bootstrap. Queda como excepción escrita.
     */

    public function test_con_las_tablas_puestas_esta_en_verde(): void
    {
        $GLOBALS['_wp_tablas'] = ['wp_convoca_logs', 'wp_convoca_locks', 'wp_convoca_member_sequence'];

        $r = Admin_Status::check_tables();

        $this->assertSame(Admin_Status::SEVERITY_OK, $r['severity']);
    }

    public function test_el_cron_sin_programar_avisa(): void
    {
        $GLOBALS['_wp_cron']['programados'] = []; // nada programado

        $r = Admin_Status::check_cron();

        $this->assertSame('cron', $r['id']);
        $this->assertNotSame(Admin_Status::SEVERITY_OK, $r['severity'], 'sin cron no se envían recordatorios ni renovaciones');
        // Y tiene que decir CUÁLES faltan: «faltan eventos» no sirve para arreglarlo (lo destapó la
        // verificación en negativo, que cambió el mensaje y la prueba siguió verde).
        $this->assertStringContainsString('convoca_daily_event', $r['message']);
        $this->assertStringContainsString('convoca_weekly_event', $r['message']);
    }

    public function test_el_cron_programado_esta_en_verde(): void
    {
        $GLOBALS['_wp_cron']['programados'] = ['convoca_daily_event' => 1700000000, 'convoca_weekly_event' => 1700000000];

        $r = Admin_Status::check_cron();

        $this->assertSame(Admin_Status::SEVERITY_OK, $r['severity']);
    }

    public function test_el_parte_devuelve_las_cuatro_comprobaciones(): void
    {
        $GLOBALS['_wp_tablas'] = ['wp_convoca_logs', 'wp_convoca_locks', 'wp_convoca_member_sequence'];
        $GLOBALS['_wp_cron']['programados'] = ['convoca_daily_event' => 1700000000, 'convoca_weekly_event' => 1700000000];
        $GLOBALS['convoca_test_pages']['mi-area'] = (object) ['post_status' => 'publish', 'post_content' => '[convoca_mi_area]'];

        $todo = Admin_Status::run_all();

        $this->assertCount(4, $todo);
        $this->assertSame(
            ['php', 'page_mi_area', 'tables', 'cron'],
            array_column($todo, 'id'),
            'cada comprobación tiene su identificador'
        );

        foreach ($todo as $r) {
            $this->assertArrayHasKey('severity', $r);
            $this->assertArrayHasKey('message', $r);
            $this->assertArrayHasKey('label', $r);
        }
    }
}
