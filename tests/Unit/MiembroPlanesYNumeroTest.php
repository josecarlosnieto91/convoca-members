<?php
/**
 * Los planes de socio, el enlace de WhatsApp y el número de socio.
 *
 * Tres cosas que el socio ve y que se rompen en silencio: el **plan** (si su clave o su etiqueta dejan de
 * resolver, sus horas no se evalúan nunca y su certificado no llega), el **enlace de WhatsApp** (un teléfono
 * sin prefijo abre un chat en otro país) y el **número de socio** (una secuencia que se repite da dos socios
 * con el mismo número).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CPT_Miembro;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Planes, contacto y secuencia de socio.
 */
final class MiembroPlanesYNumeroTest extends TestCase {

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
		foreach ( array( '_wp_stores', '_test_posts', '_wp_insert_id', '_sh_db' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
		);
		remove_all_filters( 'convoca_members_plans' );
	}

	/**
	 * Los planes por defecto traen lo que hace falta para cobrar y para contar horas.
	 */
	public function test_los_planes_por_defecto(): void {
		$planes = CPT_Miembro::get_default_plans();

		$this->assertArrayHasKey( 'bronze', $planes );
		$this->assertArrayHasKey( 'voluntariado', $planes, 'el plan de voluntariado siempre está' );
		foreach ( $planes as $clave => $plan ) {
			$this->assertArrayHasKey( 'label', $plan, $clave . ': sin etiqueta no se puede elegir' );
			$this->assertArrayHasKey( 'price', $plan );
			$this->assertArrayHasKey( 'hours', $plan );
			$this->assertArrayHasKey( 'payment_methods', $plan );
		}
	}

	/**
	 * Sin nada guardado se usan los de por defecto.
	 */
	public function test_sin_configuracion_se_usan_los_de_por_defecto(): void {
		$this->assertSame( CPT_Miembro::get_default_plans(), CPT_Miembro::get_plans() );
	}

	/**
	 * Un plan guardado a medias se rellena: sin precio, sin horas o sin formas de pago no se queda cojo.
	 */
	public function test_un_plan_a_medias_se_rellena(): void {
		update_option( 'convoca_members_plans', array( 'bronze' => array( 'label' => 'Bronce' ) ) );

		$planes = CPT_Miembro::get_plans();

		$this->assertSame( 30, $planes['bronze']['price'], 'el precio del plan conocido' );
		$this->assertSame( 15, $planes['bronze']['hours'] );
		$this->assertSame( array( 'bizum', 'tarjeta', 'transferencia' ), $planes['bronze']['payment_methods'] );
	}

	/**
	 * El plan de voluntariado se añade aunque no estuviera guardado: sin él sus horas no se evalúan.
	 */
	public function test_el_voluntariado_se_anade_siempre(): void {
		update_option( 'convoca_members_plans', array( 'bronze' => array( 'label' => 'Bronce' ) ) );

		$this->assertArrayHasKey( 'voluntariado', CPT_Miembro::get_plans() );
	}

	/**
	 * Los planes se pueden filtrar por el sitio.
	 */
	public function test_los_planes_se_filtran(): void {
		add_filter(
			'convoca_members_plans',
			static function ( $planes ) {
				$planes['socia_de_honor'] = array(
					'label'           => 'Honor',
					'price'           => 0,
					'hours'           => 0,
					'payment_methods' => array( 'ninguna' ),
				);

				return $planes;
			}
		);

		$this->assertArrayHasKey( 'socia_de_honor', CPT_Miembro::get_plans() );
	}

	/**
	 * El plan se busca por clave, por etiqueta y con acentos o mayúsculas.
	 */
	public function test_buscar_un_plan(): void {
		$this->assertSame( '🥉 Bronce', CPT_Miembro::get_plan( 'bronze' )['label'], 'por clave' );
		$this->assertSame( '🥉 Bronce', CPT_Miembro::get_plan( 'Bronce' )['label'], 'por etiqueta, sin el emoji' );
		$this->assertSame( '🥉 Bronce', CPT_Miembro::get_plan( '  BRONCE ' )['label'], 'con espacios y mayúsculas' );
		// La guarda `'' === $buscada` es redundante (ninguna clave ni etiqueta se normaliza a vacío): lo que
		// se afirma es el efecto, que una clave vacía no encuentra plan.
		$this->assertNull( CPT_Miembro::get_plan( '' ), 'sin clave no hay plan' );
		$this->assertNull( CPT_Miembro::get_plan( 'plan que no existe' ) );
	}

