<?php
/**
 * Custom Post Type for Documents (Volunteers).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Documento
{
    public function __construct()
    {
        add_action('init', [$this, 'register_cpt']);
        add_action('load-post-new.php', [$this, 'redirect_default_editor']);
        add_action('load-post.php', [$this, 'redirect_default_editor']);
        add_filter('map_meta_cap', [$this, 'map_documento_caps'], 10, 4);
    }

    public function redirect_default_editor(): void
    {
        global $typenow;
        if ($typenow === 'bdv_documento') {
            wp_safe_redirect(admin_url('admin.php?page=bdv-members'));
            exit;
        }
    }

    public function register_cpt(): void
    {
        $labels = [
            'name'                  => _x('Documentos', 'Post Type General Name', 'convoca-members'),
            'singular_name'         => _x('Documento', 'Post Type Singular Name', 'convoca-members'),
            'menu_name'             => __('Documentos', 'convoca-members'),
            'name_admin_bar'        => __('Documento', 'convoca-members'),
            'archives'              => __('Archivo de documentos', 'convoca-members'),
            'attributes'            => __('Atributos del documento', 'convoca-members'),
            'parent_item_colon'     => __('Documento superior:', 'convoca-members'),
            'all_items'             => __('Todos los documentos', 'convoca-members'),
            'add_new_item'          => __('Añadir nuevo documento', 'convoca-members'),
            'add_new'               => __('Añadir nuevo', 'convoca-members'),
            'new_item'              => __('Nuevo documento', 'convoca-members'),
            'edit_item'             => __('Editar documento', 'convoca-members'),
            'update_item'           => __('Actualizar documento', 'convoca-members'),
            'view_item'             => __('Ver documento', 'convoca-members'),
            'view_items'            => __('Ver documentos', 'convoca-members'),
            'search_items'          => __('Buscar documento', 'convoca-members'),
            'not_found'             => __('No encontrado', 'convoca-members'),
            'not_found_in_trash'    => __('No encontrado en la papelera', 'convoca-members'),
        ];
        
        $args = [
            'label'                 => __('Documento', 'convoca-members'),
            'description'           => __('Documentos generados como el Acuerdo de Incorporación.', 'convoca-members'),
            'labels'                => $labels,
            'supports'              => ['title', 'author', 'custom-fields'],
            'hierarchical'          => false,
            'public'                => false, // Only accessible via code/admin
            'show_ui'               => false,
            'show_in_menu'          => false,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'bdv_documento',
            'map_meta_cap'          => true,
        ];
        
        register_post_type('bdv_documento', $args);
    }

    /**
     * Map capabilities to 'gestionar_documentos_voluntariado'
     */
    public function map_documento_caps($caps, $cap, $user_id, $args)
    {
        $documento_caps = [
            'edit_bdv_documento',
            'read_bdv_documento',
            'delete_bdv_documento',
            'edit_bdv_documentos',
            'edit_others_bdv_documentos',
            'publish_bdv_documentos',
            'read_private_bdv_documentos',
        ];

        if (in_array($cap, $documento_caps)) {
            $caps = ['gestionar_documentos_voluntariado'];
        }

        return $caps;
    }
}
