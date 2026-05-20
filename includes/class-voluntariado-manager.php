<?php
/**
 * Voluntariado Manager - Handles auto-conversion of volunteers to active members.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if (!defined('ABSPATH')) {
    exit;
}

class Voluntariado_Manager
{
    /**
     * Initialize hooks.
     */
    public static function init(): void
    {
        add_action('convoca_members_hora_aprobada', [__CLASS__, 'on_hora_aprobada'], 10, 2);
    }

    /**
     * Handle hora approved - check if volunteer should be converted to active.
     */
    public static function on_hora_aprobada(int $hora_id, int $miembro_id): void
    {
        $total_horas = self::get_horas_aprobadas($miembro_id);
        
        $plan = get_post_meta($miembro_id, '_bdv_plan', true);
        if (empty($plan)) {
            return;
        }

        $plan_data = CPT_Miembro::get_plan($plan);
        if (!$plan_data) {
            return;
        }
        $horas_objetivo = $plan_data['hours'] ?? 0;
        
        if ($horas_objetivo <= 0) {
            return;
        }

        $ya_completo = get_post_meta($miembro_id, '_bdv_objetivo_horas_completado', true);
        
        if ($ya_completo === '1') {
            return;
        }

        if ($total_horas >= $horas_objetivo) {
            self::convertir_en_activo($miembro_id, $total_horas, $plan);
        }
    }

    /**
     * Get total approved hours for a member.
     */
    public static function get_horas_aprobadas(int $miembro_id): float
    {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_bdv_horas' 
             AND post_id IN (
                 SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'registro_hora' 
                 AND post_status = 'publish'
             )
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_bdv_miembro_id' AND meta_value = %d
             )
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_bdv_estado' AND meta_value = 'aprobada'
             )",
            $miembro_id
        ));
        
        return (float) ($result ?: 0);
    }

    /**
     * Convert volunteer to active member.
     */
    private static function convertir_en_activo(int $miembro_id, float $total_horas, string $plan): void
    {
        update_post_meta($miembro_id, '_bdv_objetivo_horas_completado', '1');
        update_post_meta($miembro_id, '_bdv_fecha_objetivo_completado', current_time('mysql'));
        update_post_meta($miembro_id, '_bdv_horas_totales_voluntariado', $total_horas);
        
        $estado_actual = get_post_meta($miembro_id, '_bdv_estado_miembro', true);
        
        if ($estado_actual !== 'activo') {
            update_post_meta($miembro_id, '_bdv_estado_cuota', 'activa');
            Estados::change($miembro_id, 'activo', "Completadas {$total_horas}h del plan {$plan}");
        }
        
        \Convoca\Core\Logger::info(
            "Voluntario ID $miembro_id ha completado las {$total_horas}h del plan {$plan}. Migrado a socio activo.",
            'Members/Voluntariado',
            $miembro_id
        );

        \Convoca\Core\Utils::do_action(
            'biodevas_members_objetivo_completado',
            'biodevas_member_objetivo_completado',
            $miembro_id,
            $total_horas,
            $plan
        );

        \Convoca\Core\Utils::do_action(
            'biodevas_members_email_objetivo_voluntariado',
            'biodevas_email_objetivo_voluntariado',
            $miembro_id
        );
    }

    /**
     * Check if member has completed their hours objective.
     */
    public static function ha_completado_objetivo(int $miembro_id): bool
    {
        return get_post_meta($miembro_id, '_bdv_objetivo_horas_completado', true) === '1';
    }

    /**
     * Get hours objective for a member's plan.
     */
    public static function get_horas_objetivo(int $miembro_id): float
    {
        $plan = get_post_meta($miembro_id, '_bdv_plan', true);
        if (empty($plan)) {
            return 0;
        }
        
        $plan_data = CPT_Miembro::get_plan($plan);
        return $plan_data ? (float) ($plan_data['hours'] ?? 0) : 0;
    }

    /**
     * Get progress percentage for a member.
     */
    public static function get_progreso(int $miembro_id): array
    {
        $total = self::get_horas_aprobadas($miembro_id);
        $objetivo = self::get_horas_objetivo($miembro_id);
        
        $porcentaje = $objetivo > 0 ? min(100, round(($total / $objetivo) * 100)) : 0;
        
        return [
            'total' => $total,
            'objetivo' => $objetivo,
            'porcentaje' => $porcentaje,
            'completado' => $total >= $objetivo,
        ];
    }
}