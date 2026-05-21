<?php
/**
 * Custom Post Type: registro_hora for volunteering hours tracking.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Registro_Hora {

	public const POST_TYPE = 'registro_hora';

	/** Meta keys for registro_hora records. */
	public const META_KEYS = array(
		'miembro_id',
		'fecha',
		'horas',
		'actividad_id',
		'estado',
		'aprobada_por',
		'proyecto_id',
		'tareas',
	);

	/**
	 * Register the CPT.
	 */
	public static function register(): void {
		$labels = array(
			'name'                  => _x( 'Registros de Horas', 'Post Type General Name', 'convoca-members' ),
			'singular_name'         => _x( 'Registro de Hora', 'Post Type Singular Name', 'convoca-members' ),
			'menu_name'             => __( 'Horas Voluntariado', 'convoca-members' ),
			'name_admin_bar'        => __( 'Registro de Hora', 'convoca-members' ),
			'archives'              => __( 'Archivo de Registros', 'convoca-members' ),
			'attributes'            => __( 'Atributos de Registro', 'convoca-members' ),
			'parent_item_colon'     => __( 'Registro Padre:', 'convoca-members' ),
			'all_items'             => __( 'Todos los Registros', 'convoca-members' ),
			'add_new_item'          => __( 'Añadir Nuevo Registro', 'convoca-members' ),
			'add_new'               => __( 'Añadir Nuevo', 'convoca-members' ),
			'new_item'              => __( 'Nuevo Registro', 'convoca-members' ),
			'edit_item'             => __( 'Editar Registro', 'convoca-members' ),
			'update_item'           => __( 'Actualizar Registro', 'convoca-members' ),
			'view_item'             => __( 'Ver Registro', 'convoca-members' ),
			'view_items'            => __( 'Ver Registros', 'convoca-members' ),
			'search_items'          => __( 'Buscar Registro', 'convoca-members' ),
			'not_found'             => __( 'No encontrado', 'convoca-members' ),
			'not_found_in_trash'    => __( 'No encontrado en la papelera', 'convoca-members' ),
			'featured_image'        => __( 'Imagen Destacada', 'convoca-members' ),
			'set_featured_image'    => __( 'Establecer imagen destacada', 'convoca-members' ),
			'remove_featured_image' => __( 'Borrar imagen destacada', 'convoca-members' ),
			'use_featured_image'    => __( 'Usar como imagen destacada', 'convoca-members' ),
			'insert_into_item'      => __( 'Insertar en el registro', 'convoca-members' ),
			'uploaded_to_this_item' => __( 'Subido a este registro', 'convoca-members' ),
			'items_list'            => __( 'Lista de registros', 'convoca-members' ),
			'items_list_navigation' => __( 'Navegación de lista de registros', 'convoca-members' ),
			'filter_items_list'     => __( 'Filtrar lista de registros', 'convoca-members' ),
		);

		$args = array(
			'label'               => __( 'Registro de Hora', 'convoca-members' ),
			'description'         => __( 'Seguimiento de horas de voluntariado.', 'convoca-members' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => 5,
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
}
