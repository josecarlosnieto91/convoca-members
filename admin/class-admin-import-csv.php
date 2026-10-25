<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Admin
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * CSV Import page for members.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Import_CSV {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_convoca_import_csv_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_convoca_import_csv_run', array( $this, 'handle_import' ) );
		add_action( 'admin_post_convoca_import_csv_template', array( $this, 'handle_template_download' ) );
		add_action( 'admin_post_convoca_import_csv_continue', array( $this, 'handle_batch_continue' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'conv-members',
			__( 'Importar Socios CSV', 'convoca-members' ),
			__( 'Importar CSV', 'convoca-members' ),
			'manage_options',
			'conv-import-csv',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}
		$preview = get_transient( 'convoca_csv_preview_' . get_current_user_id() );
		?>
		<div class="wrap" style="max-width:900px;">
			<h1><?php esc_html_e( 'Importar Socios desde CSV', 'convoca-members' ); ?></h1>

			<?php
			if ( isset( $_GET['import_result'] ) ) :
				// El color del cartel depende del resultado: verde es «todo bien», y con filas fallidas no lo era.
				$estado_import = sanitize_key( wp_unslash( $_GET['import_status'] ?? 'ok' ) );
				$clase_import  = 'convoca-alert--success';
				$icono_import  = '✅';
				if ( 'parcial' === $estado_import ) {
					$clase_import = 'convoca-alert--warning';
					$icono_import = '⚠️';
				} elseif ( 'error' === $estado_import ) {
					$clase_import = 'convoca-alert--danger';
					$icono_import = '❌';
				}
				?>
				<div class="convoca-alert <?php echo esc_attr( $clase_import ); ?>" style="display:block;margin-bottom:20px;" role="alert">
					<p><strong><?php echo esc_html( $icono_import ); ?></strong> <?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['import_result'] ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			$done_key = 'convoca_import_batch_' . get_current_user_id() . '_done';
			$done_msg = get_transient( $done_key );
			if ( $done_msg ) :
				delete_transient( $done_key );
				?>
				<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( $done_msg ); ?></p>
				</div>
			<?php endif; ?>

			<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
				<h2><?php esc_html_e( '1. Sube el archivo CSV', 'convoca-members' ); ?></h2>
				<p><?php esc_html_e( 'El archivo debe incluir una fila de cabeceras. Columnas esperadas: nombre, apellidos, email, dni, plan, teléfono, dirección, municipio.', 'convoca-members' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
					<?php wp_nonce_field( 'convoca_import_csv' ); ?>
					<input type="hidden" name="action" value="convoca_import_csv_preview">
					<div class="convoca-field">
						<input type="file" name="csv_file" accept=".csv,.xlsx" required>
					</div>
					<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Previsualizar', 'convoca-members' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'convoca_import_csv_template' ); ?>
					<input type="hidden" name="action" value="convoca_import_csv_template">
					<button type="submit" class="convoca-btn convoca-btn-secondary"><?php esc_html_e( '📄 Descargar plantilla CSV', 'convoca-members' ); ?></button>
				</form>
			</div>

			<?php
			// Check for batch import progress.
			$batch_key      = 'convoca_import_batch_' . get_current_user_id();
			$batch_progress = get_transient( $batch_key );
			?>

			<?php if ( $batch_progress ) : ?>
				<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
					<h2><?php esc_html_e( '⏳ Importación en curso', 'convoca-members' ); ?></h2>
					<p style="font-size:1.2em;">
						<?php
						printf(
							/* translators: 1: imported count, 2: total count */                            esc_html__( 'Procesados %1$d de %2$d registros...', 'convoca-members' ),
							(int) $batch_progress['imported'],
							(int) $batch_progress['total']
						);
						?>
					</p>
					<?php $pct = $batch_progress['total'] > 0 ? round( $batch_progress['imported'] / $batch_progress['total'] * 100 ) : 0; ?>
					<div style="background:#eee;border-radius:8px;height:24px;margin:16px 0;overflow:hidden;">
						<div style="background:#4CAF50;height:100%;width:<?php echo (int) $pct; ?>%;transition:width 0.3s;"></div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'convoca_import_csv_continue' ); ?>
						<input type="hidden" name="action" value="convoca_import_csv_continue">
						<button type="submit" class="convoca-btn convoca-btn-primary">
							<?php esc_html_e( '▶ Continuar importación', 'convoca-members' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<?php if ( $preview ) : ?>
				<div class="convoca-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
					<h2><?php printf( /* translators: %d: number of rows */ esc_html__( '2. Vista previa (%d filas detectadas)', 'convoca-members' ), (int) $preview['total'] ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'convoca_import_csv_run' ); ?>
						<input type="hidden" name="action" value="convoca_import_csv_run">
						<input type="hidden" name="token" value="<?php echo esc_attr( $preview['token'] ); ?>">
						<input type="hidden" name="total_rows" value="<?php echo esc_attr( $preview['total'] ); ?>">

						<div class="convoca-field">
							<label><?php esc_html_e( 'Mapeo de columnas', 'convoca-members' ); ?></label>
							<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
								<?php
								foreach ( array(
									'nombre'    => 'Nombre',
									// Los apellidos tienen su propio campo en la ficha (`_convoca_apellidos`) y el carnet y los
									// documentos los usan: el importador no los ofrecía, así que un censo con la columna
									// «apellidos» no tenía dónde meterlos (issue #44).
									'apellidos' => 'Apellidos',
									'email'     => 'Email',
									'dni'       => 'DNI',
									'plan'      => 'Plan',
									'telefono'  => 'Teléfono',
									'direccion' => 'Dirección',
									'municipio' => 'Municipio',
								) as $field => $label ) :
									?>
									<div>
										<label style="font-size:12px;">→ <?php echo esc_html( $label ); ?></label>
										<select name="map[<?php echo esc_attr( $field ); ?>]" style="width:100%;">
											<option value="">— <?php esc_html_e( 'Ignorar', 'convoca-members' ); ?> —</option>
											<?php foreach ( $preview['headers'] as $h ) : ?>
												<option value="<?php echo esc_attr( $h ); ?>" <?php selected( strtolower( $h ), $field ); ?>><?php echo esc_html( $h ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<h3><?php esc_html_e( 'Primeras filas:', 'convoca-members' ); ?></h3>
						<div style="overflow-x:auto;">
							<table class="wp-list-table widefat fixed striped">
								<thead><tr>
								<?php
								foreach ( $preview['headers'] as $h ) :
									?>
									<th><?php echo esc_html( $h ); ?></th><?php endforeach; ?></tr></thead>
								<tbody>
									<?php foreach ( $preview['rows'] as $row ) : ?>
										<tr>
										<?php
										foreach ( $row as $cell ) :
											?>
											<td><?php echo esc_html( $cell ); ?></td><?php endforeach; ?></tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<div style="margin-top:20px;display:flex;gap:16px;flex-wrap:wrap;">
							<label>
								<input type="checkbox" name="send_welcome" value="1" checked>
								<?php esc_html_e( 'Enviar email de bienvenida', 'convoca-members' ); ?>
							</label>
							<label>
								<input type="checkbox" name="update_existing" value="1">
								<?php esc_html_e( 'Actualizar datos si el socio ya existe (por email o DNI)', 'convoca-members' ); ?>
							</label>
						</div>

						<div style="margin-top:20px;">
							<button type="submit" class="convoca-btn convoca-btn-primary" onclick="return confirm('<?php esc_attr_e( '¿Importar todos los socios? Esta acción no se puede deshacer.', 'convoca-members' ); ?>');">
								🚀 <?php printf( /* translators: %d: number of members */ esc_html__( 'Importar %d socios', 'convoca-members' ), (int) $preview['total'] ); ?>
							</button>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_preview(): void {
		check_admin_referer( 'convoca_import_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No se ha subido ningún archivo.', 'convoca-members' ) );
		}

		$filename = $_FILES['csv_file']['tmp_name'];

		// Convert .xlsx to CSV if needed (no external deps, first sheet only).
		$ext = strtolower( pathinfo( $_FILES['csv_file']['name'] ?? '', PATHINFO_EXTENSION ) );
		if ( 'xlsx' === $ext ) {
			try {
				$csv_data = \Convoca\Members\Admin\Xlsx_Reader::to_csv( $filename );
				$temp_csv = tempnam( self::dir_importacion(), 'xlsx_' ) . '.csv';
				file_put_contents( $temp_csv, $csv_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp file.
				$filename = $temp_csv;
			} catch ( \RuntimeException $e ) {
				wp_die( esc_html__( 'No se pudo leer el archivo XLSX: ', 'convoca-members' ) . esc_html( $e->getMessage() ) );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV parsing requires direct file access.
		$handle = fopen( $filename, 'r' );
		if ( ! $handle ) {
			wp_die( esc_html__( 'No se pudo leer el archivo.', 'convoca-members' ) );
		}

		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'CSV vacío o sin cabeceras.', 'convoca-members' ) ); }
		$headers = array_map( 'trim', $headers );

		$rows  = array();
		$total = 0;
		while ( ( $data = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false && $total < 5 ) {
			$rows[] = $data;
			++$total;
		}
		// Count total rows.
		while ( fgetcsv( $handle, 0, ',', '"', '' ) !== false ) {
			++$total;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// El censo se guarda en el directorio privado, con nombre de ficha aleatoria (members#48).
		$token   = bin2hex( random_bytes( 16 ) );
		$destino = self::dir_importacion() . '/' . $token . '.csv';
		if ( ! copy( $filename, $destino ) ) {
			wp_die( esc_html__( 'No se ha podido guardar el archivo para la importación.', 'convoca-members' ) );
		}
		chmod( $destino, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Permisos del dueño para un fichero con datos personales.

		set_transient(
			'convoca_csv_fichero_' . $token,
			array(
				'ruta'    => $destino,
				'usuario' => get_current_user_id(),
			),
			HOUR_IN_SECONDS
		);

		set_transient(
			'convoca_csv_preview_' . get_current_user_id(),
			array(
				'headers'  => $headers,
				'rows'     => $rows,
				'total'    => $total,
				'token'    => $token,
				'nombre'   => sanitize_file_name( (string) ( $_FILES['csv_file']['name'] ?? '' ) ),
			),
			600
		);

		wp_safe_redirect( admin_url( 'admin.php?page=conv-import-csv' ) );
		exit;
	}

	public function handle_template_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$filename = 'plantilla-socios.csv';

		$headers = array( 'nombre', 'apellidos', 'email', 'dni', 'plan', 'telefono', 'direccion', 'municipio' );
		$example = array( 'Juan García López', 'juan@example.com', '12345678Z', 'socio-adulto', '612345678', 'Calle Ejemplo 1 123', 'Madrid' );

		// La ceremonia vive en el núcleo, con el separador que exige SU LECTOR: esta plantilla se
		// vuelve a subir y el importador de socios la lee con `fgetcsv( …, ',', … )`, así que el
		// fichero tiene que seguir saliendo con comas. Ahí está el porqué del parámetro.
		\Convoca\Core\Utils::stream_csv( $filename, $headers, array( $example ), ',' );

		exit;
	}

	/**
	 * El directorio donde vive el censo entre los dos pasos de la importación (members#48).
	 *
	 * ANTES: el CSV se copiaba al `/tmp` **compartido** del servidor (modo 1777) y en el segundo paso se abría
	 * la ruta que mandaba el cliente (`$_POST['filename']`). Un censo con nombres, DNI, correos y direcciones
	 * pasaba por un directorio que cualquier otro usuario de la máquina puede leer, y con un `filename` a mano
	 * la importación leía CUALQUIER fichero de ese directorio.
	 *
	 * AHORA: un subdirectorio propio del sitio, con permisos de dueño (0700, así nadie de fuera puede ni
	 * entrar), fuera de la raíz web (no se puede pedir por URL ni con nginx, donde un `.htaccess` no valdría),
	 * y con el fichero nombrado con una ficha aleatoria. En el segundo paso el navegador manda la **ficha**,
	 * no una ruta: el servidor resuelve la ruta él mismo y comprueba que el fichero está dentro de su carpeta.
	 *
	 * @return string Ruta del directorio, sin barra final.
	 */
	private static function dir_importacion(): string {
		$dir = rtrim( sys_get_temp_dir(), '/' ) . '/convoca-imports-' . substr( md5( ABSPATH ), 0, 12 );

		if ( ! is_dir( $dir ) ) {
			// Se crea ya en 0700 (`wp_mkdir_p()` lo haría en 0755 y dejaría una ventana con el censo dentro).
			mkdir( $dir, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Carpeta privada de trabajo.
		}

		// Los restos de una importación abandonada en el primer paso se limpian aquí (viven dos horas).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob, WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- Directorio propio del sitio.
		foreach ( (array) glob( $dir . '/*.csv' ) as $viejo ) {
			if ( filemtime( $viejo ) < time() - 2 * HOUR_IN_SECONDS ) {
				unlink( $viejo ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Limpieza de restos.
			}
		}

		return $dir;
	}

	/**
	 * Resuelve la ruta del censo a partir de la ficha que manda el navegador (members#48).
	 *
	 * @param string $token Ficha (32 caracteres hexadecimales).
	 * @return string Ruta del fichero, o '' si la ficha no vale.
	 */
	private static function ruta_por_ficha( string $token ): string {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return '';
		}

		$ficha = get_transient( 'convoca_csv_fichero_' . $token );
		if ( ! is_array( $ficha ) || empty( $ficha['ruta'] ) ) {
			return '';
		}

		// Solo sirve el fichero de esta importación y de quien la empezó.
		if ( (int) ( $ficha['usuario'] ?? 0 ) !== get_current_user_id() ) {
			return '';
		}

		$ruta = (string) $ficha['ruta'];
		$real = realpath( $ruta );
		$dir  = realpath( self::dir_importacion() );

		if ( ! $real || ! $dir || 0 !== strpos( $real, $dir . '/' ) || ! is_file( $real ) ) {
			return '';
		}

		return $real;
	}

	public function handle_import(): void {
		check_admin_referer( 'convoca_import_csv_run' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		// La ruta la resuelve el servidor a partir de la ficha; el navegador no manda rutas (members#48).
		$filepath = self::ruta_por_ficha( (string) wp_unslash( $_POST['token'] ?? '' ) );
		if ( '' === $filepath ) {
			wp_die( esc_html__( 'La importación ha caducado o no se ha podido comprobar el archivo. Vuelve a subir el CSV.', 'convoca-members' ) );
		}

		$map             = array_map( 'sanitize_text_field', wp_unslash( $_POST['map'] ?? array() ) );
		$send_welcome    = isset( $_POST['send_welcome'] );
		$update_existing = isset( $_POST['update_existing'] );

		// Read all rows into memory.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV parsing requires direct file access.
		$handle  = fopen( $filepath, 'r' );
		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $headers ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_die( esc_html__( 'Error al leer cabeceras.', 'convoca-members' ) ); }

		$rows = array();
		while ( ( $data = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			// Una fila con celdas vacías **o con solo espacios** no se importa ni cuenta como error.
			//
			// `array_filter()` sin callback no descarta `'   '` (el espacio es verdadero), así que una fila de
			// relleno de una hoja de cálculo se procesaba y salía en el resumen como «1 fila con errores: nombre
			// y email obligatorios» sobre una fila que no existe, con el cartel en aviso en vez de en verde
			// (members#73). Y comparando contra `''` se respeta un `'0'` legítimo, que `array_filter()` a secas
			// se comía (un DNI o un teléfono `0` no hacen vacía la fila).
			if ( empty( array_filter( array_map( 'trim', $data ), static fn( $celda ) => '' !== $celda ) ) ) {
				continue;
			}
			$row = array();
			foreach ( $map as $field => $header ) {
				if ( ! $header ) {
					continue;
				}
				$idx           = array_search( $header, $headers, true );
				$row[ $field ] = $idx !== false ? trim( $data[ $idx ] ?? '' ) : '';
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$total      = count( $rows );
		$batch_size = 25;

		// For large imports, use batch processing.
		if ( $total > 50 ) {
			// Store import state in transient for batch processing.
			$batch_key = 'convoca_import_batch_' . get_current_user_id();
			set_transient(
				$batch_key,
				array(
					'rows'            => $rows,
					'total'           => $total,
					'offset'          => 0,
					'imported'        => 0,
					'errors'          => array(),
					'send_welcome'    => $send_welcome,
					'update_existing' => $update_existing,
					'filepath'        => $filepath,
				),
				3600
			);

			delete_transient( 'convoca_csv_preview_' . get_current_user_id() );

			// Process first batch synchronously within this request.
			$this->process_batch( $batch_key, $batch_size );

			// Redirect back to continue.
			wp_safe_redirect( admin_url( 'admin.php?page=conv-import-csv' ) );
			exit;
		}

		// Small import: process all at once.
		$imported = 0;
		$errors   = array();
		foreach ( $rows as $indice => $row ) {
			$result = $this->import_single_row( $row, $errors, (int) $indice + 1, $send_welcome, $update_existing );

			// Solo cuenta lo que se ha creado de verdad. `false` es «descartada» —su motivo ya está en
			// $errors— y `WP_Error` es «falló». Antes se contaba en las tres ramas, así que el resumen
			// decía «1 socios importados correctamente. 1 errores» de la MISMA fila, y el número de fila
			// que salía en el mensaje era el del contador, no el de la fila (de ahí el «Fila 0»): el
			// contador viajaba en el parámetro del número de línea. Issue #26.
			if ( is_int( $result ) ) {
				++$imported;
			} elseif ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}

			if ( false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
				set_time_limit( 30 );
			}
		}

		unlink( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Temporary import file cleanup.
		delete_transient( 'convoca_csv_preview_' . get_current_user_id() );

		\Convoca\Core\Logger::info(
			sprintf( 'Importación CSV: %d importados, %d errores.', $imported, count( $errors ) ),
			'Members/Admin'
		);

		// El aviso tiene que decir lo que ha pasado DE VERDAD: en verde no se leen los errores.
		//
		// Medido el 15/10/2026 con un CSV de tres filas (una buena, una duplicada y una sin email): el resumen
		// salía en un cartel VERDE de éxito con el texto «1 socios importados correctamente. 2 errores: …».
		// Para quien no es técnico, verde es «todo bien» y se va sin leer nada. Ahora: verde solo si todo ha
		// ido bien, aviso si hubo filas fallidas, y rojo si no se importó ninguna. Y «1 socio importado».
		$message = sprintf(
			/* translators: %d: number of members */
			_n( '%d socio importado correctamente.', '%d socios importados correctamente.', $imported, 'convoca-members' ),
			$imported
		);

		if ( empty( $errors ) ) {
			$estado = 'ok';
		} else {
			$estado   = ( $imported > 0 ) ? 'parcial' : 'error';
			$message .= ' ' . sprintf(
				/* translators: %d: error count */
				_n( '%d fila con errores:', '%d filas con errores:', count( $errors ), 'convoca-members' ),
				count( $errors )
			) . ' ' . implode( ' | ', array_slice( $errors, 0, 10 ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=conv-import-csv&import_status=' . $estado . '&import_result=' . rawurlencode( $message ) ) );
		exit;
	}

	/**
	 * Continue a batch import.
	 */
	public function handle_batch_continue(): void {
		check_admin_referer( 'convoca_import_csv_continue' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$batch_key = 'convoca_import_batch_' . get_current_user_id();
		$state     = get_transient( $batch_key );

		if ( ! $state || empty( $state['rows'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=conv-import-csv' ) );
			exit;
		}

		$this->process_batch( $batch_key, 25 );

		wp_safe_redirect( admin_url( 'admin.php?page=conv-import-csv' ) );
		exit;
	}

	/**
	 * Process a single batch of rows.
	 */
	private function process_batch( string $batch_key, int $batch_size ): void {
		$state = get_transient( $batch_key );
		if ( ! $state ) {
			return;
		}

		$rows     = $state['rows'];
		$start    = $state['offset'];
		$end      = min( $start + $batch_size, count( $rows ) );
		$imported = $state['imported'];
		$errors   = $state['errors'];

		for ( $i = $start; $i < $end; $i++ ) {
			$row    = $rows[ $i ];
			$line   = $i + 1;
			$result = $this->import_single_row( $row, $errors, $line, $state['send_welcome'], $state['update_existing'] );

			// Igual que en la importación corta: solo cuenta lo creado. Ver issue #26.
			if ( is_int( $result ) ) {
				++$imported;
			} elseif ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
			if ( false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
				set_time_limit( 30 );
			}
		}

		$state['offset']   = $end;
		$state['imported'] = $imported;
		$state['errors']   = $errors;

		if ( $end >= count( $rows ) ) {
			// Batch complete.
			delete_transient( $batch_key );
			if ( ! empty( $state['filepath'] ) && file_exists( $state['filepath'] ) ) {
				unlink( $state['filepath'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Temporary import file cleanup.
			}

			\Convoca\Core\Logger::info(
				sprintf( 'Importación CSV completa: %d importados, %d errores.', $imported, count( $errors ) ),
				'Members/Admin'
			);

			// `_n()` y no `__()`: el camino largo se quedó atrás cuando se corrigió el plural del corto (#26) y
			// decía «🎉 Importación completada. 1 socios importados» / «1 errores». Es lo único que ve quien da de
			// alta a una persona a mano (members#74).
			$message = sprintf(
				/* translators: %d: number of members */
				_n( '🎉 Importación completada. %d socio importado.', '🎉 Importación completada. %d socios importados.', $imported, 'convoca-members' ),
				$imported
			);
			if ( ! empty( $errors ) ) {
				$message .= ' ' . sprintf(
					/* translators: %d: error count */
					_n( '%d error:', '%d errores:', count( $errors ), 'convoca-members' ),
					count( $errors )
				) . ' ' . implode( ' | ', array_slice( $errors, 0, 10 ) );
			}

			// Store result message and redirect.
			set_transient( 'convoca_import_batch_' . get_current_user_id() . '_done', $message, 60 );
		} else {
			set_transient( $batch_key, $state, 3600 );
		}
	}

	/**
	 * Import or update a single member row.
	 *
	 * @param array $row           Mapped row data.
	 * @param array &$errors       Error accumulator.
	 * @param int   $line          Line number for error messages.
	 * @param bool  $send_welcome  Whether to trigger welcome email.
	 * @param bool  $update_existing Whether to update existing members.
	 * @return int|false|\WP_Error Post ID on success, false on skip (error logged), WP_Error on failure.
	 */
	private function import_single_row( array $row, array &$errors, int $line, bool $send_welcome, bool $update_existing ) {
		$nombre = $row['nombre'] ?? '';
		$email  = $row['email'] ?? '';
		$dni    = $row['dni'] ?? '';

		// Validate required fields.
		if ( empty( $nombre ) || empty( $email ) ) {
			$errors[] = sprintf( /* translators: %d: line number */ __( 'Fila %d: nombre y email obligatorios.', 'convoca-members' ), $line );
			return false;
		}

		if ( ! is_email( $email ) ) {
			$errors[] = sprintf( /* translators: 1: line number, 2: email address */ __( 'Fila %1$d: email inválido (%2$s).', 'convoca-members' ), $line, $email );
			return false;
		}

		// Validate DNI.
		if ( ! empty( $dni ) && ! \Convoca\Core\Utils::validar_dni( $dni ) ) {
			$errors[] = sprintf( /* translators: 1: line number, 2: DNI value */ __( 'Fila %1$d: DNI inválido (%2$s).', 'convoca-members' ), $line, $dni );
			return false;
		}

		// Check for existing member by email or DNI.
		$existing_id = $this->find_existing_member( $email, $dni );

		if ( $existing_id ) {
			if ( ! $update_existing ) {
				if ( $dni ) {
					/* translators: 1: line number, 2: email, 3: DNI */
					$errors[] = sprintf( __( 'Fila %1$d: socio ya existe (email: %2$s, DNI: %3$s).', 'convoca-members' ), $line, $email, $dni );
				} else {
					/* translators: 1: line number, 2: email */
					$errors[] = sprintf( __( 'Fila %1$d: socio ya existe (email: %2$s).', 'convoca-members' ), $line, $email );
				}
				return false;
			}

			// Update existing member.
			wp_update_post(
				array(
					'ID'         => $existing_id,
					'post_title' => $nombre,
				)
			);
			$post_id = $existing_id;
		} else {
			// Create new member.
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'miembro',
					'post_title'  => $nombre,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				/* translators: 1: line number, 2: error message */
				$errors[] = sprintf( __( 'Fila %1$d: error al crear (%2$s).', 'convoca-members' ), $line, $post_id->get_error_message() );
				return $post_id;
			}

			// Generate access code for new members.
			$access_code = \Convoca\Core\Utils::generate_access_code();
			update_post_meta( $post_id, '_convoca_access_code', $access_code );

			// Assign member number only to new members.
			$num = CPT_Miembro::get_next_member_number( $post_id );
			update_post_meta( $post_id, '_convoca_numero_socio', $num );
		}

		// Save meta (for both new and updated members).
		$meta_map = array( 'apellidos', 'email', 'dni', 'telefono', 'direccion', 'municipio', 'plan' );
		foreach ( $meta_map as $m ) {
			if ( ! empty( $row[ $m ] ) ) {
				update_post_meta( $post_id, '_convoca_' . $m, sanitize_text_field( $row[ $m ] ) );
			}
		}
		update_post_meta( $post_id, '_convoca_estado_miembro', 'activo' );

		if ( $send_welcome ) {
			do_action( 'convoca_member_created', $post_id, array( 'nombre' => $nombre ) );
		}

		return $post_id;
	}

	/**
	 * Find an existing member by email or DNI.
	 *
	 * @param string $email
	 * @param string $dni
	 * @return int|null Post ID if found, null otherwise.
	 */
	private function find_existing_member( string $email, string $dni ): ?int {
		// La FICHA es el criterio que manda, y se mira primero.
		//
		// Antes se miraba antes el usuario de WordPress y, si el correo era de un usuario, se devolvía
		// null («no es una ficha duplicada»). Pero casi todos los socios tienen usuario con su correo —lo
		// crea el alta—, así que el control de duplicados no veía a NINGUNO de ellos: importar un socio
		// existente creaba una ficha duplicada. Medido: una importación de una fila ya existente sumó dos
		// fichas a la demo y el resumen dijo «1 importados, 0 errores». Issue #26.
		$by_email = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_convoca_email',
				'meta_value'     => $email,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $by_email ) ) {
			return (int) $by_email[0];
		}

		// Check by DNI.
		if ( ! empty( $dni ) ) {
			$by_dni = get_posts(
				array(
					'post_type'      => 'miembro',
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'meta_key'       => '_convoca_dni',
					'meta_value'     => $dni,
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $by_dni ) ) {
				return (int) $by_dni[0];
			}
		}

		// Sin ficha, un usuario de WordPress con ese correo NO es una ficha duplicada: se deja crear la ficha.
		//
		// Aquí había un `get_user_by( 'email', $email )` cuyo resultado se descartaba —las dos ramas devolvían
		// `null`—, así que era una consulta a `wp_users` por CADA fila del CSV: mil consultas inútiles en una
		// importación de mil socios (members#72). La decisión se expresa con este comentario, no ejecutando la
		// consulta y tirando el valor.
		return null;
	}
}
