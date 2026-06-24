<?php
/**
 * Admin view for volunteer projects.
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

class Admin_Proyectos extends \WP_List_Table {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_convoca_save_proyecto_admin', array( $this, 'handle_save_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'load-post.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 80 );
		add_action( 'admin_post_convoca_export_proyectos_csv', array( $this, 'handle_export_csv' ) );
	}

	/**
	 * Enqueue assets for the custom editor.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'conv-proyecto-editor' ) !== false ) {
			wp_enqueue_style( 'convoca-core', CONVOCA_COMMON_URL . 'assets/css/convoca-common.css', array(), CONVOCA_COMMON_VERSION );
		}
	}

	/**
	 * Redirect standard post editor to custom editor.
	 */
	public function redirect_to_custom_editor() {
		$screen    = get_current_screen();
		$post_type = $_GET['post_type'] ?? '';
		if ( ! $post_type && isset( $_GET['post'] ) ) {
			$post_type = get_post_type( $_GET['post'] );
		}

		if ( ( $screen && $screen->id === CPT_Proyecto::POST_TYPE ) || $post_type === CPT_Proyecto::POST_TYPE ) {
			if ( isset( $screen->action ) && $screen->action === 'add' || strpos( $_SERVER['REQUEST_URI'], 'post-new.php' ) !== false ) {
				wp_redirect( admin_url( 'admin.php?page=conv-proyecto-editor' ) );
				exit;
			} else {
				$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
				if ( $post_id && strpos( $_SERVER['REQUEST_URI'], 'post.php' ) !== false ) {
					wp_redirect( admin_url( 'admin.php?page=conv-proyecto-editor&id=' . $post_id ) );
					exit;
				}
			}
		}
	}

	public function customize_admin_bar( \WP_Admin_Bar $wp_admin_bar ): void {
		$node_id = 'new-proyecto';
		$node    = $wp_admin_bar->get_node( $node_id );
		if ( $node ) {
			$node->href = admin_url( 'admin.php?page=conv-proyecto-editor' );
			$wp_admin_bar->add_node( $node );
		}
	}

	/**
	 * Initialize WP_List_Table (must be called after admin screen is available).
	 */
	private function init_table(): void {
		parent::__construct(
			array(
				'singular' => 'proyecto',
				'plural'   => 'proyectos',
				'ajax'     => false,
			)
		);
	}

	public function add_menu() {
		add_submenu_page(
			'conv-members',
			__( 'Proyectos', 'convoca-members' ),
			__( 'Proyectos', 'convoca-members' ),
			'gestionar_miembros',
			'conv-proyectos',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			null, // Hidden from menu.
			__( 'Editor de Proyecto', 'convoca-members' ),
			__( 'Editor de Proyecto', 'convoca-members' ),
			'gestionar_miembros',
			'conv-proyecto-editor',
			array( $this, 'render_editor' )
		);
	}

	public function render_page() {
		$this->init_table();
		$this->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Proyectos de Voluntariado', 'convoca-members' ); ?></h1>
			<a href="<?php echo admin_url( 'post-new.php?post_type=proyecto' ); ?>" class="page-title-action">
				<?php _e( 'Añadir nuevo', 'convoca-members' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_export_proyectos_csv' ), 'convoca_export_proyectos' ) ); ?>" class="convoca-btn convoca-btn-outline" style="margin-left:10px;">
				📥 <?php _e( 'Exportar CSV', 'convoca-members' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="post_type" value="miembro">
				<input type="hidden" name="page" value="conv-proyectos">
				<?php
				$this->search_box( __( 'Buscar proyectos', 'convoca-members' ), 'proyecto' );
				$this->display();
				?>
			</form>
		</div>
		<style>
			.column-fecha_inicio, .column-fecha_fin { width: 120px; }
			.column-responsable { width: 150px; }
			.column-activo { width: 80px; }
		</style>
		<?php
	}

	public function handle_export_csv(): void {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_export_proyectos' ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$args = array(
			'post_type'      => CPT_Proyecto::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$search = sanitize_text_field( $_GET['s'] ?? '' );
		if ( $search ) {
			$args['s'] = $search;
		}

		$query    = new \WP_Query( $args );
		$filename = 'convoca-proyectos-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$out,
			array(
				__( 'Título', 'convoca-members' ),
				__( 'Inicio', 'convoca-members' ),
				__( 'Fin', 'convoca-members' ),
				__( 'Responsable', 'convoca-members' ),
				__( 'Activo', 'convoca-members' ),
				__( 'Descripción', 'convoca-members' ),
			)
		);

		foreach ( $query->posts as $post ) {
			$responsable_id = (int) get_post_meta( $post->ID, '_convoca_responsable', true );
			$responsable    = $responsable_id ? ( get_userdata( $responsable_id )?->display_name ?? '' ) : '';
			fputcsv(
				$out,
				array(
					\Convoca\Core\Utils::escape_csv_field( $post->post_title ),
					get_post_meta( $post->ID, '_convoca_fecha_inicio', true ),
					get_post_meta( $post->ID, '_convoca_fecha_fin', true ),
					$responsable,
					get_post_meta( $post->ID, '_convoca_activo', true ) === '1' ? __( 'Sí', 'convoca-members' ) : __( 'No', 'convoca-members' ),
					\Convoca\Core\Utils::escape_csv_field( wp_trim_words( $post->post_content, 30 ) ),
				)
			);
		}

		fclose( $out );
		exit;
	}

	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Título', 'convoca-members' ),
			'fecha_inicio' => __( 'Inicio', 'convoca-members' ),
			'fecha_fin'    => __( 'Fin', 'convoca-members' ),
			'responsable'  => __( 'Responsable', 'convoca-members' ),
			'activo'       => __( 'Activo', 'convoca-members' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'title'        => array( 'title', false ),
			'fecha_inicio' => array( 'fecha_inicio', false ),
			'fecha_fin'    => array( 'fecha_fin', false ),
		);
	}

	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

		$args = array(
			'post_type'      => CPT_Proyecto::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $per_page,
			'offset'         => ( $current_page - 1 ) * $per_page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $search ) {
			$args['s'] = $search;
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

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID );
	}

	public function column_default( $item, $column_name ) {
		$meta = CPT_Proyecto::get_meta( $item->ID );

		switch ( $column_name ) {
			case 'title':
				$edit_link = get_edit_post_link( $item->ID );
				return sprintf(
					'<strong><a href="%s">%s</a></strong>',
					$edit_link,
					esc_html( $item->post_title )
				);

			case 'fecha_inicio':
				return $meta['fecha_inicio'] ?? '—';

			case 'fecha_fin':
				$fin = $meta['fecha_fin'] ?? '';
				if ( $fin && $fin < current_time( 'mysql' ) ) {
					return '<span style="color:#d63638;">' . esc_html( $fin ) . '</span>';
				}
				return $fin ?: '—';

			case 'responsable':
				$resp_id = $meta['responsable'] ?? '';
				if ( $resp_id ) {
					$user = get_userdata( $resp_id );
					return $user ? esc_html( $user->display_name ) : '—';
				}
				return '—';

			case 'activo':
				$activo = $meta['activo'] ?? '0';
				return $activo === '1'
					? '<span style="color:#00a32a;">✓ ' . __( 'Sí', 'convoca-members' ) . '</span>'
					: '<span style="color:#646970;">—</span>';

			default:
				return '';
		}
	}

	public function no_items() {
		_e( 'No se encontraron proyectos.', 'convoca-members' );
	}

	/**
	 * Render the custom editor page.
	 */
	public function render_editor() {
		$post_id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$proyecto = $post_id ? get_post( $post_id ) : null;
		$meta     = $post_id ? CPT_Proyecto::get_meta( $post_id ) : array(
			'fecha_inicio' => wp_date( 'Y-m-d' ),
			'fecha_fin'    => '',
			'responsable'  => get_current_user_id(),
			'activo'       => '1',
		);

		$title = $proyecto ? __( 'Editar Proyecto', 'convoca-members' ) : __( 'Nuevo Proyecto', 'convoca-members' );
		$users = get_users( array( 'role__in' => array( 'administrator', 'shop_manager', 'monitor' ) ) );

		?>
		<div class="wrap convoca-admin">
			<h1><?php echo esc_html( $title ); ?></h1>

			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="conv-form-custom">
				<input type="hidden" name="action" value="convoca_save_proyecto_admin">
				<input type="hidden" name="id" value="<?php echo $post_id; ?>">
				<?php wp_nonce_field( 'convoca_save_proyecto_nonce' ); ?>

				<div class="conv-grid conv-grid--2">
					<div class="conv-card">
						<div class="conv-card-header">
							<h2><?php _e( 'Información General', 'convoca-members' ); ?></h2>
						</div>
						<div class="conv-card-body">
							<div class="conv-field">
								<label for="title"><?php _e( 'Nombre del Proyecto', 'convoca-members' ); ?> *</label>
								<input type="text" name="post_title" id="title" value="<?php echo $proyecto ? esc_attr( $proyecto->post_title ) : ''; ?>" required class="widefat">
							</div>

							<div class="convoca-field">
								<label for="description"><?php _e( 'Descripción / Objetivos', 'convoca-members' ); ?></label>
								<textarea name="post_content" id="description" rows="10"><?php echo $proyecto ? esc_textarea( $proyecto->post_content ) : ''; ?></textarea>
							</div>
						</div>
					</div>

					<div class="conv-card">
						<div class="conv-card-header">
							<h2><?php _e( 'Configuración y Fechas', 'convoca-members' ); ?></h2>
						</div>
						<div class="conv-card-body">
							<div class="conv-field">
								<label for="fecha_inicio"><?php _e( 'Fecha de Inicio', 'convoca-members' ); ?> *</label>
								<input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo esc_attr( $meta['fecha_inicio'] ); ?>" required>
							</div>

							<div class="conv-field">
								<label for="fecha_fin"><?php _e( 'Fecha de Fin (opcional)', 'convoca-members' ); ?></label>
								<input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo esc_attr( $meta['fecha_fin'] ); ?>">
							</div>

							<div class="conv-field">
								<label for="responsable"><?php _e( 'Responsable', 'convoca-members' ); ?></label>
								<select name="responsable" id="responsable">
									<?php foreach ( $users as $user ) : ?>
										<option value="<?php echo $user->ID; ?>" <?php selected( $meta['responsable'], $user->ID ); ?>>
											<?php echo esc_html( $user->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="conv-field">
								<label>
									<input type="checkbox" name="activo" value="1" <?php checked( $meta['activo'], '1' ); ?>>
									<?php _e( 'Proyecto Activo', 'convoca-members' ); ?>
								</label>
							</div>
						</div>
					</div>
				</div>

				<div class="conv-form-actions">
					<?php submit_button( __( 'Guardar Proyecto', 'convoca-members' ), 'primary', 'submit', false ); ?>
					<a href="<?php echo admin_url( 'admin.php?page=conv-proyectos' ); ?>" class="button"><?php _e( 'Cancelar', 'convoca-members' ); ?></a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save from admin form.
	 */
	public function handle_save_admin() {
		check_admin_referer( 'convoca_save_proyecto_nonce' );

		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'convoca-members' ) );
		}

		$post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$title   = sanitize_text_field( $_POST['post_title'] );
		$content = wp_kses_post( $_POST['post_content'] );

		$post_data = array(
			'post_type'    => CPT_Proyecto::POST_TYPE,
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
		);

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data );
		} else {
			$result  = wp_insert_post( $post_data );
			$post_id = $result;
		}

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		// Save Meta.
		update_post_meta( $post_id, '_convoca_fecha_inicio', sanitize_text_field( $_POST['fecha_inicio'] ) );
		update_post_meta( $post_id, '_convoca_fecha_fin', sanitize_text_field( $_POST['fecha_fin'] ) );
		update_post_meta( $post_id, '_convoca_responsable', (int) $_POST['responsable'] );
		update_post_meta( $post_id, '_convoca_activo', isset( $_POST['activo'] ) ? '1' : '0' );

		wp_redirect( admin_url( 'admin.php?page=conv-proyectos&message=saved' ) );
		exit;
	}
}