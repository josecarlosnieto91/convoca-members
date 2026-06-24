<?php
/**
 * Custom Post Type: proyecto for volunteer projects.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Proyecto {

	public const POST_TYPE = 'proyecto';

	/** Meta keys for proyecto records. */
	public const META_KEYS = array(
		'fecha_inicio',
		'fecha_fin',
		'fecha_baja',
		'responsable',
		'activo',
	);

	/**
	 * Register the CPT.
	 */
	public static function register(): void {
		$labels = array(
			'name'                  => _x( 'Proyectos', 'Post Type General Name', 'convoca-members' ),
			'singular_name'         => _x( 'Proyecto', 'Post Type Singular Name', 'convoca-members' ),
			'menu_name'             => __( 'Proyectos', 'convoca-members' ),
			'name_admin_bar'        => __( 'Proyecto', 'convoca-members' ),
			'archives'              => __( 'Archivo de Proyectos', 'convoca-members' ),
			'attributes'            => __( 'Atributos de Proyecto', 'convoca-members' ),
			'parent_item_colon'     => __( 'Proyecto Padre:', 'convoca-members' ),
			'all_items'             => __( 'Todos los Proyectos', 'convoca-members' ),
			'add_new_item'          => __( 'Añadir Nuevo Proyecto', 'convoca-members' ),
			'add_new'               => __( 'Añadir Nuevo', 'convoca-members' ),
			'new_item'              => __( 'Nuevo Proyecto', 'convoca-members' ),
			'edit_item'             => __( 'Editar Proyecto', 'convoca-members' ),
			'update_item'           => __( 'Actualizar Proyecto', 'convoca-members' ),
			'view_item'             => __( 'Ver Proyecto', 'convoca-members' ),
			'view_items'            => __( 'Ver Proyectos', 'convoca-members' ),
			'search_items'          => __( 'Buscar Proyecto', 'convoca-members' ),
			'not_found'             => __( 'No encontrado', 'convoca-members' ),
			'not_found_in_trash'    => __( 'No encontrado en la papelera', 'convoca-members' ),
			'featured_image'        => __( 'Imagen Destacada', 'convoca-members' ),
			'set_featured_image'    => __( 'Establecer imagen destacada', 'convoca-members' ),
			'remove_featured_image' => __( 'Borrar imagen destacada', 'convoca-members' ),
			'use_featured_image'    => __( 'Usar como imagen destacada', 'convoca-members' ),
			'insert_into_item'      => __( 'Insertar en el proyecto', 'convoca-members' ),
			'uploaded_to_this_item' => __( 'Subido a este proyecto', 'convoca-members' ),
			'items_list'            => __( 'Lista de proyectos', 'convoca-members' ),
			'items_list_navigation' => __( 'Navegación de lista de proyectos', 'convoca-members' ),
			'filter_items_list'     => __( 'Filtrar lista de proyectos', 'convoca-members' ),
		);

		$args = array(
			'label'               => __( 'Proyecto', 'convoca-members' ),
			'description'         => __( 'Proyectos de voluntariado.', 'convoca-members' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'author', 'revisions' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => 6,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get meta data for a proyecto.
	 */
	public static function get_meta( int $post_id ): array {
		$data = array();
		foreach ( self::META_KEYS as $key ) {
			$data[ $key ] = get_post_meta( $post_id, '_convoca_' . $key, true );
		}
		return $data;
	}

	/**
	 * Get all active proyectos for dropdown select.
	 */
	public static function get_active_proyectos( bool $include_all = false ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => $include_all ? 'any' : 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$proyectos = get_posts( $args );

		$result = array();
		$now    = current_time( 'mysql' );

		foreach ( $proyectos as $p ) {
			$meta      = self::get_meta( $p->ID );
			$fecha_fin = $meta['fecha_fin'] ?? '';

			if ( ! $include_all && ! empty( $fecha_fin ) && $fecha_fin < $now ) {
				continue;
			}

			$result[ $p->ID ] = $p->post_title;
		}

		return $result;
	}

	/**
	 * Get proyectos where a member has participated.
	 */
	public static function get_proyectos_by_member( int $miembro_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id as proyecto_id, p.post_title, p.post_content
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} ph ON ph.post_id = pm.post_id AND ph.meta_key = '_convoca_member_id' AND ph.meta_value = %d
             WHERE pm.meta_key = '_convoca_proyecto_id'
             AND pm.meta_value = p.ID
             AND p.post_type = %s
             AND p.post_status = 'publish'
             ORDER BY p.post_title ASC",
				$miembro_id,
				self::POST_TYPE
			),
			ARRAY_A
		);

		$proyectos = array();
		foreach ( $results as $r ) {
			$proyectos[] = array(
				'id'          => $r['proyecto_id'],
				'titulo'      => $r['post_title'],
				'descripcion' => $r['post_content'],
				'meta'        => self::get_meta( (int) $r['proyecto_id'] ),
			);
		}

		return $proyectos;
	}

	/**
	 * Render metabox for proyecto details.
	 */
	public static function render_metabox( \WP_Post $post ): void {
		$post_id = $post->ID;
		$meta    = self::get_meta( $post_id );
		?>
		<div class="conv-proyecto-metabox">
			<?php wp_nonce_field( 'convoca_save_proyecto', 'convoca_proyecto_nonce' ); ?>
			<p>
				<label for="conv_fecha_inicio"><?php esc_html_e( 'Fecha de inicio:', 'convoca-members' ); ?></label>
				<input type="date" id="conv_fecha_inicio" name="conv_fecha_inicio" 
						value="<?php echo esc_attr( $meta['fecha_inicio'] ?? '' ); ?>" class="widefat">
			</p>
			<p>
				<label for="conv_fecha_fin"><?php esc_html_e( 'Fecha de fin:', 'convoca-members' ); ?></label>
				<input type="date" id="conv_fecha_fin" name="conv_fecha_fin" 
						value="<?php echo esc_attr( $meta['fecha_fin'] ?? '' ); ?>" class="widefat">
			</p>
			<p>
				<label for="conv_fecha_baja"><?php esc_html_e( 'Fecha de baja (archivo):', 'convoca-members' ); ?></label>
				<input type="date" id="conv_fecha_baja" name="conv_fecha_baja" 
						value="<?php echo esc_attr( $meta['fecha_baja'] ?? '' ); ?>" class="widefat">
				<small><?php esc_html_e( 'Fecha en la que el proyecto se considera archivado definitivamente.', 'convoca-members' ); ?></small>
			</p>
			<p>
				<label for="conv_responsable"><?php esc_html_e( 'Responsable:', 'convoca-members' ); ?></label>
				<select id="conv_responsable" name="conv_responsable" class="widefat">
					<option value=""><?php esc_html_e( '— Seleccionar —', 'convoca-members' ); ?></option>
					<?php
					$users = get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) );
					foreach ( $users as $user ) {
						echo '<option value="' . esc_attr( $user->ID ) . '"' .
							selected( $meta['responsable'] ?? '', $user->ID, false ) . '>' .
							esc_html( $user->display_name ) . '</option>';
					}
					?>
				</select>
			</p>
			<p>
				<label>
					<input type="checkbox" name="conv_activo" value="1" 
							<?php checked( $meta['activo'] ?? '', '1' ); ?>>
					<?php esc_html_e( 'Proyecto activo (visible para voluntarios)', 'convoca-members' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 */
	public static function save_metabox( int $post_id ): void {
		if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
			return;
		}

		if ( ! isset( $_POST['convoca_proyecto_nonce'] ) || ! wp_verify_nonce( $_POST['convoca_proyecto_nonce'], 'convoca_save_proyecto' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_convoca_fecha_inicio', sanitize_text_field( $_POST['convoca_fecha_inicio'] ?? '' ) );
		update_post_meta( $post_id, '_convoca_fecha_fin', sanitize_text_field( $_POST['convoca_fecha_fin'] ?? '' ) );
		update_post_meta( $post_id, '_convoca_fecha_baja', sanitize_text_field( $_POST['convoca_fecha_baja'] ?? '' ) );
		update_post_meta( $post_id, '_convoca_responsable', sanitize_text_field( $_POST['convoca_responsable'] ?? '' ) );
		update_post_meta( $post_id, '_convoca_activo', ! empty( $_POST['convoca_activo'] ) ? '1' : '0' );
	}
}