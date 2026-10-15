<?php
/**
 * La baja de un socio no puede ser un borrado (issue #27).
 *
 * Lo que había: el enlace «Eliminar» del listado llamaba a `wp_trash_post()` sobre un tipo que **no admite
 * papelera**, así que no hacía nada, volvía al listado y no decía por qué. Y la única baja que funcionaba de
 * verdad era un borrado físico (`wp_delete_post( $id, true )`), que se llevaba ficha, cuotas, horas e
 * inscripciones de un clic, sin vuelta atrás y dejando el histórico apuntando a un socio que ya no existe.
 *
 * Lo que tiene que pasar: dar de baja es un cambio de estado `baja` (la ficha se conserva, el número de socio
 * no se reutiliza y se puede reactivar), y el borrado definitivo existe aparte, con un aviso que dice qué se
 * pierde. Los botones de papelera de este tipo desaparecen mientras el tipo no la admita.
 *
 * Prueba de contrato sobre el código: cruza los enlaces que publica el listado con los manejadores que
 * registra el editor, y vigila que el borrado definitivo sea la única vía que llama a `wp_delete_post`.
 * Cada aserción tiene su negativo probado a mano (volviendo a poner el `wp_trash_post` o el prefijo viejo,
 * esta prueba cae).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class BajaLogicaTest extends TestCase {

	private function fuente( string $relativo ): string {
		$ruta = dirname( __DIR__, 2 ) . '/' . $relativo;
		$this->assertFileExists( $ruta, "No encuentro $relativo" );
		return file_get_contents( $ruta );
	}

	/**
	 * El cuerpo de una función, sin la siguiente y sin comentarios.
	 *
	 * @param string $relativo    Fichero.
	 * @param string $nombre      Nombre de la función.
	 * @return string
	 */
	private function cuerpo_de( string $relativo, string $nombre ): string {
		$codigo = $this->codigo( $relativo );
		$ini    = strpos( $codigo, "function $nombre" );
		$this->assertNotFalse( $ini, "No existe la función $nombre en $relativo" );
		$resto = substr( $codigo, $ini );
		$fin   = strpos( $resto, "
	public function" );
		return false === $fin ? $resto : substr( $resto, 0, $fin );
	}

	/**
	 * El código sin comentarios: si no, la prueba salta con la prosa que documenta el defecto.
	 *
	 * @param string $relativo Ruta dentro del plugin.
	 * @return string
	 */
	private function codigo( string $relativo ): string {
		return preg_replace( '#/\*.*?\*/#s', '', $this->fuente( $relativo ) );
	}

	/** Los dos enlaces que ofrece la fila existen como manejadores. */
	public function test_los_enlaces_de_fila_tienen_manejador(): void {
		$listado = $this->fuente( 'admin/class-admin-list.php' );
		$editor  = $this->fuente( 'admin/class-admin-member-editor.php' );

		foreach ( array( 'convoca_baja_member', 'convoca_delete_member' ) as $accion ) {
			$this->assertStringContainsString( "action=$accion", $listado, "El listado no ofrece $accion" );
			$this->assertStringContainsString(
				"add_action( 'admin_post_$accion'",
				$editor,
				"$accion no tiene manejador: sería un botón muerto"
			);
		}
	}

	/** Dar de baja cambia el estado; no borra. */
	public function test_baja_cambia_estado_y_no_borra(): void {
		$editor = $this->fuente( 'admin/class-admin-member-editor.php' );
		$cuerpo = $this->cuerpo_de( 'admin/class-admin-member-editor.php', 'handle_baja' );

		$this->assertStringContainsString( "Estados::change( \$post_id, 'baja'", $cuerpo );
		$this->assertStringNotContainsString( 'wp_delete_post', $cuerpo, 'La baja no puede borrar la ficha' );
	}

	/** El borrado definitivo es explícito y de verdad. */
	public function test_el_borrado_definitivo_borra_de_verdad(): void {
		$editor = $this->fuente( 'admin/class-admin-member-editor.php' );
		$cuerpo = $this->cuerpo_de( 'admin/class-admin-member-editor.php', 'handle_delete' );

		$this->assertStringContainsString( 'wp_delete_post( $post_id, true )', $cuerpo );
		$this->assertStringContainsString( 'delete_post', $cuerpo, 'El borrado definitivo necesita su permiso' );
	}

	/** Nadie manda este tipo a la papelera: no la admite. */
	public function test_nadie_usa_la_papelera_en_este_tipo(): void {
		foreach ( array( 'admin/class-admin-member-editor.php', 'admin/class-admin-list.php' ) as $f ) {
			$this->assertStringNotContainsString(
				'wp_trash_post',
				$this->codigo( $f ),
				"$f vuelve a mandar fichas a una papelera que este tipo no admite"
			);
		}
	}

	/** El lote da de baja, no borra, y la papelera no se ofrece. */
	public function test_lote_y_papelera(): void {
		$listado = $this->fuente( 'admin/class-admin-list.php' );

		$this->assertStringContainsString( "'bulk-baja'", $listado );
		$this->assertStringNotContainsString( "'bulk-delete'", $listado, 'Sigue ofreciendo borrado en lote' );
		$this->assertStringContainsString( "'bulk_actions-edit-miembro'", $listado, 'La papelera cruda no se quita' );
	}
}
