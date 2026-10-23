<?php

/**
 * Las migraciones de datos de members.
 *
 * `Members_Upgrade_Manager` estaba al 0 % (67 statements). Lo que se prueba es la migración que toca
 * datos que la asociación ya tiene escritos: la plantilla del correo de «solicitud recibida», a la
 * que hay que añadirle los datos de la transferencia **sin pisar** lo que hayan personalizado. Una
 * migración que sobreescribe una plantilla es un cambio que nadie pidió y que se descubre tarde.
 */

namespace Convoca\Members\Tests\Unit;

use Convoca\Members\Email_Manager;
use Convoca\Members\Members_Upgrade_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/Email_Manager.php';
require_once dirname(__DIR__, 2) . '/includes/Members_Upgrade_Manager.php';

class UpgradeManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_wp_stores']['options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_wp_stores']['options'] = [];
    }

    private function privado(string $metodo): \ReflectionMethod
    {
        // Sin setAccessible(): desde PHP 8.1 la reflexión alcanza los métodos protegidos y en 8.5
        // la llamada está deprecada (el CI corre 8.4 y tampoco la necesita).
        return new \ReflectionMethod(Members_Upgrade_Manager::class, $metodo);
    }

    private function gestor(): Members_Upgrade_Manager
    {
        // El constructor llama a init() de la clase base; para las pruebas unitarias basta con la
        // instancia, sin arrancar la comprobación de versiones.
        return (new \ReflectionClass(Members_Upgrade_Manager::class))->newInstanceWithoutConstructor();
    }

    // ── lo que declara ────────────────────────────────────────────────────────────────────

    public function test_las_migraciones_van_en_orden_de_version(): void
    {
        $callbacks = $this->privado('get_upgrade_callbacks')->invoke($this->gestor());

        $this->assertSame(['1.0.2', '1.0.3', '2.8.47', '2.8.81'], array_keys($callbacks), 'el orden es lo que garantiza que se apliquen en secuencia');
        foreach ($callbacks as $version => $callback) {
            // El callback es [objeto, método] y el método es protegido: no es «callable» desde
            // fuera, pero tiene que existir y recibir la migración correcta.
            $this->assertIsArray($callback, "la migración {$version} tiene que ser [objeto, método]");
            $this->assertSame('upgrade_to_' . str_replace('.', '_', $version), $callback[1], "{$version} apunta a su método");
            $this->assertTrue(method_exists($callback[0], $callback[1]), "el método de {$version} existe");
        }
    }

    public function test_la_version_y_la_opcion_son_las_de_members(): void
    {
        $gestor = $this->gestor();

        $this->assertSame('convoca_members_db_version', $this->privado('get_option_name')->invoke($gestor), 'la opción no puede ser la del núcleo');
        $this->assertSame('convoca_members', $this->privado('get_transient_prefix')->invoke($gestor), 'ni el prefijo de la caché');
    }

    // ── la migración de la plantilla ──────────────────────────────────────────────────────

    public function test_sin_plantilla_guardada_no_hace_nada_y_va_bien(): void
    {
        $ok = $this->privado('upgrade_to_2_8_81')->invoke($this->gestor());

        $this->assertTrue($ok, 'no tener plantilla no es un fallo de la migración');
        $this->assertArrayNotHasKey(Email_Manager::option_name(), $GLOBALS['_wp_stores']['options'], 'y no se crea una');
    }

    public function test_la_migracion_añade_los_datos_de_la_transferencia(): void
    {
        $opcion = Email_Manager::option_name();
        $GLOBALS['_wp_stores']['options'][ $opcion ] = [
            'solicitud_recibida' => [
                'body' => '<p>Hola</p><p>Un miembro del equipo revisará tu solicitud</p>',
            ],
        ];

        $ok = $this->privado('upgrade_to_2_8_81')->invoke($this->gestor());

        $this->assertTrue($ok);
        $cuerpo = $GLOBALS['_wp_stores']['options'][ $opcion ]['solicitud_recibida']['body'];
        $this->assertStringContainsString('{datos_transferencia}', $cuerpo, 'sin el token, el correo no lleva los datos');
        $this->assertStringContainsString('<p>Hola</p>', $cuerpo, 'y lo que ya había se queda donde estaba');
    }

    public function test_si_ya_tiene_el_token_no_se_toca_la_plantilla(): void
    {
        $opcion = Email_Manager::option_name();
        $original = '<p>{datos_transferencia}</p><p>Un miembro del equipo revisará tu solicitud</p>';
        $GLOBALS['_wp_stores']['options'][ $opcion ] = ['solicitud_recibida' => ['body' => $original]];

        $this->privado('upgrade_to_2_8_81')->invoke($this->gestor());

        $this->assertSame(
            $original,
            $GLOBALS['_wp_stores']['options'][ $opcion ]['solicitud_recibida']['body'],
            'la migración es idempotente: no puede duplicar el token ni tocar lo personalizado'
        );
    }

    public function test_una_plantilla_sin_ese_parrafo_no_se_reescribe_a_lo_bruto(): void
    {
        $opcion = Email_Manager::option_name();
        $personalizada = '<p>Texto propio de la asociación, escrito a mano</p>';
        $GLOBALS['_wp_stores']['options'][ $opcion ] = ['solicitud_recibida' => ['body' => $personalizada]];

        $this->privado('upgrade_to_2_8_81')->invoke($this->gestor());

        $this->assertStringContainsString(
            'Texto propio de la asociación',
            $GLOBALS['_wp_stores']['options'][ $opcion ]['solicitud_recibida']['body'],
            'lo escrito a mano no se pierde nunca'
        );
    }

    public function test_una_plantilla_vacia_no_revienta(): void
    {
        $opcion = Email_Manager::option_name();
        $GLOBALS['_wp_stores']['options'][ $opcion ] = [];

        $this->assertTrue((bool) $this->privado('upgrade_to_2_8_81')->invoke($this->gestor()));
    }
}
