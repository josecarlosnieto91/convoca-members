<?php
/**
 * Dobles de las funciones y clases de página que WordPress trae y el bootstrap
 * standalone no. Van en el espacio global a propósito: las clases las llaman sin
 * namespace, y el `class_exists` es necesario porque el CI de este repo corre sin
 * el repositorio de core clonado (entonces no hay ningún doble de WP_Post).
 *
 * Se controlan desde `$GLOBALS['convoca_test_pages']` (slug => objeto página).
 */

if (!function_exists('get_page_by_path')) {
    function get_page_by_path($slug)
    {
        return $GLOBALS['convoca_test_pages'][$slug] ?? null;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($page)
    {
        $id = is_object($page) ? ($page->ID ?? 0) : (int) $page;

        return 'https://example.com/pagina/' . $id . '/';
    }
}

if (!class_exists('WP_Post')) {
    /**
     * Doble mínimo: las propiedades se asignan a mano (el de core tampoco tiene
     * constructor) para que el mismo test valga con cualquiera de los dos.
     */
    class WP_Post
    {
        public $ID = 0;
        public $post_name = '';
        public $post_status = 'publish';
    }
}
