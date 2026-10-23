<?php

/**
 * El lector de XLSX que se usa para importar socios desde una hoja de cálculo.
 *
 * `class-xlsx-reader.php` estaba al 0 % (84 statements). Es de los sitios donde un error no da un
 * fatal: da una importación con los datos corridos una columna, o con las tildes rotas, o con dos
 * socios pegados en la misma fila. Por eso lo que se prueba aquí es el parseo con XML de verdad:
 * celdas numéricas, cadenas compartidas, texto en línea, huecos entre columnas y el escapado del CSV.
 */

namespace Convoca\Members\Tests\Unit;

use Convoca\Members\Admin\XLSX_Reader;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/Admin/class-xlsx-reader.php';

class XlsxReaderTest extends TestCase
{
    private static function privado(string $metodo): \ReflectionMethod
    {
        return new \ReflectionMethod(XLSX_Reader::class, $metodo);
    }

    // ── abrir el fichero ────────────────────────────────────────────────────────────────

    public function test_un_fichero_que_no_existe_no_se_abre_en_silencio(): void
    {
        $this->expectException(\RuntimeException::class);

        XLSX_Reader::to_csv('/tmp/no-existe-' . uniqid() . '.xlsx');
    }

    // ── índice de columna ───────────────────────────────────────────────────────────────

    public function test_la_letra_de_la_celda_se_traduce_a_indice(): void
    {
        $col = self::privado('col_index');

        $this->assertSame(0, $col->invoke(null, 'A1'), 'A es la columna 0');
        $this->assertSame(1, $col->invoke(null, 'B2'));
        $this->assertSame(25, $col->invoke(null, 'Z9'));
        $this->assertSame(26, $col->invoke(null, 'AA1'), 'la doble letra sigue contando, no vuelve a empezar');
        $this->assertSame(27, $col->invoke(null, 'AB1'));
    }

    // ── cadenas compartidas ─────────────────────────────────────────────────────────────

    public function test_las_cadenas_compartidas_se_leen_en_orden(): void
    {
        $xml = '<?xml version="1.0"?><sst><si><t>Ana</t></si><si><t>García</t></si></sst>';

        $cadenas = self::privado('parse_shared_strings')->invoke(null, $xml);

        $this->assertSame(['Ana', 'García'], array_values($cadenas), 'el orden es lo que hace que la celda 1 sea «García»');
    }

    public function test_un_xml_sin_cadenas_no_revienta(): void
    {
        $this->assertSame([], self::privado('parse_shared_strings')->invoke(null, '<sst></sst>'));
    }

    // ── la hoja ────────────────────────────────────────────────────────────────────────

    public function test_una_fila_con_numero_y_texto_compartido(): void
    {
        $xml = '<?xml version="1.0"?><worksheet><sheetData>'
             . '<row r="1">'
             . '<c r="A1"><v>1</v></c>'
             . '<c r="B1" t="s"><v>0</v></c>'
             . '</row>'
             . '</sheetData></worksheet>';

        $csv = self::privado('parse_sheet')->invoke(null, $xml, ['Ana']);

        $this->assertStringContainsString('1', $csv);
        $this->assertStringContainsString('Ana', $csv, 'la celda con t="s" toma el valor de las cadenas compartidas');
    }

    public function test_una_celda_con_texto_en_linea(): void
    {
        $xml = '<?xml version="1.0"?><worksheet><sheetData>'
             . '<row r="1"><c r="A1" t="inlineStr"><is><t>Socio de prueba</t></is></c></row>'
             . '</sheetData></worksheet>';

        $csv = self::privado('parse_sheet')->invoke(null, $xml, []);

        $this->assertStringContainsString('Socio de prueba', $csv);
    }

    public function test_un_hueco_entre_columnas_no_pega_los_valores(): void
    {
        // A1 y C1: la B va vacía. Si el hueco se ignora, «uno» y «tres» quedan pegados.
        $xml = '<?xml version="1.0"?><worksheet><sheetData>'
             . '<row r="1">'
             . '<c r="A1"><v>1</v></c>'
             . '<c r="C1"><v>3</v></c>'
             . '</row>'
             . '</sheetData></worksheet>';

        $csv = self::privado('parse_sheet')->invoke(null, $xml, []);
        $linea = trim(explode("\n", trim($csv))[0]);

        $this->assertSame('1,,3', $linea, 'el hueco tiene que quedar como celda vacía (el CSV va con comas)');
    }

    public function test_dos_filas_dan_dos_lineas(): void
    {
        $xml = '<?xml version="1.0"?><worksheet><sheetData>'
             . '<row r="1"><c r="A1"><v>1</v></c></row>'
             . '<row r="2"><c r="A2"><v>2</v></c></row>'
             . '</sheetData></worksheet>';

        $csv   = self::privado('parse_sheet')->invoke(null, $xml, []);
        $lineas = array_values(array_filter(explode("\n", trim($csv)), static fn ($l) => '' !== trim($l)));

        $this->assertCount(2, $lineas, 'una fila del Excel es una línea del CSV');
    }

    // ── el escapado ────────────────────────────────────────────────────────────────────

    public function test_un_valor_normal_no_se_escapa(): void
    {
        $this->assertSame('Ana', self::privado('csv_escape')->invoke(null, 'Ana'));
    }

    public function test_una_coma_obliga_a_entrecomillar(): void
    {
        $this->assertSame('"García, Ana"', self::privado('csv_escape')->invoke(null, 'García, Ana'));
    }

    public function test_las_comillas_se_duplican(): void
    {
        $this->assertSame('"Dijo ""hola"""', self::privado('csv_escape')->invoke(null, 'Dijo "hola"'));
    }

    public function test_un_salto_de_linea_no_parte_la_fila(): void
    {
        $escapado = self::privado('csv_escape')->invoke(null, "dos\nlíneas");

        $this->assertStringStartsWith('"', (string) $escapado, 'un salto de línea dentro de un campo tiene que ir entre comillas');
    }
}
