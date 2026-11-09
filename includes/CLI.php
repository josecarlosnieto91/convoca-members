<?php
/**
 * Comandos WP-CLI de Convoca Members.
 *
 * @package    Convoca\Members
 * @subpackage Includes
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migración del cifrado de datos personales.
 */
final class CLI {

	/**
	 * Nombre de la tabla de copia previa.
	 */
	private const TABLA_COPIA = 'convoca_cifrado_previo';

	/**
	 * Registra los comandos.
	 *
	 * @return void
	 */
	public static function registrar(): void {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'convoca-members migrar-cifrado', array( self::class, 'migrar_cifrado' ) );
	}

	/**
	 * Cifra los campos sensibles que siguen en claro.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Solo cuenta y muestra una muestra; no escribe nada.
	 *
	 * [--limit=<n>]
	 * : Máximo de filas por tabla.
	 *
	 * ## EXAMPLES
	 *
	 *     wp convoca-members migrar-cifrado --dry-run
	 *     wp convoca-members migrar-cifrado
	 *
	 * @param array<int,string>    $args       Argumentos posicionales.
	 * @param array<string,mixed>  $assoc_args Opciones.
	 * @return void
	 */
	public static function migrar_cifrado( array $args, array $assoc_args ): void {
		if ( ! self::hay_llave() ) {
			\WP_CLI::error( 'Falta CONVOCA_ENCRYPTION_KEY válida en wp-config.php o no hay motor de cifrado.' );
		}

		$limite    = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 0;
		$claves    = self::claves();
		$post_meta = self::filas( 'postmeta', $claves, $limite );
		$user_meta = self::filas( 'usermeta', $claves, $limite );

		if ( 0 === count( $post_meta ) + count( $user_meta ) ) {
			\WP_CLI::success( 'No hay campos sensibles en claro. Nada que migrar.' );
			return;
		}

		if ( isset( $assoc_args['dry-run'] ) ) {
			self::informar( $post_meta, $user_meta );
			return;
		}

		self::ejecutar( $post_meta, $user_meta );
	}

	/**
	 * ¿Hay llave y motor de cifrado?
	 *
	 * @return bool
	 */
	private static function hay_llave(): bool {
		return class_exists( '\Convoca\Core\Cifrado' ) && \Convoca\Core\Cifrado::disponible();
	}

	/**
	 * Claves de meta que se cifran.
	 *
	 * @return string[]
	 */
	private static function claves(): array {
		return array_map(
			static function ( string $campo ): string {
				return '_convoca_' . $campo;
			},
			Datos_Socio::CAMPOS_CIFRADOS
		);
	}

	/**
	 * Cuenta y muestra una muestra sin valores.
	 *
	 * @param array<int,array<string,mixed>> $post_meta Postmeta.
	 * @param array<int,array<string,mixed>> $user_meta Usermeta.
	 * @return void
	 */
	private static function informar( array $post_meta, array $user_meta ): void {
		\WP_CLI::log( sprintf( 'Postmeta: %d filas; usermeta: %d filas.', count( $post_meta ), count( $user_meta ) ) );

		$muestra = array_map(
			static function ( array $fila ): array {
				return array(
					'id'       => $fila['id'],
					'objeto'   => $fila['objeto'],
					'meta_key' => $fila['meta_key'],
				);
			},
			array_slice( $post_meta, 0, 3 )
		);

		\WP_CLI::log( 'Muestra: ' . wp_json_encode( $muestra ) );
		\WP_CLI::success( 'Dry-run: no se ha escrito nada.' );
	}

	/**
	 * Copia, cifra y resume.
	 *
	 * @param array<int,array<string,mixed>> $post_meta Postmeta.
	 * @param array<int,array<string,mixed>> $user_meta Usermeta.
	 * @return void
	 */
	private static function ejecutar( array $post_meta, array $user_meta ): void {
		global $wpdb;

		self::crear_copia( $wpdb );
		self::copiar( $wpdb, 'postmeta', $post_meta );
		self::copiar( $wpdb, 'usermeta', $user_meta );

		$migradas = 0;
		$errores  = 0;

		foreach ( $post_meta as $fila ) {
			self::cifrar_fila( $wpdb, 'postmeta', $fila ) ? ++$migradas : ++$errores;
		}

		foreach ( $user_meta as $fila ) {
			self::cifrar_fila( $wpdb, 'usermeta', $fila ) ? ++$migradas : ++$errores;
		}

		\WP_CLI::log( sprintf( 'Migradas: %d. Errores: %d.', $migradas, $errores ) );
		\WP_CLI::log( 'Copia previa en ' . $wpdb->prefix . self::TABLA_COPIA . ' (bórrala cuando verifiques).' );

		if ( $errores > 0 ) {
			\WP_CLI::error( 'La migración terminó con errores; revisa el log antes de borrar la copia.' );
		}

		\WP_CLI::success( 'Cifrado migrado.' );
	}

	/**
	 * Filas pendientes de una tabla de metas.
	 *
	 * @param string   $tipo     postmeta|usermeta.
	 * @param string[] $claves   Claves sensibles.
	 * @param int      $limite   Límite; 0 = sin límite.
	 * @return array<int,array<string,mixed>>
	 */
	private static function filas( string $tipo, array $claves, int $limite ): array {
		global $wpdb;

		if ( 'postmeta' === $tipo ) {
			$tabla  = $wpdb->postmeta;
			$id     = 'meta_id';
			$objeto = 'post_id';
		} else {
			$tabla  = $wpdb->usermeta;
			$id     = 'umeta_id';
			$objeto = 'user_id';
		}

		$marcas = implode( ',', array_fill( 0, count( $claves ), '%s' ) );
		$sql    = "SELECT {$id} AS id, {$objeto} AS objeto, meta_key, meta_value
			FROM {$tabla}
			WHERE meta_key IN ({$marcas})
			  AND meta_value <> ''
			  AND meta_value NOT LIKE 'enc:v1:%%'
			ORDER BY {$id} ASC";

		$args = $claves;

		if ( $limite > 0 ) {
			$sql   .= ' LIMIT %d';
			$args[] = $limite;
		}

		$filas = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- la lista de marcas se construye con %s.

		return is_array( $filas ) ? $filas : array();
	}

	/**
	 * Crea la tabla de copia si no existe.
	 *
	 * @param \wpdb $wpdb Base de datos.
	 * @return void
	 */
	private static function crear_copia( $wpdb ): void {
		$tabla = $wpdb->prefix . self::TABLA_COPIA;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla interno.
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$tabla} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				meta_type VARCHAR(10) NOT NULL,
				meta_id BIGINT UNSIGNED NOT NULL,
				objeto BIGINT UNSIGNED NOT NULL,
				meta_key VARCHAR(255) NOT NULL,
				meta_value LONGTEXT NOT NULL,
				creado DATETIME NOT NULL,
				PRIMARY KEY (id)
			) {$wpdb->get_charset_collate()}"
		);
	}

	/**
	 * Copia las filas antes de tocarlas.
	 *
	 * @param \wpdb $wpdb Base de datos.
	 * @param string $tipo postmeta|usermeta.
	 * @param array<int,array<string,mixed>> $filas Filas.
	 * @return void
	 */
	private static function copiar( $wpdb, string $tipo, array $filas ): void {
		$tabla = $wpdb->prefix . self::TABLA_COPIA;

		foreach ( $filas as $fila ) {
			$wpdb->insert(
				$tabla,
				array(
					'meta_type'  => $tipo,
					'meta_id'    => (int) $fila['id'],
					'objeto'     => (int) $fila['objeto'],
					'meta_key'   => (string) $fila['meta_key'],
					'meta_value' => (string) $fila['meta_value'],
					'creado'     => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Cifra una fila y actualiza su índice si es DNI.
	 *
	 * @param \wpdb $wpdb Base de datos.
	 * @param string $tipo postmeta|usermeta.
	 * @param array<string,mixed> $fila Fila.
	 * @return bool
	 */
	private static function cifrar_fila( $wpdb, string $tipo, array $fila ): bool {
		$tabla = 'postmeta' === $tipo ? $wpdb->postmeta : $wpdb->usermeta;
		$id    = 'postmeta' === $tipo ? 'meta_id' : 'umeta_id';
		$clave = (string) $fila['meta_key'];
		$claro = (string) $fila['meta_value'];
		$campo = 0 === strpos( $clave, '_convoca_' ) ? substr( $clave, 9 ) : $clave;

		$nuevo = \Convoca\Core\Cifrado::cifrar( $claro );

		if ( '' === $nuevo || $nuevo === $claro ) {
			return false;
		}

		$ok = $wpdb->update(
			$tabla,
			array( 'meta_value' => $nuevo ),
			array( $id => (int) $fila['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return false;
		}

		if ( 'dni' === $campo ) {
			$indice = \Convoca\Core\Cifrado::indice_ciego( $claro );

			if ( '' !== $indice ) {
				if ( 'postmeta' === $tipo ) {
					update_post_meta( (int) $fila['objeto'], '_convoca_dni_idx', $indice );
				} else {
					update_user_meta( (int) $fila['objeto'], '_convoca_dni_idx', $indice );
				}
			}
		}

		return true;
	}
}
