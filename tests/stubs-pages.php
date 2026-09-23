<?php
/**
 * Dobles de las funciones de página que WordPress trae y el bootstrap standalone
 * no. Van en el espacio global a propósito: las clases las llaman sin namespace.
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
