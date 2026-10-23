<?php
/**
 * El panel de voluntariado visto desde la API: lo que la persona decide, y nada más.
 *
 * Tres reglas se prueban aquí, y las tres están escritas con nombres y apellidos en el código porque se
 * decidieron con la junta:
 *
 * - un cuestionario **en blanco** no cierra la entrevista (members#52): antes daba las gracias y pasaba a la
 *   persona a activa sin haber contestado nada;
 * - pedir la baja **no** da de baja: avisa a la junta, que es quien la confirma;
 * - el acuerdo queda guardado **con su fecha**.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Circuito_Voluntariado;
use Convoca\Members\Rest_API;
use Convoca\Members\Roles_Miembro;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Circuito_Voluntariado.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Panel de voluntariado por la API.
 */
final class RestApiVoluntariadoPanelTest extends TestCase {

	/**
	 * La API.
	 *
	 * @var Rest_API
	 */
	private Rest_API $api;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$this->api = new Rest_API();
		$this->con_sesion( 55 );
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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $_COOKIE['convoca_member_session'] );

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
	 * Abre sesión y siembra la ficha.
	 *
	 * @param int $id Socio.
	 */
	private function con_sesion( int $id ): void {
		$testigo = 'testigo-de-prueba';

		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 0,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$_COOKIE['convoca_member_session'] = $testigo;

		$ficha                                     = (object) array(
			'ID'           => $id,
			'post_type'    => 'miembro',
			'post_status'  => 'publish',
			'post_title'   => 'Ada Lovelace',
			'post_date'    => '2026-01-01 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]             = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_email' => 'ada@example.test',
			'_convoca_dni'   => '00000000T',
		);
	}

	/**
	 * Configura el cuestionario de la entrevista.
	 *
	 * @param array $preguntas Preguntas.
	 */
	private function cuestionario( array $preguntas ): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array(
			'entrevista_preguntas' => implode( "\n", $preguntas ),
		);
	}

	/**
	 * Envía una acción del panel.
	 *
	 * @param string $accion Acción.
	 * @param array  $params Parámetros.
	 * @return array
	 */
	private function enviar( string $accion, array $params = array() ): array {
		$req = new \WP_REST_Request();
		$req->set_param( 'accion', $accion );
		foreach ( $params as $clave => $valor ) {
			$req->set_param( $clave, $valor );
		}

		return (array) $this->api->submit_voluntariado( $req )->get_data();
	}

	/**
	 * Un cuestionario contestado cierra la entrevista y da las gracias.
	 */
	public function test_el_cuestionario_contestado(): void {
		$this->cuestionario( array( '¿Por qué quieres colaborar?', '¿De qué tiempo dispones?' ) );

		$datos = $this->enviar(
			'cuestionario',
			array(
				'respuestas' => array( 'Porque me apetece ayudar', 'Los sábados' ),
			)
		);

		$this->assertSame( '¡Gracias! Ya has enviado el cuestionario.', $datos['message'] );
		$this->assertSame(
			'1',
			(string) get_post_meta( 55, Roles_Miembro::META_VOLUNTARIO, true ),
			'contestado el cuestionario, la persona es voluntaria'
		);
		$guardadas = (array) get_post_meta( 55, Circuito_Voluntariado::META_RESPUESTAS, true );
		$this->assertSame( 'Porque me apetece ayudar', $guardadas['¿Por qué quieres colaborar?'] ?? '', 'con la pregunta, no con el número' );
	}

	/**
	 * Uno **en blanco** no cierra nada (members#52): se dice y no se toca la ficha.
	 */
	public function test_el_cuestionario_en_blanco_no_cierra(): void {
		$this->cuestionario( array( '¿Por qué quieres colaborar?' ) );

		$datos = $this->enviar( 'cuestionario', array( 'respuestas' => array( '   ', '' ) ) );

		$this->assertSame( 'Te falta contestar alguna pregunta: el cuestionario está en blanco.', $datos['message'] );
		$this->assertSame(
			'',
			(string) get_post_meta( 55, Roles_Miembro::META_VOLUNTARIO, true ),
			'un envío vacío no convierte a nadie en voluntario'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( 55, Circuito_Voluntariado::META_RESPUESTAS, true ),
			'ni guarda respuestas que no existen'
		);
	}

	/**
	 * El acuerdo queda guardado **con su fecha**: es lo que se puede demostrar después.
	 */
	public function test_el_acuerdo_queda_fechado(): void {
		$datos = $this->enviar( 'acuerdo' );

		$this->assertSame( 'Acuerdo aceptado: queda guardado con su fecha.', $datos['message'] );
		$acuerdo = (array) get_post_meta( 55, Circuito_Voluntariado::META_ACUERDO, true );
		$this->assertNotEmpty( $acuerdo['fecha'] ?? '', 'la fecha es lo que se puede demostrar después' );
	}

	/**
	 * Pedir la baja **no** da de baja: avisa a la junta, que es quien la confirma.
	 */
	public function test_pedir_la_baja_no_da_de_baja(): void {
		$GLOBALS['_wp_actions'] = array();

		$datos = $this->enviar( 'baja', array( 'motivo' => 'Me mudo fuera' ) );

		$this->assertStringContainsString( 'junta', (string) $datos['message'] );
		$this->assertSame(
			'',
			(string) get_post_meta( 55, '_convoca_estado_miembro', true ),
			'la ficha no cambia de estado: la baja la confirma la junta'
		);
		// La marca guarda `fecha|usuario|motivo` de una vez: se afirma que **está pedida** y que el motivo viaja.
		$pedida = (string) get_post_meta( 55, Circuito_Voluntariado::META_BAJA_SOLICITADA, true );
		$this->assertNotSame( '', $pedida, 'queda pedida' );
		$this->assertStringContainsString( 'Me mudo fuera', $pedida, 'con el motivo que escribió' );
	}

	/**
	 * La baja desde el panel de socio sí anota el estado `baja_solicitada` y avisa.
	 */
	public function test_la_baja_del_panel_anota_y_avisa(): void {
		$GLOBALS['_wp_actions'] = array();

		$datos = (array) $this->api->unsubscribe_request( new \WP_REST_Request() )->get_data();

		$this->assertTrue( $datos['success'] );
		$this->assertSame(
			'baja_solicitada',
			(string) get_post_meta( 55, '_convoca_estado_miembro', true ),
			'el estado queda en baja **solicitada**, no en baja'
		);
		$this->assertContains(
			'convoca_members_unsubscribe_request',
			array_column( (array) $GLOBALS['_wp_actions'], 'hook' ),
			'y se avisa para que salga el correo'
		);
	}

	/**
	 * El bloque del circuito se sirve para pintarlo en el panel.
	 */
	public function test_el_bloque_del_circuito(): void {
		// Sólo se pinta a quien está (o estuvo) en el circuito: a una socia que nunca lo pidió no se le pone un
		// botón para aceptar un acuerdo que no le toca (members#51).
		$GLOBALS['_wp_stores']['post_meta'][55][ Roles_Miembro::META_VOLUNTARIO ] = '1';

		$datos = (array) $this->api->get_voluntariado()->get_data();

		$this->assertArrayHasKey( 'html', $datos );
		$this->assertNotSame( '', trim( $datos['html'] ), 'algo se pinta' );
	}

	/**
	 * A quien nunca pidió ser voluntaria **no** se le pinta el bloque: ni siquiera un botón para aceptar un
	 * acuerdo que no le toca (members#51).
	 */
	public function test_el_bloque_no_se_pinta_a_quien_no_esta_en_el_circuito(): void {
		$datos = (array) $this->api->get_voluntariado()->get_data();

		$this->assertSame( '', trim( (string) $datos['html'] ), 'nada que pintar para quien no está en el circuito' );
	}

	/**
	 * Una acción que no existe no rompe: contesta sin mensaje y devuelve el bloque.
	 */
	public function test_una_accion_desconocida_no_rompe(): void {
		$datos = $this->enviar( 'inventada' );

		$this->assertSame( '', (string) $datos['message'], 'sin mensaje porque no hay nada que decir' );
		$this->assertArrayHasKey( 'html', $datos, 'y el panel se vuelve a pintar' );
	}
}
