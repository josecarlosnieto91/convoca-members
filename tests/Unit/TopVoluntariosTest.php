<?php
/**
 * El top de voluntarios (issue #31).
 *
 * La web prometía «Tracking de Horas: informes, **top voluntarios** y exportación a CSV/PDF» y el «top» no
 * existía (cero apariciones de `ranking`, `leaderboard` o `top_voluntario`). Lo que importa aquí no es que
 * ordene de más a menos —eso es lo de menos— sino que **cuente las mismas horas que la renovación**: si el
 * ranking y la renovación usaran fuentes distintas, dirían cosas distintas del mismo voluntariado.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class TopVoluntariosTest extends TestCase {

	private function codigo(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/Voluntariado_Gamification.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	public function test_cuenta_las_mismas_horas_que_la_renovacion(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( 'Voluntariado_Manager::get_horas_aprobadas_desde(', $codigo, 'El periodo tiene que salir de la misma fuente que la renovación' );
		$this->assertStringContainsString( 'Voluntariado_Manager::get_horas_aprobadas(', $codigo, 'Y el histórico de la misma' );
		$this->assertStringNotContainsString( '_convoca_horas_voluntariado_total', $codigo, 'No vale el agregado heredado: la fuente es la del libro de horas' );
	}

	public function test_ordena_de_mas_a_menos_y_desempata_sin_capricho(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( '$b[', $codigo, 'Ordena por las horas de cada fila' );
		$this->assertStringContainsString( '<=> $a', $codigo, 'De más a menos horas' );
		$this->assertStringContainsString( 'strcoll(', $codigo, 'Y en empate, alfabético: nadie sale antes por casualidad del orden de la consulta' );
	}

	public function test_el_limite_tiene_techo(): void {
		$this->assertStringContainsString( 'min( 100, (int) $args[', $this->codigo(), 'Un límite de 100.000 no puede tumbar la página' );
	}

	public function test_el_shortcode_esta_enganchado_y_es_prudente_por_defecto(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( "add_shortcode( 'convoca_top_voluntarios'", $codigo );
		$this->assertStringContainsString( "'publico' => '0'", $codigo, 'Por defecto NO se enseña a cualquiera: son nombres y horas de personas' );
		$this->assertStringContainsString( 'is_user_logged_in()', $codigo );
	}

	public function test_sin_sesion_no_ensena_el_ranking(): void {
		// Se doblan las tres funciones que el arnés no trae, para poder comprobarlo de verdad.
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			function is_user_logged_in() {
				return false;
			}
		}
		if ( ! function_exists( 'shortcode_atts' ) ) {
			function shortcode_atts( $pares, $atts, $shortcode = '' ) {
				return array_merge( $pares, (array) $atts );
			}
		}
		if ( ! function_exists( 'wp_parse_args' ) ) {
			function wp_parse_args( $args, $defaults = array() ) {
				return array_merge( $defaults, (array) $args );
			}
		}
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/Voluntariado_Gamification.php';

		$html = \Convoca\Members\Voluntariado_Gamification::shortcode_top();
		$this->assertStringContainsString( 'Entra en tu cuenta', $html );
		$this->assertStringNotContainsString( '<ol', $html, 'Sin sesión no puede salir ni un nombre' );
	}
}
