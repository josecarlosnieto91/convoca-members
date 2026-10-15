<?php
/**
 * Un editor no puede ver ni borrar el censo de socios (issue #15).
 *
 * Lo medido por la auditoría: con rol `editor` —sin ninguna capacidad de este plugin— `edit.php?post_type=miembro`
 * respondía 200 con el listado de socios por nombre y apellidos, y los enlaces de papelera funcionaban. La causa
 * era que `miembro`, `registro_hora` y `proyecto` se registraban sin tipo de capacidad propio y WordPress
 * traducía sus capacidades a las primitivas de `post`, que el editor tiene por defecto.
 *
 * Lo que esta prueba puede demostrar: que cada tipo declara su **propio** par de capacidades, que las trece
 * capacidades derivadas se conceden y que ya no hay ningún tipo colgando de `post`. Lo que **no** puede —el
 * arnés no es WordPress— es resolver `user_can()`: eso se mide en la demo, con un editor de verdad y un
 * administrador de verdad, y está en la evidencia del issue. Aquí se vigila el mecanismo, que es lo que se
 * rompe en silencio.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CapacidadesSociosTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Los pares singular/plural declarados en los tipos del plugin, leídos del código.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	private function familias(): array {
		$familias = array();
		foreach ( array( 'includes/CPT_Miembro.php', 'includes/CPT_Proyecto.php', 'includes/CPT_Registro_Hora.php' ) as $ruta ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );
			if ( preg_match( "/'capability_type'\s*=>\s*array\(\s*'(convoca_\w+)',\s*'(\w+)'/", $codigo, $m ) ) {
				$familias[] = array( $m[1], $m[2] );
			}
		}

		return $familias;
	}

	/**
	 * Las trece capacidades que WordPress compone de un par singular/plural.
	 *
	 * @param array{0:string,1:string} $par Par singular/plural.
	 * @return array<int, string>
	 */
	private function derivadas( array $par ): array {
		list( $singular, $plural ) = $par;

		return array(
			"edit_$singular",
			"read_$singular",
			"delete_$singular",
			"edit_$plural",
			"edit_others_$plural",
			"delete_$plural",
			"publish_$plural",
			"read_private_$plural",
			"delete_private_$plural",
			"delete_published_$plural",
			"delete_others_$plural",
			"edit_private_$plural",
			"edit_published_$plural",
		);
	}

	public function test_los_tres_tipos_tienen_tipo_de_capacidad_propio(): void {
		$familias = $this->familias();
		$this->assertCount( 3, $familias, 'Socios, horas y proyectos: cada uno con su par propio' );
		foreach ( $familias as $par ) {
			$this->assertStringStartsWith( 'convoca_', $par[0] );
			$this->assertStringStartsWith( 'convoca_', $par[1] );
		}
	}

	public function test_ningun_tipo_cuelga_ya_de_las_capacidades_de_post(): void {
		foreach ( array( 'includes/CPT_Miembro.php', 'includes/CPT_Proyecto.php', 'includes/CPT_Registro_Hora.php' ) as $ruta ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );
			$this->assertStringNotContainsString( "'capability_type'     => 'post'", $codigo, "$ruta: `post` es lo que hacía que un editor pudiera listar y borrar socios" );
		}
	}

	public function test_se_conceden_las_trece_de_cada_familia(): void {
		$bootstrap = (string) file_get_contents( $this->raiz() . '/convoca-members.php' );
		$roles     = (string) file_get_contents( $this->raiz() . '/includes/Roles_Miembro.php' );

		// El método que las compone y la concesión, en la clase...
		$this->assertStringContainsString( 'public static function capacidades_de_los_tipos()', $roles );
		$this->assertStringContainsString( 'foreach ( self::capacidades_de_los_tipos() as $cap_propia )', $roles, 'Las derivadas tienen que concederse, no solo declararse' );
		// ...y esa clase enganchada a `init`: si la concesión vive en la activación, una instalación que se
		// actualiza no la recorre (medido: el administrador se quedaba fuera con un 403).
		$this->assertStringContainsString( "add_action( 'init', array( \\Convoca\\Members\\Roles_Miembro::class, 'crear_roles' ) )", $bootstrap, 'En `init`, no en la activación' );
		$this->assertStringNotContainsString( 'as $cap_propia', $bootstrap, 'La concesión no vive repartida por el arranque' );

		// Y el bloque que concede no menciona a `editor`: las capacidades son solo de administración.
		$bloque = substr( $roles, (int) strpos( $roles, 'as $cap_propia' ), 140 );
		$this->assertStringNotContainsString( 'editor', $bloque );

		$plantillas = array(
			'edit_$singular',
			'read_$singular',
			'delete_$singular',
			'edit_$plural',
			'edit_others_$plural',
			'delete_$plural',
			'publish_$plural',
			'read_private_$plural',
			'delete_private_$plural',
			'delete_published_$plural',
			'delete_others_$plural',
			'edit_private_$plural',
			'edit_published_$plural',
		);
		foreach ( $plantillas as $plantilla ) {
			$this->assertStringContainsString( '"' . $plantilla . '"', $roles, "El método no compone $plantilla" );
		}

		foreach ( $this->familias() as $par ) {
			// Y que derive del mismo par que declara el tipo, no de otro parecido.
			$this->assertStringContainsString( "'{$par[0]}', '{$par[1]}'", $roles, 'Tiene que derivar del mismo par que declara el tipo' );
		}
	}
}
