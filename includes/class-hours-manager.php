<?php
/**
 * Centralized manager for volunteering hours.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if (!defined('ABSPATH')) {
    exit;
}

class Hours_Manager
{

    /**
     * Validate hour record data.
     *
     * @param array $data Data to validate.
     * @return bool|\WP_Error True if valid, WP_Error otherwise.
     */
    public static function validate_hours_data(array $data): bool|\WP_Error
    {
        if (empty($data['miembro_id'])) {
            return new \WP_Error('missing_miembro', __('Debes seleccionar un socio.', 'convoca-members'));
        }
        if (empty($data['fecha'])) {
            return new \WP_Error('missing_fecha', __('La fecha es obligatoria.', 'convoca-members'));
        }
        if (empty($data['horas']) || (float) $data['horas'] <= 0) {
            return new \WP_Error('invalid_horas', __('El número de horas debe ser mayor que cero.', 'convoca-members'));
        }
        if (empty($data['proyecto_id'])) {
            return new \WP_Error('missing_proyecto', __('Debes seleccionar un proyecto.', 'convoca-members'));
        }
        if (empty($data['tareas'])) {
            return new \WP_Error('missing_tareas', __('Debes describir las tareas realizadas.', 'convoca-members'));
        }

        // Validate proyecto_id
        $proyecto = get_post((int) $data['proyecto_id']);
        if (!$proyecto || $proyecto->post_type !== 'proyecto') {
            return new \WP_Error('invalid_proyecto', __('El proyecto seleccionado no es válido.', 'convoca-members'));
        }

        return true;
    }

    /**
     * Save or update an hour record.
     *
     * @param array $data     Data to save.
     * @param bool  $is_admin Whether the save is from admin context.
     * @return int|\WP_Error  Post ID on success, WP_Error on failure.
     */
    public static function save_hours_record(array $data, bool $is_admin = false): int|\WP_Error
    {
        $validation = self::validate_hours_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $record_id = isset($data['id']) ? (int) $data['id'] : 0;
        $miembro_id = (int) $data['miembro_id'];
        $fecha = sanitize_text_field($data['fecha']);
        $horas = (float) $data['horas'];
        $proyecto_id = (int) $data['proyecto_id'];
        $actividad_id = isset($data['actividad_id']) ? (int) $data['actividad_id'] : 0;
        $tareas = sanitize_textarea_field($data['tareas']);
        $descripcion = isset($data['descripcion']) ? sanitize_textarea_field($data['descripcion']) : '';
        $estado = $is_admin && isset($data['estado']) ? sanitize_text_field($data['estado']) : 'pendiente';
        $nota_admin = isset($data['nota_admin']) ? sanitize_textarea_field($data['nota_admin']) : '';

        $post_title = sprintf(
            __('Registro Socio %d — %s — %gh', 'convoca-members'),
            $miembro_id,
            $fecha,
            $horas
        );

        $post_data = [
            'post_type'    => 'registro_hora',
            'post_title'   => $post_title,
            'post_content' => $descripcion,
            'post_status'  => 'publish',
        ];

        if ($record_id) {
            $post_data['ID'] = $record_id;
            $old_status = get_post_meta($record_id, '_bdv_estado', true);
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
            $old_status = '';
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, '_bdv_miembro_id', $miembro_id);
        update_post_meta($post_id, '_bdv_fecha', $fecha);
        update_post_meta($post_id, '_bdv_horas', $horas);
        update_post_meta($post_id, '_bdv_proyecto_id', $proyecto_id);
        update_post_meta($post_id, '_bdv_tareas', substr($tareas, 0, 500));

        if ($actividad_id) {
            update_post_meta($post_id, '_bdv_actividad_id', $actividad_id);
        } else {
            delete_post_meta($post_id, '_bdv_actividad_id');
        }

        if ($is_admin) {
            update_post_meta($post_id, '_bdv_nota_admin', $nota_admin);
        }

        // Handle state changes
        if ($estado !== $old_status) {
            self::process_approval($post_id, $estado, get_current_user_id());
        }

        return $post_id;
    }

    /**
     * Process approval or rejection of an hour record.
     *
     * @param int    $record_id  The record ID.
     * @param string $new_status New status ('pendiente', 'aprobada', 'rechazada').
     * @param int    $admin_id   The admin user ID performing the action.
     * @return bool
     */
    public static function process_approval(int $record_id, string|bool $new_status, int $admin_id): bool
    {
        // Normalize bool values: true → 'aprobada', false → 'rechazada'
        if (is_bool($new_status)) {
            $new_status = $new_status ? 'aprobada' : 'rechazada';
        }

        $miembro_id = (int) get_post_meta($record_id, '_bdv_miembro_id', true);
        if (!$miembro_id) {
            return false;
        }

        $old_status = get_post_meta($record_id, '_bdv_estado', true);
        if ($old_status === $new_status) {
            return true;
        }

        update_post_meta($record_id, '_bdv_estado', $new_status);
        update_post_meta($record_id, '_bdv_aprobada_por', $admin_id);

        if ($new_status === 'aprobada') {
            do_action('convoca_members_hora_aprobada', $record_id, $miembro_id);
        } elseif ($new_status === 'rechazada') {
            do_action('convoca_members_hora_rechazada', $record_id, $miembro_id);
        }

        return true;
    }
}
