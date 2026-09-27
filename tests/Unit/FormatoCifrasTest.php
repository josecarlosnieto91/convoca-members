<?php
/**
 * Las cifras que ve una persona llevan separadores castellanos explícitos.
 *
 * `number_format()` sin separadores usa los de PHP: punto decimal y coma de millar, que en
 * castellano se leen al revés («12.5 h» en vez de «12,5», «50.00 €» en vez de «50,00 €»). Pasó en
 * dos sitios de la parte pública —la verificación del certificado y el importe de renovación del
 * panel— mientras otras dos partes del mismo plugin sí los pasaban bien. No es cuestión de estilo:
 * es la cifra que ve el socio.
 *
 * `number_format( $x, 0 )` no entra aquí: sin decimales no hay separador que equivocar.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class FormatoCifrasTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return string[] */
    private function fuentes(): array
    {
        $lista = array();
        foreach (array('includes', 'public', 'admin') as $carpeta) {
            $dir = $this->raiz() . '/' . $carpeta;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $f) {
                if (str_ends_with($f->getPathname(), '.php')) {
                    $lista[] = $f->getPathname();
                }
            }
        }

        return $lista;
    }

    public function test_ninguna_cifra_con_decimales_usa_los_separadores_por_defecto(): void
    {
        $malos = array();

        foreach ($this->fuentes() as $ruta) {
            $fuente = (string) file_get_contents($ruta);
            // number_format( …, 1 ) o ( …, 2 ) sin los dos separadores detrás.
            if (preg_match_all('/number_format\([^;)]*?,\s*[1-9][0-9]*\s*\)/', $fuente, $m)) {
                $malos[] = basename($ruta) . ': ' . implode(' · ', $m[0]);
            }
        }

        $this->assertSame(
            array(),
            $malos,
            "Estas cifras se formatean con los separadores de PHP (punto decimal):\n  " . implode("\n  ", $malos)
        );
    }

    public function test_la_verificacion_del_certificado_usa_la_coma_castellana(): void
    {
        $fuente = (string) file_get_contents($this->raiz() . '/public/class-verificar-certificado.php');

        $this->assertStringContainsString('Texto_PDF::horas', $fuente);
        $this->assertStringNotContainsString("number_format( \$result['horas'], 1 )", $fuente);
    }

    public function test_el_importe_de_renovacion_del_panel_lleva_los_separadores(): void
    {
        $fuente = (string) file_get_contents($this->raiz() . '/public/class-mi-area.php');

        $this->assertStringContainsString("number_format( (float) \$importe, 2, ',', '.' )", $fuente);
    }
}
