<?php
/**
 * Real behavioral tests for Email_Manager — acción (CTA) por defecto de cada plantilla.
 *
 * Contrato: una plantilla cuyo cuerpo no trae botón recibe un CTA de respaldo
 * (si no, el email llega sin acción); y todo CTA debe apuntar a un placeholder
 * que el motor sustituya, porque uno fuera de VARIABLES viaja al correo como
 * texto literal y deja el botón roto. Las plantillas que ya llevan botón en su
 * cuerpo no necesitan respaldo y devuelven array() — de ahí que el primero de
 * los tests sea condicional a propósito.
 *
 * El CI de este repo clona solo convoca-members, así que la comprobación que
 * necesita los cuerpos por defecto (construidos con Email_Layout de core) se
 * salta cuando core no está; la de los placeholders del CTA corre siempre.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Email_Manager;

class EmailCtaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Email_Layout se carga de verdad (no se stubea): los cuerpos por defecto
        // se construyen con él y el test debe medir ese HTML, no un sustituto.
        foreach (
            array(
                dirname(__DIR__, 3) . '/convoca-core/includes/Email_Layout.php',
                dirname(__DIR__, 2) . '/includes/Email_Manager.php',
            ) as $file
        ) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        // `install_defaults()` sale sin hacer nada si la opción ya existe, y `get_templates()` lee de ella. Si otra
        // prueba dejó la opción puesta con las plantillas ya borradas (o el orden aleatorio pone esta prueba antes de
        // que se instalen), los cuerpos salían VACÍOS y la comprobación de abajo pasaba por vacío: sin botón en
        // ningún cuerpo, el `if` no se cumplía nunca y la prueba no medía nada. Se borra para que la instalación sea
        // determinista.
        if (class_exists('\\Convoca\\Members\\Email_Manager')) {
            delete_option('convoca_email_templates');
        }
    }

    /**
     * CTA de respaldo de cada plantilla, leído del mapa privado.
     *
     * @return array<string, array{0: string, 1: string}|array{}>
     */
    private function ctas(): array
    {
        $method = new \ReflectionMethod( Email_Manager::class, 'default_cta' );
        $ctas   = array();

        foreach ( Email_Manager::TEMPLATES as $slug ) {
            $ctas[ $slug ] = $method->invoke( null, $slug );
        }

        return $ctas;
    }

    /**
     * Cuerpo por defecto de cada plantilla.
     *
     * Los defaults viven en la opción (los escribe install_defaults(), que corre
     * al activar); get_templates() solo la lee.
     *
     * @return array<string, string>
     */
    private function bodies(): array
    {
        Email_Manager::install_defaults();
        $templates = Email_Manager::get_templates();
        $bodies    = array();

        foreach ( Email_Manager::TEMPLATES as $slug ) {
            $bodies[ $slug ] = (string) ( $templates[ $slug ]['body'] ?? '' );
        }

        return $bodies;
    }

    public function test_toda_plantilla_sin_boton_tiene_cta_de_respaldo(): void
    {
        if ( ! class_exists( '\Convoca\Core\Email_Layout' ) ) {
            $this->markTestSkipped( 'Necesita convoca-core en el workspace para construir los cuerpos por defecto.' );
        }

        $ctas = $this->ctas();
        $bodies = $this->bodies();

        // Sin esta aserción la prueba es **vacua**: con los cuerpos vacíos ninguna plantilla «trae botón», pero
        // tampoco se puede exigir CTA a quien no tiene cuerpo que enviar. Medido: 9 de las 20 plantillas
        // (`credenciales_acceso`, `objetivo_voluntariado_completado`, `voluntariado_cita`, `voluntariado_acuerdo`,
        // `voluntariado_baja_solicitada`, `voluntariado_desvinculacion`, `voluntariado_horas_pendientes`,
        // `confirm_email`, `verify_phone`) NO tienen CTA de respaldo: su red es el botón de su cuerpo por defecto.
        // Si alguien edita ese cuerpo y quita el botón, el correo llega sin acción y nada lo avisa.
        $this->assertNotEmpty(
            array_filter($bodies),
            'Los cuerpos por defecto tienen que existir: sin ellos esta comprobación no mide nada.'
        );

        foreach ( $bodies as $slug => $body ) {
            if ( false === strpos( $body, 'email-btn' ) ) {
                $this->assertNotEmpty(
                    $ctas[ $slug ],
                    "La plantilla '{$slug}' no trae botón y tampoco CTA de respaldo: llegaría sin acción."
                );
            }
        }
    }

    public function test_el_cta_de_respaldo_usa_una_variable_soportada(): void
    {
        foreach ( $this->ctas() as $slug => $cta ) {
            if ( ! $cta ) {
                continue;
            }

            $this->assertContains(
                $cta[0],
                Email_Manager::VARIABLES,
                "El CTA de '{$slug}' apunta a {$cta[0]}, que el motor no sustituye."
            );
            $this->assertNotSame( '', trim( $cta[1] ), "El CTA de '{$slug}' no tiene texto." );
        }
    }
}
