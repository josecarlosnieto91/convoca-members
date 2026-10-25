<?php
/**
 * Las renovaciones automáticas.
 *
 * `process_automatic_renewals()` **cobra dinero sin que nadie lo pida**: busca socios con pago recurrente, estado
 * activo y fecha de renovación vencida, y les pasa el cargo. Las guardas son lo que separa una renovación de un
 * cobro indebido:
 *
 *  - **sin plan, o con precio 0, no se cobra**. Un plan mal configurado no puede acabar en un cargo de 0 € ni en
 *    una excepción a mitad del bucle;
 *  - **sin Gateway activo no se cobra**, y la comprobación va **antes** de tocar `Payment_Handler`: esa clase vive
 *    en otro plugin y en un sitio con Members sin Gateway no existe. Antes iba después y el cron diario moría con
 *    «Class not found», que es el peor sitio donde morir: el resto del día se queda sin ejecutar;
 *  - en cada salida se **libera el bloqueo** del socio. Si no, esa ficha se queda sin poder renovarse para
 *    siempre.
 *
 * Excepción escrita: el camino en el que el cargo **se intenta** (con token, `retry_days`, contador de intentos y
 * caída a pago manual) necesita `Convoca\Gateway\Payment_Handler`, que no existe en esta suite. Cargarlo dejaría la
 * clase declarada para el resto de las pruebas —PHP no descarga clases— y hay ficheros que dependen de que **no**
 * esté. Queda declarado, no escondido.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Renovaciones automáticas.
 */
final class RenovacionesAutomaticasTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Cron.
	 *
	 * @var Cron_Manager
	 */
	private Cron_Manager $cron;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'  => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'   => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'col'    => $GLOBALS['_wp_stores']['col'] ?? null,
			'reads'  => $GLOBALS['_wp_stores']['col_reads'] ?? null,
			'opts'   => $GLOBALS['_wp_stores']['options'] ?? array(),
			'errlog' => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		// `col_reads` **acumula** entre pruebas: sin vaciarlo, la comprobación de la consulta encontraba la de una
		// prueba anterior y pasaba aunque la consulta de esta no tuviera lo que se buscaba.
		$GLOBALS['_wp_stores']['col_reads'] = array();
		// Un plan con precio de verdad: los de por defecto no sirven para probar un cargo.
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'socio'  => array(
				'label' => 'Socio',
				'price' => 30,
				'hours' => 0,
			),
			'gratis' => array(
				'label' => 'Gratis',
				'price' => 0,
				'hours' => 0,
			),
		);

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-autorenew-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->cron = ( new \ReflectionClass( Cron_Manager::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_stores']['options']   = $this->previo['opts'];

		foreach ( array( 'col' => 'col', 'col_reads' => 'reads' ) as $clave => $origen ) {
			if ( null === $this->previo[ $origen ] ) {
				unset( $GLOBALS['_wp_stores'][ $clave ] );
			} else {
				$GLOBALS['_wp_stores'][ $clave ] = $this->previo[ $origen ];
			}
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra un socio renovable.
	 *
	 * @param int   $id   ID.
	 * @param array $meta Metas.
	 * @return void
	 */
	private function socio( int $id, array $meta ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ]      = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia ' . $id,
			'post_date'   => '2025-01-01 00:00:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array_merge(
			array(
				'_convoca_pago_recurrente' => '1',
				'_convoca_estado_miembro'  => 'activo',
				'_convoca_fecha_renovacion' => '2020-01-01',
			),
			$meta
		);
	}

	/**
	 * Ejecuta la pasada, con los socios que devuelve la consulta.
	 *
	 * @param array $ids IDs que «encuentra» la base.
	 * @return void
	 */
	private function pasada( array $ids ): void {
		$GLOBALS['_wp_stores']['col'] = $ids;
		$m = new \ReflectionMethod( Cron_Manager::class, 'process_automatic_renewals' );
		$m->invoke( $this->cron );
	}

	/**
	 * El registro de la pasada.
	 *
	 * @return string
	 */
	private function registro(): string {
		$log = (string) ( $GLOBALS['_errlog_tmp'] ?? '' );

		return is_file( $log ) ? (string) file_get_contents( $log ) : '';
	}

	/**
	 * Sin socios que renueven, la pasada termina sin hacer nada.
	 */
	public function test_sin_socios_que_renueven_no_hace_nada(): void {
		$this->pasada( array() );

		$this->assertSame( '', $this->registro(), 'no se registró nada' );
	}

	/**
	 * **Sin Gateway activo no se cobra, y se dice por qué.**
	 *
	 * La comprobación va antes de tocar `Payment_Handler`, que vive en el plugin Gateway y aquí no existe: si
	 * estuviera detrás, esto no sería un aviso sino un «Class not found» que se lleva por delante el resto del cron.
	 */
	public function test_sin_gateway_no_se_cobra(): void {
		$this->socio( 500, array( '_convoca_plan' => 'socio' ) );

		$this->pasada( array( 500 ) );

		$this->assertStringContainsString( 'Gateway no disponible', $this->registro(), 'el motivo queda anotado' );
		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][500]['_convoca_last_auto_renewal'] ?? '' ),
			'no se marcó ninguna renovación'
		);
		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][500]['_convoca_autorenew_attempts'] ?? '' ),
			'ni se contó ningún intento de cobro'
		);
	}

	/**
	 * **Un plan sin precio no se cobra.**
	 *
	 * Un cargo de 0 € no cobra nada pero deja una renovación marcada, y el socio se queda un año más sin haber
	 * pagado. Se corta antes, con su aviso.
	 */
	public function test_un_plan_sin_precio_no_se_cobra(): void {
		$this->socio( 500, array( '_convoca_plan' => 'gratis' ) );

		$this->pasada( array( 500 ) );

		$this->assertStringContainsString( 'Plan no encontrado o importe 0', $this->registro() );
		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][500]['_convoca_last_auto_renewal'] ?? '' )
		);
	}

	/**
	 * Y un plan que no existe tampoco.
	 */
	public function test_un_plan_inexistente_no_se_cobra(): void {
		$this->socio( 500, array( '_convoca_plan' => 'plan-que-no-existe' ) );

		$this->pasada( array( 500 ) );

		$this->assertStringContainsString( 'Plan no encontrado o importe 0', $this->registro() );
		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][500]['_convoca_last_auto_renewal'] ?? '' )
		);
	}

	/**
	 * **Una ficha rota no impide atender a las demás.**
	 *
	 * El bucle es por socio: si el primero no tiene plan, el segundo tiene que renovarse igual. Un `return` donde va
	 * un `continue` dejaría a todos los siguientes sin renovar, en silencio.
	 */
	public function test_una_ficha_rota_no_para_el_resto(): void {
		$this->socio( 500, array( '_convoca_plan' => 'plan-que-no-existe' ) );
		$this->socio( 501, array( '_convoca_plan' => 'socio' ) );

		$this->pasada( array( 500, 501 ) );

		$registro = $this->registro();
		$this->assertStringContainsString( 'miembro #500', $registro, 'el roto se anota' );
		$this->assertStringContainsString(
			'#501',
			$registro,
			'el segundo se atiende igual (aquí también acaba en «sin gateway», pero se le mira)'
		);
	}

	/**
	 * **La consulta pide lo que tiene que pedir**: pago recurrente, estado activo y renovación vencida.
	 *
	 * Si se colara un socio sin pago recurrente, se le cobraría a quien no lo autorizó; y si la fecha no se compara
	 * con hoy, se renueva a todo el mundo cada día.
	 */
	public function test_la_consulta_filtra_por_recurrente_activo_y_fecha(): void {
		$this->pasada( array() );

		$lecturas = (string) json_encode( (array) ( $GLOBALS['_wp_stores']['col_reads'] ?? array() ) );

		// Se exige la **condición completa**, no solo el nombre de la meta: quitar `= '1'` o `= 'activo'` deja el
		// nombre en la consulta y la prueba pasaría sin filtrar nada.
		$this->assertStringContainsString( '_convoca_pago_recurrente', $lecturas, 'solo los que pagan por cargo' );
		$this->assertStringContainsString( "pm_rec.meta_value = '1'", $lecturas, 'y solo si lo tienen activado' );
		$this->assertStringContainsString( '_convoca_estado_miembro', $lecturas );
		$this->assertStringContainsString( "pm_est.meta_value = 'activo'", $lecturas, 'y solo los activos' );
		$this->assertStringContainsString( '_convoca_fecha_renovacion', $lecturas );
		$this->assertStringContainsString( '<= ', $lecturas, 'la fecha se compara, no solo se nombra' );
		$this->assertStringContainsString( wp_date( 'Y-m-d' ), $lecturas, 'comparando con la fecha de hoy, no con otra' );
	}
}
