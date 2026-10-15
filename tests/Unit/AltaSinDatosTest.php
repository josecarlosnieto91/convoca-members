<?php
/**
 * Un alta con el nombre y el DNI en blanco no puede pasar la validación.
 *
 * Defecto real (issue #23, reproducido en el banco): el alta validaba con `empty()`, y `empty( '     ' )`
 * es **FALSE** —una cadena de espacios no está vacía—. El dato se saneaba DESPUÉS, así que se creaba una
 * ficha con el título vacío y el `_convoca_dni` vacío. Y esa ficha casaba con el deduplicador de cualquier
 * alta posterior que llegara con el DNI en blanco, que quedaba rechazada con «Ya existe un miembro
 * registrado con este DNI o email.» sin que quien la sufre pueda hacer nada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

class AltaSinDatosTest extends TestCase
{
    /** Datos válidos de partida, para romper solo lo que se está probando. */
    private function datosValidos(): array
    {
        return array(
            'nombre'           => 'Ana Ejemplo',
            'dni'              => '12345678Z',
            'email'            => 'ana@example.org',
            'fecha_nacimiento' => '1990-05-05',
            'telefono'         => '600111222',
            'direccion'        => 'Calle Ejemplo 1',
            'municipio'        => 'Oviedo',
            'plan'             => 'individual',
            'forma_pago'       => 'transferencia',
            'rgpd'             => '1',
        );
    }

    public function test_un_nombre_en_blanco_no_vale(): void
    {
        $datos             = $this->datosValidos();
        $datos['nombre']   = '      ';

        $resultado = Process_Member::validate( $datos );

        $this->assertInstanceOf( \WP_Error::class, $resultado, 'Un nombre en blanco tiene que rechazarse.' );
        $this->assertContains( 'El nombre es obligatorio.', $resultado->get_error_messages() );
    }

    public function test_un_dni_en_blanco_no_vale(): void
    {
        $datos          = $this->datosValidos();
        $datos['dni']   = "  \t ";

        $resultado = Process_Member::validate( $datos );

        $this->assertInstanceOf( \WP_Error::class, $resultado, 'Un DNI en blanco tiene que rechazarse.' );
        $this->assertContains( 'El DNI/NIE es obligatorio.', $resultado->get_error_messages() );
    }

    /**
     * El negativo: la comprobación que había antes (`empty()`) no veía el problema.
     *
     * No es una suposición sobre el código viejo: es PHP.
     */
    public function test_empty_no_ve_una_cadena_de_espacios(): void
    {
        $this->assertFalse( empty( '     ' ), 'empty( "     " ) es FALSE: por eso el alta en blanco pasaba.' );
        $this->assertTrue( empty( trim( '     ' ) ), 'Y por eso el arreglo es sanear antes de validar.' );
    }

    public function test_un_alta_correcta_sigue_pasando(): void
    {
        $resultado = Process_Member::validate( $this->datosValidos() );

        $this->assertTrue( $resultado, 'Una alta con los datos bien no puede empezar a rechazarse.' );
    }
}
