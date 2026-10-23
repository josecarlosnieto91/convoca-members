<?php
/**
 * El panel de voluntariado: lo que ve la junta cuando abre la pantalla.
 *
 * Dos listas y un tablero. La prueba mira lo que un panel así puede romper en silencio: que la solicitud de
 * alguien **no** aparezca (y esa persona se quede esperando), que el botón de aprobar lleve el nonce de revocar,
 * o que una ficha sin datos salga igual que una completa y nadie sepa a quién le falta qué.
 *
 * Se lee el HTML generado, no el código: es lo que ve quien gestiona.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Voluntariado;
use Convoca\Members\Circuito_Voluntariado;
use Convoca\Members\Roles_Miembro;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Circuito_Voluntariado.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Voluntariado.php';

/**
 * Panel de voluntariado.
 */
final class AdminVoluntariadoRenderTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_test_caps'], $GLOBALS['_wp_nonces'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
	}

	/**
	 * Un usuario del almacén.
	 *
	 * @param int    $id     Id.
	 * @param string $nombre Nombre de pila.
	 * @param array  $roles  Roles.
	 */
	private function usuario( int $id, string $nombre, array $roles = array() ): void {
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = (object) array(
			'ID'           => $id,
			'display_name' => $nombre,
			'first_name'   => $nombre,
			'user_email'   => strtolower( $nombre ) . '@example.test',
			'roles'        => $roles,
		);
	}

	/**
	 * Una ficha de miembro.
	 *
	 * @param int   $id   Id.
	 * @param array $meta Metadatos.
	 * @param string $titulo Título.
	 */
	private function ficha( int $id, array $meta, string $titulo = 'Ficha' ): void {
		$post                                         = (object) array(
			'ID'           => $id,
			'post_type'    => 'miembro',
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_date'    => '2026-10-01 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]                = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ]        = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]    = $meta;
	}

	/**
	 * Captura lo que imprime una función.
	 *
	 * @param callable $fn Función que imprime.
	 * @return string
	 */
	private function html( callable $fn ): string {
		ob_start();
		$fn();

		return (string) ob_get_clean();
	}

	/**
	 * Sin permiso no se imprime nada: el panel no se asoma por una puerta lateral.
	 */
	public function test_sin_permiso_no_pinta_nada(): void {
		$GLOBALS['_test_caps']['manage_options'] = false;

		$this->assertSame(
			'',
			$this->html( static fn() => Admin_Voluntariado::render_management() ),
			'sin manage_options no se pinta ni el encabezado'
		);
	}

	/**
	 * Una solicitud pendiente sale con lo que hace falta para decidir: quién es, cómo se la localiza y qué cuenta.
	 */
	public function test_la_solicitud_pendiente(): void {
		$this->usuario( 7, 'Ada' );
		$GLOBALS['_wp_stores']['user_meta'][7] = array(
			Admin_Voluntariado::META_APROBADO         => '0',
			'_convoca_telefono'                        => '600000000',
			'_convoca_voluntario_motivacion'           => 'Quiero echar una mano con el huerto.',
		);

		// Y otra sin teléfono: ahí es donde el hueco tiene que marcarse en vez de quedarse en blanco.
		$this->usuario( 10, 'Bea' );
		$GLOBALS['_wp_stores']['user_meta'][10] = array( Admin_Voluntariado::META_APROBADO => '0' );

		$html = $this->html( static fn() => Admin_Voluntariado::render_management() );

		$this->assertStringContainsString( 'Solicitudes Pendientes', $html );
		$this->assertStringContainsString( 'Bea', $html, 'la segunda solicitud también sale' );
		$this->assertStringContainsString( 'Ada', $html );
		$this->assertStringContainsString( 'ada@example.test', $html, 'el correo, para poder escribirle' );
		$this->assertStringContainsString( '600000000', $html );
		$this->assertStringContainsString( 'Quiero echar una mano con el huerto.', $html, 'su motivación' );
		// Bea no dejó teléfono y Ada sí: el hueco se marca con —, no se deja en blanco. (La motivación vacía sí
		// queda vacía: es texto libre de la persona, no un dato del sistema.)
		$this->assertSame( 1, substr_count( $html, '<td>—</td>' ), 'el teléfono que falta se marca con —' );
		$this->assertStringContainsString(
			'nonce-convoca_volunteer_approve_7',
			$html,
			'el botón de aprobar lleva **su** nonce'
		);
		$this->assertStringNotContainsString(
			'nonce-convoca_volunteer_revoke_7',
			$html,
			'y no el de revocar, que haría lo contrario de lo que dice el botón'
		);
	}

	/**
	 * Y si no hay nadie, se dice: un panel en blanco parece roto.
	 */
	public function test_sin_solicitudes_se_dice(): void {
		$html = $this->html( static fn() => Admin_Voluntariado::render_management() );

		$this->assertStringContainsString( 'No hay solicitudes pendientes', $html );
		$this->assertStringContainsString( 'No hay voluntarios activos registrados', $html );
	}

	/**
	 * Un voluntario en activo con ficha enlazada ofrece verla; sin ficha, se marca el hueco.
	 */
	public function test_los_activos_y_su_ficha(): void {
		$this->usuario( 8, 'Grace', array( 'voluntario_aprobado' ) );
		$GLOBALS['_wp_stores']['user_meta'][8] = array( '_convoca_member_id' => 55, '_convoca_telefono' => '' );
		$this->usuario( 9, 'Linus', array( 'voluntario_aprobado' ) );
		$GLOBALS['_wp_stores']['user_meta'][9] = array( '_convoca_member_id' => 0 );

		$html = $this->html( static fn() => Admin_Voluntariado::render_management() );

		$this->assertStringContainsString( 'Voluntarios Activos', $html );
		$this->assertStringContainsString( 'Ver ficha', $html, 'el que la tiene puede abrirla' );
		$this->assertStringContainsString( 'post=55&action=edit', $html, 'y es la suya' );
		$this->assertStringContainsString( 'nonce-convoca_volunteer_revoke_8', $html, 'se le puede revocar' );
		$this->assertSame( 1, substr_count( $html, 'Ver ficha' ), 'sólo quien tiene ficha enlazada puede abrirla' );
		$this->assertStringContainsString( 'Linus', $html );
		$this->assertSame( 0, substr_count( $html, '<td></td>' ), 'sin teléfono ni ficha el hueco se marca con —' );
	}

	/**
	 * El tablero del circuito: cada paso con su cuenta y **lo que le falta** a cada persona.
	 */
	public function test_el_circuito_marca_lo_pendiente(): void {
		// Una ficha en el circuito (con la condición de voluntario) y otra que sólo tiene el paso apuntado.
		$this->ficha(
			55,
			array(
				Roles_Miembro::META_VOLUNTARIO => '1',
				Circuito_Voluntariado::META_ESTADO => 'solicitud',
				'_convoca_email'               => 'ada@example.test',
			),
			'Ada Lovelace'
		);

		$html = $this->html( static fn() => Admin_Voluntariado::render_circuito() );

		$this->assertStringContainsString( 'El circuito del voluntariado', $html );
		foreach ( Circuito_Voluntariado::pasos() as $etiqueta ) {
			$this->assertStringContainsString( esc_html( $etiqueta ), $html, 'todos los pasos tienen su bloque' );
		}
		$this->assertStringContainsString( 'Ada Lovelace', $html, 'la persona aparece' );

		// El ⚠ también está en el párrafo de presentación, así que aquí se afirma **qué falta**, no el símbolo.
		$faltantes = Circuito_Voluntariado::faltantes( 55 );
		$this->assertNotEmpty( $faltantes, 'a esta ficha le falta algo (es el caso que se quiere ver)' );
		$this->assertStringContainsString(
			esc_html( implode( ' · ', $faltantes ) ),
			$html,
			'y el tablero dice exactamente qué'
		);

		// Y los pasos donde no hay nadie lo dicen: un bloque vacío parece un fallo de carga.
		$this->assertStringContainsString( 'Nadie en este paso', $html );
	}

	/**
	 * La insignia de monitor sólo la lleva quien lo es: una marca de más le da a alguien una autoridad que no
	 * tiene delante de la junta.
	 */
	public function test_la_insignia_de_monitor(): void {
		$comun = array(
			Roles_Miembro::META_VOLUNTARIO => '1',
			Circuito_Voluntariado::META_ESTADO => 'activo',
		);
		$this->ficha( 55, $comun + array( Roles_Miembro::META_MONITOR => '1', '_convoca_email' => 'moni@example.test' ), 'Moni' );
		$this->ficha( 56, $comun + array( '_convoca_email' => 'volun@example.test' ), 'Volun' );

		$html = $this->html( static fn() => Admin_Voluntariado::render_circuito() );

		$this->assertStringContainsString( 'convoca-badge', $html, 'la insignia existe' );
		$this->assertSame( 1, substr_count( $html, 'convoca-badge' ), 'y la lleva una sola de las dos' );
		$pos_moni = strpos( $html, 'Moni' );
		$pos_badge = strpos( $html, 'convoca-badge' );
		$this->assertNotFalse( $pos_moni );
		$this->assertLessThan( 200, abs( $pos_badge - $pos_moni ), 'y está pegada al nombre de quien la tiene' );
	}

	/**
	 * `render_page()` sin permiso mata: no es que no pinte, es que no se pasa.
	 */
	public function test_render_page_sin_permiso_mata(): void {
		$GLOBALS['_test_caps']['manage_options'] = false;
		// `wp_die()` mata de verdad (lanza): sin eso, la guarda seguía y el panel se pintaba igual.
		$GLOBALS['_wp_die_lanza'] = true;
		$GLOBALS['_wp_die_llamado'] = '';

		ob_start();
		$mato = false;
		try {
			Admin_Voluntariado::render_page();
		} catch ( \RuntimeException $e ) {
			$mato = true;
		}
		$salida = (string) ob_get_clean();

		$this->assertTrue( $mato, 'sin permiso se corta ahí' );
		$this->assertSame( '', $salida, 'y no se pinta ni el contenedor' );
		$this->assertStringContainsString( 'permisos', (string) $GLOBALS['_wp_die_llamado'], 'con su motivo' );
	}

	/**
	 * Con permiso, `render_page()` envuelve la gestión en su contenedor y su título.
	 */
	public function test_render_page_con_permiso(): void {
		$html = $this->html( static fn() => Admin_Voluntariado::render_page() );

		$this->assertStringContainsString( 'class="wrap"', $html );
		$this->assertStringContainsString( 'Voluntarios', $html );
	}
}
