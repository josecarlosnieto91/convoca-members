<?php
/**
 * Admin view for volunteering hours.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Admin_Horas extends \WP_List_Table {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_convoca_process_horas', array( $this, 'process_action' ) );
		add_action( 'admin_post_convoca_save_hours_admin', array( $this, 'handle_save_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'load-post.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'admin_post_convoca_export_horas_csv', array( $this, 'handle_export_csv' ) );
	}

	/**
	 * Initialize WP_List_Table (must be called after admin screen is available).
	 */
	private function init_table(): void {
		parent::__construct(
			array(
				'singular' => 'hora',
				'plural'   => 'horas',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Enqueue assets for the custom editor.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'conv-horas-editor' ) !== false ) {
			wp_enqueue_style( 'convoca-core', CONVOCA_COMMON_URL . 'assets/css/convoca-common.css', array(), CONVOCA_COMMON_VERSION );
		}
	}

	/**
	 * Redirect standard post editor to custom editor.
	 */
	public function redirect_to_custom_editor() {
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'registro_hora' ) {
			if ( $screen->action === 'add' ) {
				wp_redirect( admin_url( 'admin.php?page=conv-horas-editor' ) );
				exit;
			} else {
				$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
				if ( $post_id ) {
					wp_redirect( admin_url( 'admin.php?page=conv-horas-editor&id=' . $post_id ) );
					exit;
				}
			}
		}
	}

	/**
	 * Handle save from admin form.
	 */
	public function handle_save_admin() {
		check_admin_referer( 'convoca_save_hours_nonce' );

		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'convoca-members' ) );
		}

		$data = array(
			'id'           => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'miembro_id'   => (int) $_POST['miembro_id'],
			'fecha'        => sanitize_text_field( $_POST['fecha'] ),
			'horas'        => (float) $_POST['horas'],
			'proyecto_id'  => (int) $_POST['proyecto_id'],
			'actividad_id' => (int) $_POST['actividad_id'],
			'tareas'       => sanitize_textarea_field( $_POST['tareas'] ),
			'descripcion'  => sanitize_textarea_field( $_POST['descripcion'] ),
			'estado'       => sanitize_text_field( $_POST['estado'] ),
			'nota_admin'   => sanitize_textarea_field( $_POST['nota_admin'] ),
		);

		// Validar autoridad de aprobación también en el editor admin.
		$estado_solicitado = $data['estado'];
		if ( in_array( $estado_solicitado, array( 'aprobada', 'rechazada' ), true ) ) {
			$current_user_id = get_current_user_id();
			$current_user    = wp_get_current_user();
			$current_roles   = $current_user ? (array) $current_user->roles : array();
			$is_admin        = in_array( 'administrator', $current_roles, true );
			$is_monitor      = in_array( 'monitor_actividad', $current_roles, true );

			// Identificar a qué usuario pertenece este registro.
			$record_user_id = 0;
			$record_id      = $data['id'];
			if ( $record_id ) {
				$record_user_id = (int) get_post_meta( $record_id, '_convoca_usuario_id', true );
			}
			if ( ! $record_user_id ) {
				// Nuevo registro — buscar el user_id a partir del miembro_id.
				$member_email = get_post_meta( $data['miembro_id'], '_convoca_email', true );
				if ( $member_email ) {
					$user = get_user_by( 'email', $member_email );
					if ( $user ) {
						$record_user_id = $user->ID;
					}
				}
			}

			$record_user       = $record_user_id ? get_userdata( $record_user_id ) : null;
			$record_roles      = $record_user ? (array) $record_user->roles : array();
			$record_is_monitor = in_array( 'monitor_actividad', $record_roles, true );

			if ( ! $is_admin ) {
				// No-admin aprobando/rechazando:.
				if ( $record_user_id > 0 && $record_user_id === $current_user_id ) {
					wp_die( __( 'No puedes aprobar o rechazar tus propias horas de voluntariado.', 'convoca-members' ) );
				}
				if ( $record_is_monitor ) {
					wp_die( __( 'Solo un administrador puede aprobar o rechazar las horas de los monitores.', 'convoca-members' ) );
				}
			}
		}

		$post_id = Hours_Manager::save_hours_record( $data, true );

		if ( is_wp_error( $post_id ) ) {
			wp_die( $post_id->get_error_message() );
		}

		wp_redirect( admin_url( 'admin.php?page=conv-volunteer-hours&message=saved' ) );
		exit;
	}

	public function render_editor_page() {
		$record_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$record    = $record_id ? get_post( $record_id ) : null;

		$miembro_id   = $record ? (int) get_post_meta( $record_id, '_convoca_member_id', true ) : 0;
		$fecha        = $record ? get_post_meta( $record_id, '_convoca_fecha', true ) : current_time( 'Y-m-d' );
		$horas        = $record ? get_post_meta( $record_id, '_convoca_horas', true ) : '';
		$proyecto_id  = $record ? (int) get_post_meta( $record_id, '_convoca_proyecto_id', true ) : 0;
		$actividad_id = $record ? (int) get_post_meta( $record_id, '_convoca_actividad_id', true ) : 0;
		$tareas       = $record ? get_post_meta( $record_id, '_convoca_tareas', true ) : '';
		$descripcion  = $record ? $record->post_content : '';
		$estado       = $record ? get_post_meta( $record_id, '_convoca_estado', true ) : 'pendiente';
		$nota_admin   = $record ? get_post_meta( $record_id, '_convoca_nota_admin', true ) : '';

		// Fetch options.
		$members  = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$projects = get_posts(
			array(
				'post_type'      => 'proyecto',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Fetch recent activities.
		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => 30,
				'post_status'    => 'publish',
				'meta_key'       => '_convoca_fecha_inicio',
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
			)
		);

		?>
		<div class="wrap">
			<h1><?php echo $record_id ? __( 'Editar Registro de Horas', 'convoca-members' ) : __( 'Añadir Registro de Horas', 'convoca-members' ); ?></h1>
			<hr class="wp-header-end">

			<div class="convoca-diagnostic" style="max-width: 800px; margin-top: 20px;">
				<div class="convoca-diagnostic-header">
					<div class="convoca-diagnostic-summary">
						<h3><?php esc_html_e( 'Datos del Registro', 'convoca-members' ); ?></h3>
						<p><?php esc_html_e( 'Introduce la información del voluntariado realizado.', 'convoca-members' ); ?></p>
					</div>
				</div>

				<div class="convoca-diagnostic-results" style="padding: 20px;">
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="convoca_save_hours_admin">
						<input type="hidden" name="id" value="<?php echo (int) $record_id; ?>">
						<?php wp_nonce_field( 'convoca_save_hours_nonce' ); ?>

						<div class="convoca-grid-2">
							<div class="convoca-field">
								<label for="miembro_id"><?php esc_html_e( 'Socio', 'convoca-members' ); ?></label>
								<select name="miembro_id" id="miembro_id" required>
									<option value=""><?php esc_html_e( '— Seleccionar socio —', 'convoca-members' ); ?></option>
									<?php foreach ( $members as $m ) : ?>
										<option value="<?php echo (int) $m->ID; ?>" <?php selected( $miembro_id, $m->ID ); ?>>
											<?php echo esc_html( $m->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="convoca-field">
								<label for="fecha"><?php esc_html_e( 'Fecha', 'convoca-members' ); ?></label>
								<input type="date" name="fecha" id="fecha" value="<?php echo esc_attr( $fecha ); ?>" required>
							</div>
						</div>

						<div class="convoca-grid-2">
							<div class="convoca-field">
								<label for="proyecto_id"><?php esc_html_e( 'Proyecto', 'convoca-members' ); ?></label>
								<select name="proyecto_id" id="proyecto_id" required>
									<option value=""><?php esc_html_e( '— Seleccionar proyecto —', 'convoca-members' ); ?></option>
									<?php foreach ( $projects as $p ) : ?>
										<option value="<?php echo (int) $p->ID; ?>" <?php selected( $proyecto_id, $p->ID ); ?>>
											<?php echo esc_html( $p->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="convoca-field">
								<label for="horas"><?php esc_html_e( 'Horas', 'convoca-members' ); ?></label>
								<input type="number" name="horas" id="horas" step="0.25" min="0.25" value="<?php echo esc_attr( $horas ); ?>" required>
							</div>
						</div>

						<div class="convoca-field">
							<label for="actividad_id"><?php esc_html_e( 'Actividad (Opcional)', 'convoca-members' ); ?></label>
							<select name="actividad_id" id="actividad_id">
								<option value="0"><?php esc_html_e( '— Ninguna actividad específica —', 'convoca-members' ); ?></option>
								<?php foreach ( $activities as $a ) : ?>
									<option value="<?php echo (int) $a->ID; ?>" <?php selected( $actividad_id, $a->ID ); ?>>
										<?php echo esc_html( $a->post_title ); ?> (<?php echo esc_html( \Convoca\Core\Utils::format_date( get_post_meta( $a->ID, '_convoca_fecha_inicio', true ), 'd/m/Y' ) ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="convoca-field">
							<label for="tareas"><?php esc_html_e( 'Tareas realizadas', 'convoca-members' ); ?></label>
							<textarea name="tareas" id="tareas" rows="2" placeholder="<?php esc_attr_e( 'Ej: Limpieza de sendero, Atención en mesa...', 'convoca-members' ); ?>" required><?php echo esc_textarea( $tareas ); ?></textarea>
						</div>

						<div class="convoca-field">
							<label for="descripcion"><?php esc_html_e( 'Descripción detallada', 'convoca-members' ); ?></label>
							<textarea name="descripcion" id="descripcion" rows="4"><?php echo esc_textarea( $descripcion ); ?></textarea>
						</div>

						<div class="convoca-grid-2">
							<div class="convoca-field">
								<label for="estado"><?php esc_html_e( 'Estado', 'convoca-members' ); ?></label>
								<select name="estado" id="estado">
									<option value="pendiente" <?php selected( $estado, 'pendiente' ); ?>><?php esc_html_e( 'Pendiente', 'convoca-members' ); ?></option>
									<option value="aprobada" <?php selected( $estado, 'aprobada' ); ?>><?php esc_html_e( 'Aprobada', 'convoca-members' ); ?></option>
									<option value="rechazada" <?php selected( $estado, 'rechazada' ); ?>><?php esc_html_e( 'Rechazada', 'convoca-members' ); ?></option>
								</select>
							</div>
							<div class="convoca-field">
								<label for="nota_admin"><?php esc_html_e( 'Nota interna (Admin)', 'convoca-members' ); ?></label>
								<input type="text" name="nota_admin" id="nota_admin" value="<?php echo esc_attr( $nota_admin ); ?>">
							</div>
						</div>

						<div style="margin-top: 20px; display: flex; gap: 10px;">
							<button type="submit" class="convoca-btn convoca-btn-primary">
								<?php echo $record_id ? __( 'Guardar Cambios', 'convoca-members' ) : __( 'Crear Registro', 'convoca-members' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-volunteer-hours' ) ); ?>" class="convoca-btn convoca-btn-outline">
								<?php esc_html_e( 'Cancelar', 'convoca-members' ); ?>
							</a>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_page() {
		$this->init_table();
		$this->prepare_items();

		$proyectos       = CPT_Proyecto::get_active_proyectos( true );
		$filter_proyecto = isset( $_GET['filter_proyecto'] ) ? (int) $_GET['filter_proyecto'] : 0;

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Horas de Voluntariado', 'convoca-members' ); ?></h1>
			<a href="<?php echo admin_url( 'post-new.php?post_type=registro_hora' ); ?>" class="page-title-action">
				<?php _e( 'Añadir Registro', 'convoca-members' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_export_horas_csv&filter_proyecto=' . ( $filter_proyecto ?: '' ) ), 'convoca_export_horas' ) ); ?>" class="convoca-btn convoca-btn-outline" style="margin-left:10px;">
				📥 <?php _e( 'Exportar CSV', 'convoca-members' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="post_type" value="miembro">
				<input type="hidden" name="page" value="conv-volunteer-hours">
				
				<div class="tablenav top">
					<div class="alignleft actions">
						<select name="filter_proyecto">
							<option value="0"><?php _e( 'Todos los proyectos', 'convoca-members' ); ?></option>
							<?php foreach ( $proyectos as $id => $title ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_proyecto, $id ); ?>>
									<?php echo esc_html( $title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="submit" class="convoca-btn convoca-btn-outline" value="<?php _e( 'Filtrar', 'convoca-members' ); ?>">
					</div>
					<?php $this->search_box( __( 'Buscar registros', 'convoca-members' ), 'horas' ); ?>
				</div>
				
				<?php $this->display(); ?>
			</form>
		</div>
		<style>
			.column-horas { width: 80px; }
			.column-estado { width: 120px; }
			.column-proyecto { width: 150px; }
			.column-tareas { width: 200px; }
			.status-pendiente { color: #d63638; font-weight: bold; }
			.status-aprobada { color: #00a32a; font-weight: bold; }
			.status-rechazada { color: #646970; }
		</style>
		<?php
	}

	public function handle_export_csv(): void {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_export_horas' ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$filter_proyecto = isset( $_GET['filter_proyecto'] ) ? (int) $_GET['filter_proyecto'] : 0;
		$search          = sanitize_text_field( $_GET['s'] ?? '' );

		$args = array(
			'post_type'      => 'registro_hora',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( $filter_proyecto > 0 ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_convoca_proyecto_id',
					'value' => $filter_proyecto,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$filename = 'convoca-horas-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$out,
			array(
				__( 'Fecha', 'convoca-members' ),
				__( 'Socio', 'convoca-members' ),
				__( 'Proyecto', 'convoca-members' ),
				__( 'Tareas', 'convoca-members' ),
				__( 'Actividad', 'convoca-members' ),
				__( 'Horas', 'convoca-members' ),
				__( 'Descripción', 'convoca-members' ),
				__( 'Estado', 'convoca-members' ),
			)
		);

		foreach ( $query->posts as $post ) {
			$socio_id = get_post_meta( $post->ID, '_convoca_member_id', true );
			$socio    = $socio_id ? ( get_post( $socio_id )?->post_title ?? '' ) : '';

			$proyecto_id = get_post_meta( $post->ID, '_convoca_proyecto_id', true );
			$proyecto    = $proyecto_id ? ( get_post( $proyecto_id )?->post_title ?? '' ) : '';

			$act_id    = get_post_meta( $post->ID, '_convoca_actividad_id', true );
			$actividad = $act_id ? ( get_post( $act_id )?->post_title ?? '' ) : '';

			fputcsv(
				$out,
				array(
					get_post_meta( $post->ID, '_convoca_fecha', true ) ?: get_the_date( 'Y-m-d', $post->ID ),
					\Convoca\Core\Utils::escape_csv_field( $socio ),
					\Convoca\Core\Utils::escape_csv_field( $proyecto ),
					\Convoca\Core\Utils::escape_csv_field( get_post_meta( $post->ID, '_convoca_tareas', true ) ),
					\Convoca\Core\Utils::escape_csv_field( $actividad ),
					get_post_meta( $post->ID, '_convoca_horas', true ),
					\Convoca\Core\Utils::escape_csv_field( $post->post_content ),
					get_post_meta( $post->ID, '_convoca_estado', true ) ?: 'pendiente',
				)
			);
		}

		fclose( $out );
		exit;
	}

	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'fecha'       => __( 'Fecha', 'convoca-members' ),
			'socio'       => __( 'Socio', 'convoca-members' ),
			'proyecto'    => __( 'Proyecto', 'convoca-members' ),
			'tareas'      => __( 'Tareas', 'convoca-members' ),
			'actividad'   => __( 'Actividad', 'convoca-members' ),
			'horas'       => __( 'Horas', 'convoca-members' ),
			'descripcion' => __( 'Descripción', 'convoca-members' ),
			'estado'      => __( 'Estado', 'convoca-members' ),
			'acciones'    => __( 'Acciones', 'convoca-members' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'fecha' => array( 'fecha', true ),
			'socio' => array( 'socio', false ),
			'horas' => array( 'horas', false ),
		);
	}

	public function prepare_items() {
		$per_page        = 20;
		$current_page    = $this->get_pagenum();
		$search          = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$filter_proyecto = isset( $_REQUEST['filter_proyecto'] ) ? (int) $_REQUEST['filter_proyecto'] : 0;

		$args = array(
			'post_type'      => 'registro_hora',
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'offset'         => ( $current_page - 1 ) * $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $search ) {
			$args['s'] = $search;
		}

		if ( $filter_proyecto > 0 ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_convoca_proyecto_id',
					'value' => $filter_proyecto,
				),
			);
		}

		$query       = new \WP_Query( $args );
		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => ceil( $query->found_posts / $per_page ),
			)
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID );
	}

	public function column_fecha( $item ) {
		$fecha = get_post_meta( $item->ID, '_convoca_fecha', true );
		return $fecha ?: get_the_date( '', $item->ID );
	}

	public function column_socio( $item ) {
		$socio_id = get_post_meta( $item->ID, '_convoca_member_id', true );
		if ( ! $socio_id ) {
			return '—';
		}
		$socio = get_post( $socio_id );
		if ( ! $socio ) {
			return 'Socio eliminado';
		}

		return sprintf(
			'<a href="%s"><strong>%s</strong></a>',
			esc_url( get_edit_post_link( $socio_id ) ),
			esc_html( $socio->post_title )
		);
	}

	public function column_actividad( $item ) {
		$act_id = get_post_meta( $item->ID, '_convoca_actividad_id', true );
		if ( ! $act_id ) {
			return 'General/Otros';
		}
		$act = get_post( $act_id );
		if ( ! $act ) {
			return 'Actividad eliminada';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $act_id ) ),
			esc_html( $act->post_title )
		);
	}

	public function column_proyecto( $item ) {
		$proyecto_id = get_post_meta( $item->ID, '_convoca_proyecto_id', true );
		if ( ! $proyecto_id ) {
			return '—';
		}
		$proyecto = get_post( $proyecto_id );
		if ( ! $proyecto ) {
			return 'Proyecto eliminado';
		}

		return esc_html( $proyecto->post_title );
	}

	public function column_tareas( $item ) {
		$tareas = get_post_meta( $item->ID, '_convoca_tareas', true );
		if ( ! $tareas ) {
			return '—';
		}
		return esc_html( mb_substr( $tareas, 0, 60 ) ) . ( mb_strlen( $tareas ) > 60 ? '...' : '' );
	}

	public function column_horas( $item ) {
		return get_post_meta( $item->ID, '_convoca_horas', true ) . 'h';
	}

	public function column_descripcion( $item ) {
		return esc_html( $item->post_content );
	}

	public function column_estado( $item ) {
		$estado = get_post_meta( $item->ID, '_convoca_estado', true ) ?: 'pendiente';
		$class  = 'status-' . $estado;
		$label  = ucfirst( $estado );

		return sprintf( '<span class="%s">%s</span>', $class, $label );
	}

	public function column_acciones( $item ) {
		$estado  = get_post_meta( $item->ID, '_convoca_estado', true ) ?: 'pendiente';
		$actions = array();

		if ( $estado !== 'aprobada' ) {
			$url       = admin_url( 'admin-post.php?action=convoca_process_horas&record_id=' . $item->ID . '&new_status=aprobada' );
			$url       = wp_nonce_url( $url, 'process_horas_' . $item->ID );
			$actions[] = sprintf( '<a href="%s" style="color: green;">%s</a>', esc_url( $url ), esc_html__( 'Aprobar', 'convoca-members' ) );
		}

		if ( $estado !== 'rechazada' ) {
			$url       = admin_url( 'admin-post.php?action=convoca_process_horas&record_id=' . $item->ID . '&new_status=rechazada' );
			$url       = wp_nonce_url( $url, 'process_horas_' . $item->ID );
			$actions[] = sprintf( '<a href="%s" style="color: red;">%s</a>', esc_url( $url ), esc_html__( 'Rechazar', 'convoca-members' ) );
		}

		return implode( ' | ', $actions );
	}

	public function process_action() {
		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$get_data   = wp_unslash( $_GET );
		$record_id  = (int) ( $get_data['record_id'] ?? 0 );
		$new_status = sanitize_text_field( $get_data['new_status'] ?? '' );

		check_admin_referer( 'process_horas_' . $record_id );

		if ( $record_id && in_array( $new_status, array( 'aprobada', 'rechazada' ) ) ) {
			$miembro_id      = (int) get_post_meta( $record_id, '_convoca_member_id', true );
			$current_user_id = get_current_user_id();

			// Robust identification of the volunteer user ID.
			$volunteer_user_id = (int) get_post_meta( $record_id, '_convoca_usuario_id', true );

			// Fallback: Check linked member profile for an email/user.
			if ( ! $volunteer_user_id && $miembro_id ) {
				$member_email = get_post_meta( $miembro_id, '_convoca_email', true );
				if ( $member_email ) {
					$user = get_user_by( 'email', $member_email );
					if ( $user ) {
						$volunteer_user_id = $user->ID;
					}
				}
			}

			// Final fallback: check post author (legacy or secondary check).
			if ( ! $volunteer_user_id && $miembro_id ) {
				$member_post = get_post( $miembro_id );
				if ( $member_post && $member_post->post_author ) {
					$volunteer_user_id = (int) $member_post->post_author;
				}
			}

			// --- Approval authority check ---
			$current_user  = wp_get_current_user();
			$current_roles = $current_user ? (array) $current_user->roles : array();
			$is_admin      = in_array( 'administrator', $current_roles, true );
			$is_monitor    = in_array( 'monitor_actividad', $current_roles, true );

			// Determine the submitting user's roles to see if they are a monitor.
			$volunteer_user  = $volunteer_user_id ? get_userdata( $volunteer_user_id ) : null;
			$volunteer_roles = $volunteer_user ? (array) $volunteer_user->roles : array();
			$vol_is_monitor  = in_array( 'monitor_actividad', $volunteer_roles, true );

			if ( $is_admin ) {
				// Admin puede aprobar cualquier hora (propias inclusive).
				// no-op.
			} elseif ( $is_monitor ) {
				// Monitor no puede aprobar sus propias horas.
				if ( $volunteer_user_id > 0 && $volunteer_user_id === $current_user_id ) {
					\Convoca\Core\Logger::warning(
						"Monitor intentó auto-aprobarse horas. Usuario: $current_user_id, Registro: $record_id",
						'Members/Hours',
						$miembro_id
					);
					wp_die( __( 'No puedes aprobar tus propias horas de voluntariado.', 'convoca-members' ) );
				}
				// Monitor no puede aprobar horas de otro monitor.
				if ( $vol_is_monitor ) {
					\Convoca\Core\Logger::warning(
						"Monitor intentó aprobar horas de otro monitor. Aprobador: $current_user_id, Objetivo: $volunteer_user_id, Registro: $record_id",
						'Members/Hours',
						$miembro_id
					);
					wp_die( __( 'Solo un administrador puede aprobar las horas de los monitores.', 'convoca-members' ) );
				}
			} else {
				// Otros roles con gestionar_miembros (p.ej. shop_manager) → mismo límite que monitor.
				if ( $volunteer_user_id > 0 && $volunteer_user_id === $current_user_id ) {
					wp_die( __( 'No puedes aprobar tus propias horas de voluntariado.', 'convoca-members' ) );
				}
				if ( $vol_is_monitor ) {
					wp_die( __( 'Solo un administrador puede aprobar las horas de los monitores.', 'convoca-members' ) );
				}
			}

			update_post_meta( $record_id, '_convoca_estado', $new_status );
			update_post_meta( $record_id, '_convoca_aprobada_por', get_current_user_id() );

			if ( $new_status === 'aprobada' ) {
				do_action( 'convoca_members_hora_aprobada', $record_id, $miembro_id );
			} elseif ( $new_status === 'rechazada' ) {
				do_action( 'convoca_members_hora_rechazada', $record_id, $miembro_id );
			}

			// Log activity.
			$socio_id = get_post_meta( $record_id, '_convoca_member_id', true );
			\Convoca\Core\Logger::log( "Registro de horas $record_id marcado como $new_status por admin " . get_current_user_id(), 'Members/Hours', $socio_id );
		}

		wp_redirect( admin_url( 'edit.php?post_type=miembro&page=conv-volunteer-hours' ) );
		exit;
	}
}