	/**
	 * La búsqueda no se rompe con los acentos: quien escribe «socia etica» encuentra «Socia Ética».
	 */
	public function test_buscar_un_plan_con_acentos(): void {
		add_filter(
			'convoca_members_plans',
			static function ( $planes ) {
				$planes['etica'] = array(
					'label'           => 'Socia Ética',
					'price'           => 0,
					'hours'           => 0,
					'payment_methods' => array( 'ninguna' ),
				);

				return $planes;
			}
		);

		$this->assertSame( 'Socia Ética', CPT_Miembro::get_plan( 'socia etica' )['label'], 'sin el acento' );
		$this->assertSame( 'Socia Ética', CPT_Miembro::get_plan( 'SOCIÁ ÉTICA' )['label'], 'y con él, en mayúsculas' );
	}

	/**
	 * El enlace de WhatsApp: sin teléfono o con el «no» puesto, no hay enlace.
	 */
	public function test_sin_telefono_no_hay_whatsapp(): void {
		$this->socio( 55 );

		$this->assertNull( CPT_Miembro::whatsapp_link( 55 ), 'sin teléfono' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono'] = '600 00 00 00';
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_whatsapp'] = 'no';

		$this->assertNull( CPT_Miembro::whatsapp_link( 55 ), 'dijo que no tiene WhatsApp' );
	}

	/**
	 * El teléfono se normaliza: espacios fuera y prefijo de España si falta.
	 */
	public function test_el_telefono_se_normaliza(): void {
		$this->socio( 55 );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono'] = '600 00 00 00';

		$this->assertSame( 'https://wa.me/34600000000', CPT_Miembro::whatsapp_link( 55 ) );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono'] = '+34 600-00-00-00';
		$this->assertSame( 'https://wa.me/34600000000', CPT_Miembro::whatsapp_link( 55 ), 'con prefijo y guiones' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono'] = '34600000000';
		$this->assertSame( 'https://wa.me/34600000000', CPT_Miembro::whatsapp_link( 55 ), 'ya venía con el 34' );
	}

	/**
	 * Con mensaje, se cambia el nombre y se codifica para la URL.
	 */
	public function test_el_mensaje_del_whatsapp(): void {
		$this->socio( 55 );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono'] = '600000000';

		$url = CPT_Miembro::whatsapp_link( 55, 'Hola {nombre}, te esperamos el sábado' );

		$this->assertStringContainsString( '?text=', $url );
		$this->assertStringContainsString( 'Hola%20Ada%20Lovelace%2C', $url, 'el nombre se sustituye y se codifica' );
	}

	/**
	 * El número de socio sale de la secuencia y deja la opción heredada al día.
	 */
	public function test_el_numero_de_socio(): void {
		$GLOBALS['_wp_insert_id'] = 1;
		$GLOBALS['_wp_stores']['max_id']['wp_convoca_member_sequence'] = 1;

		$primero = CPT_Miembro::get_next_member_number( 55 );
		$GLOBALS['_wp_insert_id'] = 2;
		$GLOBALS['_wp_stores']['max_id']['wp_convoca_member_sequence'] = 2;
		$segundo = CPT_Miembro::get_next_member_number( 56 );

		$this->assertSame( 1, $primero, 'el primero es el 1' );
		$this->assertSame( 2, $segundo, 'y no se repite' );
		$this->assertSame( 2, (int) get_option( 'convoca_last_member_number' ), 'la opción heredada queda al día' );
	}

	/**
	 * Si la tabla de la secuencia no se puede usar, se tira del contador de opciones (y no se queda sin número).
	 */
	public function test_el_numero_de_socio_tiene_respaldo(): void {
		$GLOBALS['_sh_db']['tabla'] = 'otra_cosa'; // la tabla de la secuencia no está
		$GLOBALS['_wp_insert_id']   = 0;

		$numero = CPT_Miembro::get_next_member_number( 55 );

		$this->assertGreaterThan( 0, $numero, 'siempre hay un número' );
	}

	/**
	 * Borrar la fila de la secuencia sólo toca a los socios (el tipo se comprueba).
	 */
	public function test_borrar_la_fila_de_la_secuencia(): void {
		$GLOBALS['_test_posts'][55] = (object) array(
			'ID'        => 55,
			'post_type' => 'page',
		);

		CPT_Miembro::borrar_fila_secuencia( 55 );
		$this->assertEmpty( $GLOBALS['_wp_stores']['db_deletes'] ?? array(), 'no es un socio: no se borra nada' );

		$GLOBALS['_test_posts'][55]->post_type = 'miembro';
		CPT_Miembro::borrar_fila_secuencia( 55 );

		$borrados = $GLOBALS['_wp_stores']['db_deletes'] ?? array();
		$this->assertCount( 1, $borrados, 'siendo socio, se borra su fila' );
		$this->assertStringContainsString( 'convoca_member_sequence', $borrados[0]['table'] );
		$this->assertSame( array( 'member_id' => 55 ), $borrados[0]['where'] );
	}

	/**
	 * Deja un socio en el arnés.
	 *
	 * @param int $id Socio.
	 */
	private function socio( int $id ): void {
		$GLOBALS['_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_nombre'] = 'Ada Lovelace';
	}
}
