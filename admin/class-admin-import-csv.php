<?php
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
		add_action( 'admin_post_bdv_import_csv_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_bdv_import_csv_run', array( $this, 'handle_import' ) );
		add_action( 'admin_post_bdv_import_csv_template', array( $this, 'handle_template_download' ) );
		add_action( 'admin_post_bdv_import_csv_continue', array( $this, 'handle_batch_continue' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'bdv-members',
			__( 'Importar Socios CSV', 'convoca-members' ),
			__( 'Importar CSV', 'convoca-members' ),
			'manage_options',
			'bdv-import-csv',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}
		$preview = get_transient( 'bdv_csv_preview_' . get_current_user_id() );
		?>
		<div class="wrap" style="max-width:900px;">
			<h1><?php esc_html_e( 'Importar Socios desde CSV', 'convoca-members' ); ?></h1>

			<?php if ( isset( $_GET['import_result'] ) ) : ?>
				<div class="biodevas-alert biodevas-alert--success" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( sanitize_text_field( $_GET['import_result'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php
			$done_key = 'bdv_import_batch_' . get_current_user_id() . '_done';
			$done_msg = get_transient( $done_key );
			if ( $done_msg ) :
				delete_transient( $done_key );
				?>
				<div class="biodevas-alert biodevas-alert--success" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( $done_msg ); ?></p>
				</div>
			<?php endif; ?>

			<div class="biodevas-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
				<h2><?php esc_html_e( '1. Sube el archivo CSV', 'convoca-members' ); ?></h2>
				<p><?php esc_html_e( 'El archivo debe incluir una fila de cabeceras. Columnas esperadas: nombre, email, dni, plan, teléfono, dirección, municipio.', 'convoca-members' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px;">
					<?php wp_nonce_field( 'bdv_import_csv' ); ?>
					<input type="hidden" name="action" value="bdv_import_csv_preview">
					<div class="biodevas-field">
						<input type="file" name="csv_file" accept=".csv" required>
					</div>
					<button type="submit" class="biodevas-btn biodevas-btn-primary"><?php esc_html_e( 'Previsualizar', 'convoca-members' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'bdv_import_csv_template' ); ?>
					<input type="hidden" name="action" value="bdv_import_csv_template">
					<button type="submit" class="biodevas-btn biodevas-btn-secondary"><?php esc_html_e( '📄 Descargar plantilla CSV', 'convoca-members' ); ?></button>
				</form>
			</div>

			<?php
			// Check for batch import progress.
			$batch_key      = 'bdv_import_batch_' . get_current_user_id();
			$batch_progress = get_transient( $batch_key );
			?>

			<?php if ( $batch_progress ) : ?>
				<div class="biodevas-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
					<h2><?php esc_html_e( '⏳ Importación en curso', 'convoca-members' ); ?></h2>
					<p style="font-size:1.2em;">
						<?php
						printf(
							__( 'Procesados %1$d de %2$d registros...', 'convoca-members' ),
							$batch_progress['imported'],
							$batch_progress['total']
						);
						?>
					</p>
					<?php $pct = $batch_progress['total'] > 0 ? round( $batch_progress['imported'] / $batch_progress['total'] * 100 ) : 0; ?>
					<div style="background:#eee;border-radius:8px;height:24px;margin:16px 0;overflow:hidden;">
						<div style="background:#4CAF50;height:100%;width:<?php echo $pct; ?>%;transition:width 0.3s;"></div>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'bdv_import_csv_continue' ); ?>
						<input type="hidden" name="action" value="bdv_import_csv_continue">
						<button type="submit" class="biodevas-btn biodevas-btn-primary">
							<?php esc_html_e( '▶ Continuar importación', 'convoca-members' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<?php if ( $preview ) : ?>
				<div class="biodevas-box" style="background:#fff;border-radius:12px;padding:30px;margin-top:20px;">
					<h2><?php printf( __( '2. Vista previa (%d filas detectadas)', 'convoca-members' ), $preview['total'] ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'bdv_import_csv_run' ); ?>
						<input type="hidden" name="action" value="bdv_import_csv_run">
						<input type="hidden" name="filename" value="<?php echo esc_attr( $preview['filename'] ); ?>">
						<input type="hidden" name="total_rows" value="<?php echo esc_attr( $preview['total'] ); ?>">

						<div class="biodevas-field">
							<label><?php esc_html_e( 'Mapeo de columnas', 'convoca-members' ); ?></label>
							<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
								<?php
								foreach ( array(
									'nombre'    => 'Nombre',
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
							<button type="submit" class="biodevas-btn biodevas-btn-primary" onclick="return confirm('<?php esc_attr_e( '¿Importar todos los socios? Esta acción no se puede deshacer.', 'convoca-members' ); ?>');">
								🚀 <?php printf( __( 'Importar %d socios', 'convoca-members' ), $preview['total'] ); ?>
							</button>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_preview(): void {
		check_admin_referer( 'bdv_import_csv' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_die( __( 'No se ha subido ningún archivo.', 'convoca-members' ) );
		}

		$filename = $_FILES['csv_file']['tmp_name'];
		$handle   = fopen( $filename, 'r' );
		if ( ! $handle ) {
			wp_die( __( 'No se pudo leer el archivo.', 'convoca-members' ) );
		}

		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $headers ) {
			fclose( $handle );
			wp_die( __( 'CSV vacío o sin cabeceras.', 'convoca-members' ) ); }
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
		fclose( $handle );

		$target_filename = 'bdv_import_' . uniqid() . '.csv';
		copy( $filename, sys_get_temp_dir() . '/' . $target_filename );

		set_transient(
			'bdv_csv_preview_' . get_current_user_id(),
			array(
				'headers'  => $headers,
				'rows'     => $rows,
				'total'    => $total,
				'filename' => $target_filename,
			),
			600
		);

		wp_safe_redirect( admin_url( 'admin.php?page=bdv-import-csv' ) );
		exit;
	}

	public function handle_template_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$filename = 'plantilla-socios.csv';

		$headers = array( 'nombre', 'email', 'dni', 'plan', 'telefono', 'direccion', 'municipio' );
		$example = array( 'Juan García López', 'juan@example.com', '12345678Z', 'socio-adulto', '612345678', 'C/ Mayor 123', 'Madrid' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' ); .
		// BOM for Excel.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $out, $headers );
		fputcsv( $out, $example );
		fclose( $out );
		exit;
	}

	public function handle_import(): void {
		check_admin_referer( 'bdv_import_csv_run' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$filename = sanitize_file_name( $_POST['filename'] ?? '' );
		$filepath = sys_get_temp_dir() . '/' . $filename;
		if ( ! file_exists( $filepath ) ) {
			wp_die( __( 'Archivo temporal no encontrado. Vuelve a subir el CSV.', 'convoca-members' ) );
		}

		$map             = array_map( 'sanitize_text_field', $_POST['map'] ?? array() );
		$send_welcome    = isset( $_POST['send_welcome'] );
		$update_existing = isset( $_POST['update_existing'] );

		// Read all rows into memory.
		$handle  = fopen( $filepath, 'r' );
		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $headers ) {
			fclose( $handle );
			wp_die( __( 'Error al leer cabeceras.', 'convoca-members' ) ); }

		$rows = array();
		while ( ( $data = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			if ( empty( array_filter( $data ) ) ) {
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
		fclose( $handle );

		$total      = count( $rows );
		$batch_size = 25;

		// For large imports, use batch processing.
		if ( $total > 50 ) {
			// Store import state in transient for batch processing.
			$batch_key = 'bdv_import_batch_' . get_current_user_id();
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

			delete_transient( 'bdv_csv_preview_' . get_current_user_id() );

			// Process first batch synchronously within this request.
			$this->process_batch( $batch_key, $batch_size );

			// Redirect back to continue.
			wp_safe_redirect( admin_url( 'admin.php?page=bdv-import-csv' ) );
			exit;
		}

		// Small import: process all at once.
		$imported = 0;
		$errors   = array();
		foreach ( $rows as $row ) {
			$result = $this->import_single_row( $row, $errors, $imported, $send_welcome, $update_existing );
			if ( $result === false ) {
				++$imported;
			} elseif ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				++$imported;
			} else {
				++$imported;
			}

			@set_time_limit( 30 );
		}

		unlink( $filepath );
		delete_transient( 'bdv_csv_preview_' . get_current_user_id() );

		\Convoca\Core\Logger::info(
			sprintf( 'Importación CSV: %d importados, %d errores.', $imported, count( $errors ) ),
			'Members/Admin'
		);

		$message = sprintf( __( '%d socios importados correctamente.', 'convoca-members' ), $imported );
		if ( ! empty( $errors ) ) {
			$message .= ' ' . sprintf( __( '%d errores:', 'convoca-members' ), count( $errors ) ) . ' ' . implode( ' | ', array_slice( $errors, 0, 10 ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bdv-import-csv&import_result=' . urlencode( $message ) ) );
		exit;
	}

	/**
	 * Continue a batch import.
	 */
	public function handle_batch_continue(): void {
		check_admin_referer( 'bdv_import_csv_continue' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$batch_key = 'bdv_import_batch_' . get_current_user_id();
		$state     = get_transient( $batch_key );

		if ( ! $state || empty( $state['rows'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bdv-import-csv' ) );
			exit;
		}

		$this->process_batch( $batch_key, 25 );

		wp_safe_redirect( admin_url( 'admin.php?page=bdv-import-csv' ) );
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
			if ( $result === false || is_wp_error( $result ) ) {
				++$imported;
			} else {
				++$imported;
			}
			@set_time_limit( 30 );
		}

		$state['offset']   = $end;
		$state['imported'] = $imported;
		$state['errors']   = $errors;

		if ( $end >= count( $rows ) ) {
			// Batch complete.
			delete_transient( $batch_key );
			if ( ! empty( $state['filepath'] ) && file_exists( $state['filepath'] ) ) {
				unlink( $state['filepath'] );
			}

			\Convoca\Core\Logger::info(
				sprintf( 'Importación CSV completa: %d importados, %d errores.', $imported, count( $errors ) ),
				'Members/Admin'
			);

			$message = sprintf( __( '🎉 Importación completada. %d socios importados.', 'convoca-members' ), $imported );
			if ( ! empty( $errors ) ) {
				$message .= ' ' . sprintf( __( '%d errores:', 'convoca-members' ), count( $errors ) ) . ' ' . implode( ' | ', array_slice( $errors, 0, 10 ) );
			}

			// Store result message and redirect.
			set_transient( 'bdv_import_batch_' . get_current_user_id() . '_done', $message, 60 );
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
			$errors[] = sprintf( __( 'Fila %d: nombre y email obligatorios.', 'convoca-members' ), $line );
			return false;
		}

		if ( ! is_email( $email ) ) {
			$errors[] = sprintf( __( 'Fila %1$d: email inválido (%2$s).', 'convoca-members' ), $line, $email );
			return false;
		}

		// Validate DNI.
		if ( ! empty( $dni ) && ! \Convoca\Core\Utils::validar_dni( $dni ) ) {
			$errors[] = sprintf( __( 'Fila %1$d: DNI inválido (%2$s).', 'convoca-members' ), $line, $dni );
			return false;
		}

		// Check for existing member by email or DNI.
		$existing_id = $this->find_existing_member( $email, $dni );

		if ( $existing_id ) {
			if ( ! $update_existing ) {
				if ( $dni ) {
					$errors[] = sprintf( __( 'Fila %1$d: socio ya existe (email: %2$s, DNI: %3$s).', 'convoca-members' ), $line, $email, $dni );
				} else {
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
				)
			);

			if ( is_wp_error( $post_id ) ) {
				$errors[] = sprintf( __( 'Fila %1$d: error al crear (%2$s).', 'convoca-members' ), $line, $post_id->get_error_message() );
				return $post_id;
			}

			// Generate access code for new members.
			$access_code = \Convoca\Core\Utils::generate_access_code();
			update_post_meta( $post_id, '_bdv_access_code', $access_code );

			// Assign member number only to new members.
			$num = CPT_Miembro::get_next_member_number( $post_id );
			update_post_meta( $post_id, '_bdv_numero_socio', $num );
		}

		// Save meta (for both new and updated members).
		$meta_map = array( 'email', 'dni', 'telefono', 'direccion', 'municipio', 'plan' );
		foreach ( $meta_map as $m ) {
			if ( ! empty( $row[ $m ] ) ) {
				update_post_meta( $post_id, '_bdv_' . $m, sanitize_text_field( $row[ $m ] ) );
			}
		}
		update_post_meta( $post_id, '_bdv_estado_miembro', 'activo' );

		if ( $send_welcome ) {
			do_action( 'bdv_member_created', $post_id, array( 'nombre' => $nombre ) );
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
		// Check by email first.
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			return null; // This is a WP user, not a member post; don't treat as duplicate.
		}

		// Check by email meta.
		$by_email = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => 1,
				'meta_key'       => '_bdv_email',
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
					'meta_key'       => '_bdv_dni',
					'meta_value'     => $dni,
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $by_dni ) ) {
				return (int) $by_dni[0];
			}
		}

		return null;
	}
}
