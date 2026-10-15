<?php
/**
 * Los tipos con pantalla propia ya no ofrecen además su pantalla cruda de WordPress (issue #15).
 *
 * `register_post_type()` con `show_ui => true` le dice a WordPress que cree `edit.php?post_type=X`,
 * `post-new.php?post_type=X` y `post.php?post=N`. Si el plugin además registra su propia página, quedan **dos
 * pantallas para lo mismo**, y `show_in_menu => false` solo esconde una del menú: **la pantalla sigue viva y
 * responde** por URL. En socios, además, la cruda ofrecía «Mover a la papelera» sin que nada la vaciara.
 *
 * Se vigilan las dos mitades: que la cruda esté apagada **y que la pantalla propia siga existiendo** — apagar la
 * cruda sin tener la propia dejaría al plugin sin interfaz.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class PantallasCrudasApagadasTest extends TestCase {

	/**
	 * Tipo => [fichero del CPT, página propia].
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	private function tipos(): array {
		return array(
			'miembro'       => array( 'includes/CPT_Miembro.php', 'conv-members' ),
			'proyecto'      => array( 'includes/CPT_Proyecto.php', 'conv-proyectos' ),
			'registro_hora' => array( 'includes/CPT_Registro_Hora.php', 'conv-horas' ),
		);
	}

	public function test_la_pantalla_cruda_esta_apagada(): void {
		foreach ( $this->tipos() as $tipo => $datos ) {
			$codigo = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $datos[0] );
			$this->assertMatchesRegularExpression( "/'show_ui'\s*=>\s*false,/", $codigo, "{$tipo}: con pantalla propia, la cruda de WordPress sobra" );
			// NO se exige que no quede ningún `show_ui => true` en el fichero: `miembro` registra aquí
			// también su TAXONOMÍA, y en una taxonomía ese `true` es legítimo (sin él no hay pantalla de
			// términos). Mi primera versión lo exigía y se tropezaba con eso: el test estaba mal, no el código.
		}
	}

	public function test_la_pantalla_propia_sigue_existiendo(): void {
		$admin = '';
		foreach ( glob( dirname( __DIR__, 2 ) . '/admin/*.php' ) as $f ) {
			$admin .= (string) file_get_contents( $f );
		}
		foreach ( $this->tipos() as $tipo => $datos ) {
			$this->assertStringContainsString( $datos[1], $admin, "{$tipo}: apagar la cruda sin la propia dejaría el plugin sin interfaz" );
		}
	}
	public function test_la_url_cruda_lleva_a_la_pantalla_propia(): void {
		// Con `show_ui => false` la URL sigue existiendo y WordPress la resuelve con un **500 sin mensaje**.
		// Medido en la demo (con administrador): las tres daban 500. El plugin declara aquí **a dónde** va
		// cada tipo; la ceremonia (el `admin_init`, la salida) vive en el andamiaje del núcleo, en un solo
		// sitio (core#16) — antes estaba copiada en este fichero y por eso `proyecto` mandaba el alta a la
		// lista: el mapa no distinguía «alta» de «listado».
		$arranque = (string) file_get_contents( dirname( __DIR__, 2 ) . '/convoca-members.php' );

		$this->assertStringContainsString(
			'redirigir_pantallas_crudas(',
			$arranque,
			'El redirector se pide al andamiaje: una sola ceremonia en todo el ecosistema'
		);

		$tipos = $this->tipos();
		// Los documentos no tienen pantalla propia: se gestionan en la ficha del socio.
		$tipos['convoca_documento'] = array( 'includes/CPT_Documento.php', 'conv-members' );

		foreach ( $tipos as $tipo => $datos ) {
			$this->assertStringContainsString( "'{$tipo}'", $arranque, "{$tipo}: tiene que estar en los destinos" );
			$this->assertStringContainsString( $datos[1], $arranque, "{$tipo}: y llevar a su pantalla propia" );
		}

		// El alta va al editor propio y no a la lista (el defecto de `proyecto`, core#16).
		$this->assertStringContainsString( "'alta'", $arranque, 'El mapa distingue el alta del listado' );
		$this->assertStringContainsString( 'conv-proyecto-editor', $arranque, 'La alta de proyecto va a su editor' );
	}
}
