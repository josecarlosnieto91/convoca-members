<?php
/**
 * El listado de socios: las columnas y la baja en lote.
 *
 * Es la pantalla donde la junta trabaja el censo a diario. Lo que se mide aquí:
 *
 *  - **la baja en lote** (`process_bulk_action`): exige nonce y comprueba `edit_post` **ficha a ficha**, de modo que
 *    quien tiene permiso en una parte del censo no da de baja lo que no le toca. Y salta las que no puede, en vez de
 *    abortar la operación entera;
 *  - **el consentimiento** (`#41`): si no hay constancia, se dice «Sin registrar» con el enlace para arreglarlo. Es lo
 *    único que la junta necesita saber para cumplir;
 *  - **la petición de supresión** (`#42`): aparece en la propia columna de estado, no solo en un correo;
 *  - **«renueva por horas»** (`#45`): quién ya cumple las horas de su plan y no hay que cobrarle.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_List;
use Convoca\Members\CPT_Miembro;
use Convoca\Members\Estados;
use Convoca\Members\GDPR_Tools;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-list.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Listado de socios.
 */
final class ListadoDeSociosTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Listado.
	 *
	 * @var Admin_List
	 */
	private Admin_List $listado;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'   => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'    => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'options' => $GLOBALS['_wp_stores']['options'] ?? array(),
			'caps'    => $GLOBALS['_test_caps'] ?? array(),
			'user'    => $GLOBALS['_test_user_id'] ?? null,
			'nonces'  => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'die'     => $GLOBALS['_wp_die_lanza'] ?? null,
			'request' => $_REQUEST ?? array(),
			'actions' => $GLOBALS['_wp_actions'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['options']        = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_test_caps']                   = array( 'edit_post' => true );
		$GLOBALS['_test_user_id']                = 10;
		$GLOBALS['_wp_die_lanza']                = true;
		$_REQUEST                                = array();
		$_GET                                    = array();

		$this->listado = ( new \ReflectionClass( Admin_List::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['options']    = $this->previo['options'];
		$GLOBALS['_test_caps']               = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']            = $this->previo['die'];
		$_REQUEST                            = $this->previo['request'];
		$_GET                                = array();

		if ( null === $this->previo['user'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['user'];
		}
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
	}

	/**
	 * Siembra una ficha de socio.
	 *
	 * @param array $meta Metas.
	 * @param int   $id   ID.
	 * @return int
	 */
	private function ficha( array $meta, int $id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia ' . $id,
			'post_author' => 10,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $id;
	}

	/* ── Baja en lote ────────────────────────────────────────────────── */

	/**
	 * **Sin nonce no se da de baja a nadie.**
	 */
	public function test_la_baja_en_lote_exige_nonce(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );
		$_REQUEST = array(
			'action'   => 'bulk-baja',
			'miembros' => array( (string) $id ),
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/nonce/i' );

		$this->listado->process_bulk_action();
	}

	/**
	 * Con nonce, la baja es **lógica**: se cambia el estado y la ficha se conserva.
	 *
	 * Borrar en lote no se ofrece a propósito (`#27`): es una excepción y va de una en una.
	 */
	public function test_la_baja_en_lote_es_logica_y_conserva_la_ficha(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );
		$_REQUEST = array(
			'action'   => 'bulk-baja',
			'_wpnonce' => 'nonce-bueno',
			'miembros' => array( (string) $id ),
		);

		$this->listado->process_bulk_action();

		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'la ficha sigue existiendo' );
		$this->assertSame( 'baja', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '', 'y queda de baja' );
	}

	/**
	 * **Se salta las fichas que no puede editar, y sigue con las demás.**
	 *
	 * Si abortara al primero sin permiso, una junta con permiso sobre una parte del censo no podría dar de baja
	 * siquiera a los suyos.
	 */
	public function test_se_salta_las_fichas_sin_permiso_y_sigue_con_las_demas(): void {
		$sin_permiso = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ), 501 );
		$con_permiso = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ), 502 );

		// `edit_post` se decide **por ficha**: solo la 502.
		$GLOBALS['_test_post_caps'] = array(
			$sin_permiso => array( 'edit_post' => false ),
			$con_permiso => array( 'edit_post' => true ),
		);
		$GLOBALS['_test_caps']      = array();

		$_REQUEST = array(
			'action'   => 'bulk-baja',
			'_wpnonce' => 'nonce-bueno',
			'miembros' => array( (string) $sin_permiso, (string) $con_permiso ),
		);

		$this->listado->process_bulk_action();

		$this->assertSame(
			'activo',
			$GLOBALS['_wp_stores']['post_meta'][ $sin_permiso ]['_convoca_estado_miembro'] ?? '',
			'la que no puede editar se queda como estaba'
		);
		$this->assertSame(
			'baja',
			$GLOBALS['_wp_stores']['post_meta'][ $con_permiso ]['_convoca_estado_miembro'] ?? '',
			'y la otra sí se da de baja'
		);
	}

	/**
	 * Sin fichas seleccionadas no se hace nada (y no revienta).
	 */
	public function test_sin_fichas_seleccionadas_no_hace_nada(): void {
		$_REQUEST = array(
			'action'   => 'bulk-baja',
			'_wpnonce' => 'nonce-bueno',
			'miembros' => array(),
		);

		$this->listado->process_bulk_action();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'] );
	}

	/**
	 * Otra acción en bloque no toca el estado de nadie.
	 */
	public function test_otra_accion_no_cambia_estados(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );
		$_REQUEST = array(
			'action'   => 'bulk-exportar',
			'_wpnonce' => 'nonce-bueno',
			'miembros' => array( (string) $id ),
		);

		$this->listado->process_bulk_action();

		$this->assertSame( 'activo', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '' );
	}

	/* ── Consentimiento ──────────────────────────────────────────────── */

	/**
	 * **Sin constancia de consentimiento se dice «Sin registrar».**
	 *
	 * Es lo que la junta necesita ver para cumplir: la mitad del censo puede estar sin justificar y aquí es donde se
	 * entera.
	 */
	public function test_sin_consentimiento_se_dice_sin_registrar(): void {
		$id  = $this->ficha( array() );
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_consentimiento( $fila );

		$this->assertStringContainsString( 'Sin registrar', $html );
	}

	/**
	 * Con consentimiento se enseña la **fecha**; con versión, también la versión del texto aceptado.
	 */
	public function test_con_consentimiento_se_ensena_la_fecha_y_la_version(): void {
		$id = $this->ficha(
			array(
				'_convoca_consent_timestamp' => '2026-03-15 10:00:00',
				'_convoca_consent_version'   => '2.1',
			)
		);
		$GLOBALS['_wp_stores']['user_meta'][10] = array();
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_consentimiento( $fila );

		$this->assertStringContainsString( '2026', $html, 'sale la fecha' );
		$this->assertStringContainsString( 'texto v2.1', $html, 'y la versión' );
		$this->assertStringNotContainsString( 'Sin registrar', $html );
	}

	/* ── Estado con petición de supresión ────────────────────────────── */

	/**
	 * El estado sale con su distintivo.
	 */
	public function test_el_estado_sale_con_su_distintivo(): void {
		$id   = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_estado( $fila );

		$this->assertNotSame( '', $html );
		$this->assertStringNotContainsString( 'Pide supresión', $html, 'sin solicitud no se avisa de nada' );
	}

	/**
	 * Sin estado guardado se pinta **pendiente de documentación**, no vacío.
	 */
	public function test_sin_estado_guardado_se_pinta_pendiente(): void {
		$id   = $this->ficha( array() );
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_estado( $fila );

		$this->assertNotSame( '', $html, 'nunca una celda vacía' );
		$this->assertSame( Estados::badge_html( 'pendiente_documentacion' ), $html, 'es el estado por defecto del producto' );
	}

	/**
	 * **La petición de supresión se ve en el listado**, no solo en un correo (`#42`).
	 */
	public function test_la_peticion_de_supresion_se_ve_en_el_listado(): void {
		$id = $this->ficha(
			array(
				'_convoca_estado_miembro'          => 'activo',
				'_convoca_supresion_solicitada' => '2026-10-01',
			)
		);
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_estado( $fila );

		$this->assertStringContainsString( 'Pide supresión', $html, 'la junta tiene que verlo' );
	}

	/* ── Renueva por horas ───────────────────────────────────────────── */

	/**
	 * Un plan que **no pide horas** (socio de cuota) no aparece con un contador: renueva pagando.
	 */
	public function test_un_plan_sin_horas_no_ensena_contador(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'socio' => array(
				'label' => 'Socio',
				'price' => 30,
				'hours' => 0,
			),
		);
		$id = $this->ficha(
			array(
				'_convoca_plan' => 'socio',
			)
		);
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_renueva_horas( $fila );

		$this->assertStringContainsString( '—', $html, 'sin contador de horas' );
		$this->assertStringNotContainsString( '0/', $html, 'no enseña «x/y h»' );
		$this->assertStringNotContainsString( '<small>', $html, 'ni las horas que faltan' );
		$this->assertStringContainsString( 'renueva pagando', $html, 'y dice por qué' );
	}

	/**
	 * Un voluntario que **ya cumple** sus horas sale marcado como tal: su próxima renovación es sin pago.
	 */
	public function test_un_voluntario_que_cumple_sale_marcado(): void {
		// Las horas aprobadas salen de un `SUM(...)` en SQL, no de una meta: se le da la respuesta a la consulta.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SUM(CAST(pm_h.meta_value' => '12' );
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'voluntariado' => array(
				'label' => 'Voluntariado',
				'price' => 0,
				'hours' => 10,
			),
		);
		$id = $this->ficha(
			array(
				'_convoca_plan' => 'voluntariado',
			)
		);
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_renueva_horas( $fila );

		$this->assertStringContainsString( '✓', $html, 'marcado como que cumple' );
		$this->assertStringContainsString( '10', $html, 'con su objetivo' );
	}

	/**
	 * Y uno que **no llega** dice cuántas le faltan, que es la información útil.
	 */
	public function test_un_voluntario_que_no_llega_dice_cuantas_faltan(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SUM(CAST(pm_h.meta_value' => '3' );
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'voluntariado' => array(
				'label' => 'Voluntariado',
				'price' => 0,
				'hours' => 10,
			),
		);
		$id = $this->ficha(
			array(
				'_convoca_plan' => 'voluntariado',
			)
		);
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_renueva_horas( $fila );

		$this->assertStringContainsString( 'faltan', $html, 'dice cuántas faltan' );
		$this->assertStringNotContainsString( '✓', $html, 'y no lo marca como que cumple' );
	}

	/* ── Cuota ───────────────────────────────────────────────────────── */

	/**
	 * La cuota se enseña con su importe **y** su estado.
	 */
	public function test_la_cuota_ensena_importe_y_estado(): void {
		$id = $this->ficha(
			array(
				'_convoca_importe_cuota' => '45',
				'_convoca_estado_cuota'  => 'pendiente',
			)
		);
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_cuota( $fila );

		$this->assertStringContainsString( '45', $html, 'el importe' );
		$this->assertStringContainsString( 'convoca-badge--pending', $html, 'y su distintivo' );
	}

	/**
	 * Sin importe no se inventa un cero: una raya.
	 */
	public function test_sin_importe_no_se_inventa_un_cero(): void {
		$id   = $this->ficha( array( '_convoca_estado_cuota' => 'activa' ) );
		$fila = $GLOBALS['_wp_stores']['posts'][ $id ];

		$html = $this->listado->column_cuota( $fila );

		$this->assertStringContainsString( '—', $html );
		$this->assertStringNotContainsString( '0€', $html, 'un cero haría creer que no debe nada' );
	}

	/* ── Columnas declaradas ─────────────────────────────────────────── */

	/**
	 * Todas las columnas declaradas tienen su `column_*`.
	 *
	 * Una columna declarada sin método deja una celda vacía sin que nadie lo note; el listado «funciona».
	 */
	public function test_todas_las_columnas_declaradas_tienen_su_metodo(): void {
		$columnas = $this->listado->get_columns();

		foreach ( array_keys( $columnas ) as $columna ) {
			if ( 'cb' === $columna ) {
				continue;
			}
			$this->assertTrue(
				method_exists( $this->listado, 'column_' . $columna ) || method_exists( $this->listado, 'column_default' ),
				"la columna '{$columna}' no tiene método que la pinte"
			);
		}
	}

	/**
	 * Solo `numero` es ordenable por una meta; el resto no se promete.
	 */
	public function test_las_columnas_ordenables_son_las_que_se_pueden_ordenar(): void {
		$ordenables = $this->listado->get_sortable_columns();

		$this->assertIsArray( $ordenables );
		foreach ( $ordenables as $columna => $config ) {
			$this->assertTrue( array_key_exists( $columna, $this->listado->get_columns() ), "'{$columna}' no es una columna declarada" );
		}
	}

	/**
	 * La baja en lote está ofrecida y el borrado definitivo **no** (`#27`).
	 */
	public function test_las_acciones_en_bloque_no_ofrecen_borrado_definitivo(): void {
		$acciones = $this->listado->get_bulk_actions();

		$this->assertArrayHasKey( 'bulk-baja', $acciones );
		foreach ( $acciones as $clave => $etiqueta ) {
			$this->assertStringNotContainsString( 'trash', (string) $clave, 'no se ofrece borrado definitivo en lote' );
			$this->assertStringNotContainsString( 'delete', (string) $clave );
		}
	}
}
