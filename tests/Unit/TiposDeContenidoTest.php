<?php

/**
 * Los tipos de contenido de proyectos y de registro de horas.
 *
 * Los dos estaban al 0 % (137 y 51 statements). Lo que se prueba aquí es lo que costó un incidente
 * en otro plugin del ecosistema: que un tipo de contenido **nunca** se registre con capacidades
 * globales. Si `capability_type` no es propio, WordPress mete `read_post`/`edit_post` en
 * `$post_type_meta_caps` y el panel entero responde 403 a todo el mundo, administrador incluido.
 */

namespace Convoca\Members\Tests\Unit;

use Convoca\Members\CPT_Proyecto;
use Convoca\Members\CPT_Registro_Hora;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/CPT_Proyecto.php';
require_once dirname(__DIR__, 2) . '/includes/CPT_Registro_Hora.php';

class TiposDeContenidoTest extends TestCase
{
    protected function setUp(): void
    {
        // El doble de register_post_type vive en el bootstrap del núcleo y guarda los argumentos
        // aquí: es lo que permite afirmar con qué capacidades se registra un tipo.
        $GLOBALS['_wp_post_types_registrados'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_wp_post_types_registrados']);
    }

    // ── proyectos ────────────────────────────────────────────────────────────────────────

    public function test_el_tipo_de_proyecto_se_registra(): void
    {
        CPT_Proyecto::register();

        $args = $GLOBALS['_wp_post_types_registrados']['proyecto'] ?? null;
        $this->assertNotNull($args, 'sin registrar el tipo no hay proyectos');
        $this->assertFalse($args['public'], 'los proyectos no se publican en el sitio');
        $this->assertFalse($args['show_in_rest'], 'no tienen por qué salir en la API');
        $this->assertContains('title', $args['supports']);
    }

    public function test_el_proyecto_usa_capacidades_propias_y_no_las_globales(): void
    {
        CPT_Proyecto::register();

        $tipo = $GLOBALS['_wp_post_types_registrados']['proyecto']['capability_type'];

        $this->assertIsArray($tipo, 'un tipo de contenido necesita su propio nombre de capacidad');
        $this->assertSame(['convoca_proyecto', 'convoca_proyectos'], $tipo);
        $this->assertNotSame('post', $tipo, 'con «post» el panel se cierra a todo el mundo');
        $this->assertNotContains('read', (array) $tipo, '«read» es global y contamina $post_type_meta_caps');
    }

    public function test_la_meta_del_proyecto_se_lee_completa(): void
    {
        $meta = CPT_Proyecto::get_meta(1);

        $this->assertSame(CPT_Proyecto::META_KEYS, array_keys($meta), 'la meta se lee con todas sus claves');
    }

    // ── registro de horas ───────────────────────────────────────────────────────────────

    public function test_el_tipo_de_horas_se_registra(): void
    {
        CPT_Registro_Hora::register();

        $args = $GLOBALS['_wp_post_types_registrados']['registro_hora'] ?? null;
        $this->assertNotNull($args);
        $this->assertFalse($args['public'], 'las horas trabajadas no se publican');
    }

    public function test_el_registro_de_horas_tambien_usa_capacidades_propias(): void
    {
        CPT_Registro_Hora::register();

        $tipo = $GLOBALS['_wp_post_types_registrados']['registro_hora']['capability_type'];

        $this->assertSame(['convoca_hora', 'convoca_horas'], $tipo);
        $this->assertNotSame('post', $tipo);
    }

    // CPT_Registro_Hora no expone get_meta(): su meta se lee por la del miembro.
}
