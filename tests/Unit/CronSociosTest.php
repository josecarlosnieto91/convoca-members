<?php
/**
 * El cron de socios: el cerrojo, los recordatorios escalados y las caducidades.
 *
 * Un cron que se ejecuta dos veces manda el correo dos veces; uno que no suelta el cerrojo no vuelve a
 * ejecutarse nunca; y uno que no escala deja a la asociación cobrando a mano para siempre. Nada de eso da error:
 * simplemente pasa, o no pasa, y nadie se entera hasta que un socio se queja.
 *
 * Se prueba por el efecto observable: **qué correos salen** y qué queda apuntado en la ficha, no que el método
 * «no reviente».
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';

/**
 * Cron diario y semanal.
 */
final class CronSociosTest extends TestCase {

	/**
	 * Gestor del cron.
	 *
	 * @var Cron_Manager
	 */
	private Cron_Manager $cron;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		// Sin plantillas no se manda nada: `send()` sale por ahí antes de mirar el socio.
		\Convoca\Members\Email_Manager::install_defaults();
		$this->cron = new Cron_Manager();
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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_actions'] );

		// El registro guarda estado **estático** entre pruebas (profundidad de anidado y marcas de tiempo). Si
		// se queda a medias, las pruebas siguientes creen estar dentro de un grupo y dejan de apuntar sus
		// avisos: es exactamente lo que pasaba con las de inicio de sesión. Se devuelve a cero.
		$reflejo = new \ReflectionClass( \Convoca\Core\Logger::class );
		foreach ( array( 'log_depth' => 0, 'log_timestamps' => array() ) as $propiedad => $valor ) {
			if ( $reflejo->hasProperty( $propiedad ) ) {
				$p = $reflejo->getProperty( $propiedad );
				$p->setValue( null, $valor );
			}
		}

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'locks'      => array(),
			'posts'      => array(),
		);
		$GLOBALS['_test_posts'] = array();
	}

	/**
	 * Invoca un método privado del cron.
	 *
	 * @param string $metodo Método.
	 */
	private function privado( string $metodo ): void {
		$reflejo = new \ReflectionMethod( Cron_Manager::class, $metodo );
		$reflejo->invoke( $this->cron );
	}

	/**
	 * Un socio pendiente de pago, con su antigüedad y lo último que se le mandó.
	 *
	 * @param int    $id      Socio.
	 * @param int    $dias    Días desde que se le reclamó el pago.
	 * @param string $enviado Marca de último recordatorio («primero», «segundo», «ultimo» o vacío).
	 */
	private function pendiente( int $id, int $dias, string $enviado = '' ): void {
		$socio = (object) array(
			'ID'            => $id,
			'post_type'     => 'miembro',
			'post_status'   => 'publish',
			'post_title'    => 'Socia ' . $id,
			'post_modified' => gmdate( 'Y-m-d H:i:s' ),
		);
		$GLOBALS['_test_posts'][ $id ]             = $socio;
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $socio;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_estado_miembro'        => 'pendiente_pago',
			'_convoca_fecha_pendiente_pago'  => gmdate( 'Y-m-d H:i:s', time() - ( $dias * DAY_IN_SECONDS ) ),
			'_convoca_pago_id'               => 700 + $id,
			'_convoca_email'                 => 'socia' . $id . '@example.test',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_last_reminder'] = $enviado;
	}

	/**
	 * Correos que han salido.
	 *
	 * @return array<int, array>
	 */
	private function correos(): array {
		return $GLOBALS['_wp_stores']['emails'] ?? array();
	}

	/**
	 * Con el cerrojo ocupado no se hace nada: ni se busca a quién avisar.
	 */
	public function test_el_cerrojo_ocupado_no_deja_correr(): void {
		$this->pendiente( 55, 30 );
		// Cerrojo ocupado por otra pasada: vence dentro de una hora.
		$GLOBALS['_wp_stores']['locks']['convoca_lock_convoca_members_daily_lock'] = time() + 3600;

		$this->cron->run_daily();

		$this->assertSame( array(), $this->correos(), 'no sale ningún correo' );
		$this->assertSame(
			'',
			$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_last_reminder'],
			'y la ficha no se toca: no se llegó a mirar'
		);
	}

	/**
	 * Y se suelta al terminar, para que la siguiente pasada pueda entrar.
	 */
	public function test_el_cerrojo_se_suelta_al_terminar(): void {
		$this->cron->run_daily();

		$this->assertArrayNotHasKey(
			'convoca_lock_convoca_members_daily_lock',
			$GLOBALS['_wp_stores']['locks'],
			'el cerrojo quedó suelto: la siguiente pasada podrá entrar'
		);
	}

	/**
	 * Escalado de los recordatorios: a los 3 días el primero, a los 7 el segundo, a los 14 el último.
	 */
	public function test_los_recordatorios_escalan(): void {
		$casos = array(
			3  => array( 'recordatorio_pago', 'primero' ),
			7  => array( 'pago_pendiente_2', 'segundo' ),
			14 => array( 'pago_pendiente_ultimo', 'ultimo' ),
		);

		foreach ( $casos as $dias => $esperado ) {
			$this->limpiar();
			\Convoca\Members\Email_Manager::install_defaults(); // limpiar se llevó las plantillas
			$this->pendiente( 55, $dias );

			$this->privado( 'process_pending_payments' );

			$this->assertCount( 1, $this->correos(), 'un correo a los ' . $dias . ' días' );
			$this->assertSame(
				$esperado[1],
				$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_last_reminder'],
				'a los ' . $dias . ' días se le marca el recordatorio ' . $esperado[1]
			);
			$this->assertStringContainsString(
				'http',
				$this->correos()[0]['message'],
				'el correo lleva el enlace de pago: ' . $esperado[0]
			);
		}
	}

	/**
	 * A los dos días no se reclama nada todavía: el primer aviso es a los tres.
	 */
	public function test_antes_de_tres_dias_no_se_reclama(): void {
		$this->pendiente( 55, 2 );

		$this->privado( 'process_pending_payments' );

		$this->assertSame( array(), $this->correos() );
		$this->assertSame( '', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_last_reminder'] );
	}

	/**
	 * No se repite el mismo escalón: quien ya recibió el último no vuelve a recibirlo.
	 */
	public function test_no_se_repite_el_escalon(): void {
		$this->pendiente( 55, 20, 'ultimo' );

		$this->privado( 'process_pending_payments' );

		$this->assertSame( array(), $this->correos(), 'ya lo tenía: no se le insiste' );
	}

	/**
	 * Y el segundo tampoco se salta al último por mucho tiempo que pase: se sube escalón a escalón.
	 */
	public function test_se_sube_escalon_a_escalon(): void {
		$this->pendiente( 55, 10, 'primero' );

		$this->privado( 'process_pending_payments' );

		$this->assertCount( 1, $this->correos() );
		$this->assertSame(
			'segundo',
			$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_last_reminder'],
			'de «primero» se pasa a «segundo», no al último'
		);
	}

	/**
	 * Quien paga mientras corre el cron no recibe el reclamo: se vuelve a mirar el estado dentro del cerrojo.
	 */
	public function test_quien_paga_no_recibe_el_reclamo(): void {
		$this->pendiente( 55, 10, 'primero' );

		// La consulta lo encuentra pendiente (así lo ve `get_posts`), pero cuando el cron vuelve a mirar el
		// estado dentro del cerrojo ya ha pagado: es la carrera de verdad, no un cambio hecho antes de tiempo.
		$GLOBALS['_wp_stores']['post_meta_once'][55]['_convoca_estado_miembro'] = 'activo';

		$this->privado( 'process_pending_payments' );

		$this->assertSame( array(), $this->correos(), 'no se le reclama a quien ya pagó' );
	}

	/**
	 * Una ficha sin fecha de reclamo se arregla sola: se apunta la de modificación y se avisa en el registro.
	 */
	public function test_sin_fecha_de_reclamo_se_toma_la_de_modificacion(): void {
		$this->pendiente( 55, 10 );
		unset( $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_fecha_pendiente_pago'] );

		$this->privado( 'process_pending_payments' );

		$this->assertArrayHasKey(
			'_convoca_fecha_pendiente_pago',
			$GLOBALS['_wp_stores']['post_meta'][55],
			'la fecha se rellena para no reclamar en bucle'
		);
	}

	/**
	 * Las caducidades avisan a quien corresponda: cada socio vencido se revisa y se anuncia el hecho.
	 */
	public function test_las_caducidades_se_procesan(): void {
		$vencido = (object) array(
			'ID'          => 55,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia 55',
		);
		$GLOBALS['_test_posts'][55]         = $vencido;
		$GLOBALS['_wp_stores']['posts'][55] = $vencido;
		$GLOBALS['_wp_stores']['post_meta'][55] = array(
			'_convoca_estado_miembro'   => 'activo',
			'_convoca_fecha_renovacion' => gmdate( 'Y-m-d', time() - ( 60 * DAY_IN_SECONDS ) ),
			'_convoca_email'            => 'socia55@example.test',
		);
		$GLOBALS['_wp_stores']['col'] = array( 55 ); // la consulta devuelve este socio vencido

		$this->privado( 'process_expirations' );

		$avisos = array_values(
			array_filter(
				$GLOBALS['_wp_actions'] ?? array(),
				static fn( $a ) => 'convoca_members_membership_expired' === $a['hook']
			)
		);

		$this->assertCount( 1, $avisos, 'se dispara el aviso de caducidad' );
		$this->assertSame( 55, $avisos[0]['args'][0], 'con el socio vencido' );
		$this->assertNotSame(
			'activo',
			$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_estado_miembro'],
			'60 días vencido (más de la gracia): la ficha deja de estar activa'
		);
	}

	/**
	 * El semanal también respeta su cerrojo: con él cogido, no hay resumen.
	 */
	public function test_el_semanal_respeta_su_cerrojo(): void {
		$GLOBALS['_wp_stores']['locks']['convoca_lock_convoca_members_weekly_lock'] = time() + 3600;

		$this->cron->run_weekly();

		$this->assertSame( array(), $this->correos(), 'sin cerrojo no hay resumen' );
		$this->assertSame(
			time() + 3600,
			$GLOBALS['_wp_stores']['locks']['convoca_lock_convoca_members_weekly_lock'],
			'el cerrojo ajeno se queda como estaba (no se suelta lo que no se cogió)'
		);
	}
}
