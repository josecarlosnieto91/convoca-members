<?php
/**
 * La pantalla de registros de auditoría de Members.
 *
 * `admin/class-admin-logs.php` estaba al 0 % (142 statements). Es la pantalla donde se mira qué pasó
 * (quién aprobó, quién dio de baja) y donde se puede **borrar** el registro. Lo que se fija: que el
 * borrado exija capacidad y nonce, que borre **solo** los registros de Members y no los de los demás
 * plugins (comparten tabla), y que la paginación no se pueda sacar de rango por la URL.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Logs;
use PHPUnit\Framework\TestCase;

class RegistrosPanelTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/class-admin-logs.php';

		// El arnés responde por consulta: la comprobación de tabla y el COUNT(*) no pueden devolver lo
		// mismo (si el COUNT devuelve el nombre de la tabla, el total sale 0 y la pantalla miente).
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'queries'      => array(),
			'get_var_mapa' => array(
				'SHOW TABLES' => 'wp_convoca_logs',
				'COUNT(*)'    => '3',
			),
			'results'      => array(),
			'acciones'     => array(),
		);
		$GLOBALS['_test_caps'] = array( 'common_view_logs' => true );
		$_GET                  = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array();
		unset( $GLOBALS['_test_caps'], $_GET );
	}

	/**
	 * Pinta la pantalla y devuelve su HTML.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		Admin_Logs::render();
		return (string) ob_get_clean();
	}

	/**
	 * Sin la tabla creada se avisa y no se consulta nada más.
	 */
	public function test_sin_tabla_avisa_y_no_consulta(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SHOW TABLES' => 'otra_tabla' );

		$html = $this->pintar();

		$this->assertStringContainsString( 'no ha sido creada', $html, 'dice qué pasa y cómo arreglarlo' );
		$this->assertStringNotContainsString( 'wp-list-table', $html, 'no se pinta una tabla vacía que parezca «sin datos»' );
	}

	/**
	 * Borrar exige la capacidad: sin ella no se lanza ni el DELETE.
	 */
	public function test_borrar_sin_capacidad_no_borra(): void {
		$GLOBALS['_test_caps']['common_view_logs'] = false;
		$_GET['action'] = 'clear';

		$html = $this->pintar();

		foreach ( (array) $GLOBALS['_wp_stores']['queries'] as $sql ) {
			$this->assertStringNotContainsString( 'DELETE', (string) $sql, 'sin permiso no se borra nada' );
		}
		$this->assertStringNotContainsString( 'borrados', $html );
	}

	/**
	 * El borrado se limita a los registros de Members: la tabla es compartida por todo el ecosistema.
	 */
	public function test_el_borrado_solo_afecta_a_members(): void {
		$_GET['action'] = 'clear';

		$this->pintar();

		$deletes = array_values(
			array_filter(
				(array) $GLOBALS['_wp_stores']['queries'],
				static fn( $sql ) => false !== strpos( (string) $sql, 'DELETE' )
			)
		);

		$this->assertNotEmpty( $deletes, 'con permiso y nonce, el borrado se lanza' );
		$this->assertStringContainsString( "'%Members%'", $deletes[0], 'borra solo lo de Members, no la tabla entera' );
	}

	/**
	 * Los registros por página se recortan al rango 10-200 venga lo que venga en la URL.
	 */
	public function test_los_registros_por_pagina_se_recortan(): void {
		$GLOBALS['_wp_stores']['results'] = array();

		$_GET['per_page'] = '5';
		$this->pintar();
		$html = $this->pintar();
		$this->assertMatchesRegularExpression( '/value="[^"]*per_page=50[^"]*"\s+selected/', $html, 'por debajo de 10 se vuelve a 50' );

		$_GET['per_page'] = '500';
		$html = $this->pintar();
		$this->assertMatchesRegularExpression( '/value="[^"]*per_page=50[^"]*"\s+selected/', $html, 'por encima de 200 también' );

		$_GET['per_page'] = '25';
		$html = $this->pintar();
		$this->assertMatchesRegularExpression( '/value="[^"]*per_page=25[^"]*"\s+selected/', $html, 'un valor válido se respeta' );
	}

	/**
	 * Una página imposible (0 o negativa) se queda en la primera.
	 */
	public function test_una_pagina_imposible_cae_en_la_primera(): void {
		$GLOBALS['_wp_stores']['results'] = array();
		$_GET['paged'] = '0';

		$html = $this->pintar();

		$this->assertStringContainsString( 'Mostrando 1–3 de 3', $html, 'la página 0 se convierte en la 1' );
	}

	/**
	 * Cada registro se pinta con su nivel, su mensaje y su objeto; sin objeto pone raya.
	 */
	public function test_cada_registro_se_pinta(): void {
		$GLOBALS['_wp_stores']['results'] = array(
			(object) array(
				'created_at' => '2026-10-24 10:00:00',
				'level'      => 'info',
				'message'    => 'Socio aprobado',
				'context'    => 'Members</b>',
				'object_id'  => 55,
			),
			(object) array(
				'created_at' => '2026-10-24 11:00:00',
				'level'      => 'warning',
				'message'    => 'Reintento',
				'context'    => 'Members',
				'object_id'  => 0,
			),
		);

		$html = $this->pintar();

		$this->assertStringContainsString( 'Socio aprobado', $html );
		$this->assertStringContainsString( 'member_id=55', $html, 'el objeto enlaza a su ficha' );
		$this->assertStringContainsString( '—', $html, 'sin objeto, raya en vez de un enlace roto' );
		$this->assertStringContainsString( 'Members&lt;/b&gt;', $html, 'el contexto va escapado' );
	}

	/**
	 * Sin registros se dice, no se deja la tabla vacía y muda.
	 */
	public function test_sin_registros_se_dice(): void {
		$GLOBALS['_wp_stores']['results'] = array();

		$this->assertStringContainsString( 'No hay logs registrados', $this->pintar() );
	}
}
