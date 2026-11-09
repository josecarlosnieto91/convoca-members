<?php
/**
 * Stub de análisis para WP-CLI (no se carga en runtime).
 *
 * @package Convoca\Members
 */

/**
 * Cliente de WP-CLI.
 */
class WP_CLI {

	/**
	 * Registra un comando.
	 *
	 * @param string   $name     Nombre.
	 * @param callable $callback Callback.
	 * @return void
	 */
	public static function add_command( string $name, callable $callback ): void {}

	/**
	 * Escribe una línea.
	 *
	 * @param string $message Mensaje.
	 * @return void
	 */
	public static function log( string $message ): void {}

	/**
	 * Termina con éxito.
	 *
	 * @param string $message Mensaje.
	 * @return void
	 */
	public static function success( string $message ): void {}

	/**
	 * Termina con error.
	 *
	 * @param string $message Mensaje.
	 * @param bool   $salir   ¿Salir?
	 * @return void
	 */
	public static function error( string $message, bool $salir = true ): void {}
}
