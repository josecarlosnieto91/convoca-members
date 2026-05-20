<?php
/**
 * REST API for Members.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if (!defined('ABSPATH')) {
    exit;
}

class Rest_API
{
    /**
     * Namespace for the API.
     */
    public const NAMESPACE = 'convoca-members/v1';

    /**
     * Rate limit: max attempts per IP in window.
     */
    private const RATE_LIMIT_MAX = 10;
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register routes.
     */
    public function register_routes(): void
    {
        // Login.
        register_rest_route(self::NAMESPACE, '/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);

        // Current member profile.
        register_rest_route(self::NAMESPACE, '/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_profile'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        // Inscriptions.
        register_rest_route(self::NAMESPACE, '/me/inscripciones', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_inscriptions'],
            'permission_callback' => [$this, 'check_active_member'],
        ]);

        // Payments.
        register_rest_route(self::NAMESPACE, '/me/pagos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_payments'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        // Volunteering hours.
        register_rest_route(self::NAMESPACE, '/me/horas', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_hours'],
                'permission_callback' => [$this, 'check_active_member'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'submit_hours'],
                'permission_callback' => [$this, 'check_active_member'],
            ]
        ]);

        // Card download/view.
        register_rest_route(self::NAMESPACE, '/me/card', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_card'],
            'permission_callback' => [$this, 'check_active_member'],
        ]);

        // Active projects for dropdown.
        register_rest_route(self::NAMESPACE, '/proyectos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_proyectos'],
            'permission_callback' => '__return_true',
        ]);

        // Admin: search members (autocomplete).
        register_rest_route(self::NAMESPACE, '/admin/members/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'admin_search_members'],
            'permission_callback' => fn() => current_user_can('gestionar_miembros'),
        ]);


        // Download certificate.
        register_rest_route(self::NAMESPACE, '/me/certificate', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_certificate'],
            'permission_callback' => [$this, 'check_active_member'],
        ]);

        // Download certificate as PDF.
        register_rest_route(self::NAMESPACE, '/me/certificate/download', [
            'methods'             => 'GET',
            'callback'            => [$this, 'download_certificate'],
            'permission_callback' => [$this, 'check_active_member'],
        ]);

        // Unsubscribe request.
        register_rest_route(self::NAMESPACE, '/me/unsubscribe', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unsubscribe_request'],
            'permission_callback' => [$this, 'check_active_member'],
        ]);

        // Member notifications.
        register_rest_route(self::NAMESPACE, '/member/notifications', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_member_notifications'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        // Mark notification as read.
        register_rest_route(self::NAMESPACE, '/member/notifications/read', [
            'methods'             => 'POST',
            'callback'            => [$this, 'mark_notification_read'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        // Mark all notifications as read.
        register_rest_route(self::NAMESPACE, '/member/notifications/read-all', [
            'methods'             => 'POST',
            'callback'            => [$this, 'mark_all_notifications_read'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        register_rest_route(self::NAMESPACE, '/documentos/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_document'],
            'permission_callback' => [$this, 'check_member_auth'],
        ]);

        // Unified full-text search: /convoca-members/v1/search?q=...
        register_rest_route(self::NAMESPACE, '/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'fulltext_search'],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit' => [
                    'sanitize_callback' => 'absint',
                    'default'           => 20,
                ],
            ],
        ]);
    }

    /**
     * Check if a member is logged in via session cookie.
     */
    public static function check_member_auth(): bool
    {
        return Member_Auth::is_authenticated();
    }

    /**
     * Check if a member is logged in AND is active.
     */
    public static function check_active_member(): bool|\WP_Error
    {
        if (!Member_Auth::is_authenticated()) {
            return false;
        }

        if (!Member_Auth::is_active()) {
            return new \WP_Error(
                'inactive_member',
                'Tu cuenta no está activa. Contacta con coordinación si crees que es un error.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Login handler — WordPress credentials (username or email + password).
     */
    public function login(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check rate limit before processing
        if (!$this->check_rate_limit('login')) {
            return new \WP_REST_Response([
                'success' => false, 
                'message' => 'Demasiados intentos. Intenta de nuevo en 5 minutos.'
            ], 429);
        }

        $username = sanitize_text_field($request->get_param('username') ?? $request->get_param('email') ?? '');
        $password = $request->get_param('password') ?? '';

        if (empty($username) || empty($password)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Usuario y contraseña son obligatorios.'], 400);
        }

        $token = Member_Auth::login($username, $password);

        if (is_wp_error($token)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $token->get_error_message()
            ], 401);
        }

        return new \WP_REST_Response([
            'success' => true,
            'token'   => $token
        ]);
    }

    /**
     * Check rate limit for an action.
     * Uses transients to track attempts per IP.
     */
    private function check_rate_limit(string $action): bool
    {
        return \Convoca\Core\Utils::check_rate_limit($action, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Get profile data.
     */
    public function get_profile(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $post = get_post($member_id);

        if (!$post || $post->post_type !== 'miembro') {
            return new \WP_REST_Response(['error' => 'Miembro no encontrado.'], 404);
        }

        $access_code = get_post_meta($member_id, '_bdv_access_code', true);
        $masked_code = !empty($access_code) ? substr($access_code, 0, 4) . '****' : '';

        return new \WP_REST_Response([
            'id'     => $member_id,
            'nombre' => $post->post_title,
            'email'  => get_post_meta($member_id, '_bdv_email', true),
            'codigo' => $masked_code,
            'estado' => get_post_meta($member_id, '_bdv_estado_miembro', true),
            'tipo'   => (array) get_post_meta($member_id, '_bdv_modalidad', true) ?: ['Socio/a'],
        ]);
    }

    /**
     * Get inscriptions from biodevas-enroll.
     */
    public function get_inscriptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $email = get_post_meta($member_id, '_bdv_email', true);
        $dni = get_post_meta($member_id, '_bdv_dni', true);

        // Query inscriptions by email or DNI.
        $args = [
            'post_type'      => 'inscripcion',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => '_bde_email', 'value' => $email],
                ['key' => '_bde_dni', 'value' => $dni],
            ],
        ];

        $query = new \WP_Query($args);
        $items = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $actividad_id = get_post_meta($post->ID, '_bde_actividad_id', true);
                $actividad = get_the_title($actividad_id);
                
                $estado = get_post_meta($post->ID, '_bde_estado', true);
                $item = [
                    'id'        => $post->ID,
                    'fecha'     => get_the_date('d/m/Y', $post->ID),
                    'actividad' => $actividad ?: __('Actividad desconocida', 'convoca-members'),
                    'estado'    => $estado,
                ];

                // Only include token for confirmed states (needed for ICS download)
                if (in_array($estado, ['confirmada', 'pagada'], true)) {
                    $item['token'] = get_post_meta($post->ID, '_bde_checkin_token', true);
                }

                // Include Google Photos album link if available
                if ($actividad_id) {
                    $album_url = get_post_meta($actividad_id, '_bde_google_album_url', true);
                    if (!empty($album_url)) {
                        $item['fotos_url'] = $album_url;
                    }
                }

                $items[] = $item;
            }
        }

        return new \WP_REST_Response(['items' => $items]);
    }

    /**
     * Get payments from biodevas-gateway.
     */
    public function get_payments(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();

        $args = [
            'post_type'      => 'pago',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => '_bdg_origin', 'value' => 'members'],
                ['key' => '_bdg_origin_id', 'value' => $member_id],
            ],
        ];

        $query = new \WP_Query($args);
        $items = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $status = get_post_meta($post->ID, '_bdg_status', true);
                $amount = (int) get_post_meta($post->ID, '_bdg_amount_cents', true);
                
                $items[] = [
                    'id'       => $post->ID,
                    'fecha'    => get_the_date('d/m/Y', $post->ID),
                    'concepto' => get_post_meta($post->ID, '_bdg_product_desc', true),
                    'importe'  => number_format($amount / 100, 2, ',', '.'),
                    'estado'   => $status,
                ];
            }
        }

        return new \WP_REST_Response(['items' => $items]);
    }

    /**
     * Get volunteering hours.
     */
    public function get_hours(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();

        $args = [
            'post_type'      => 'registro_hora',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => '_bdv_miembro_id', 'value' => $member_id],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $items = [];
        $total_horas = 0;

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $horas = (float) get_post_meta($post->ID, '_bdv_horas', true);
                $estado = get_post_meta($post->ID, '_bdv_estado', true) ?: 'pendiente';
                $actividad_id = get_post_meta($post->ID, '_bdv_actividad_id', true);
                $proyecto_id = get_post_meta($post->ID, '_bdv_proyecto_id', true);
                $tareas = get_post_meta($post->ID, '_bdv_tareas', true);
                
                if ($estado === 'aprobada') {
                    $total_horas += $horas;
                }

                $items[] = [
                    'id'          => $post->ID,
                    'fecha'       => get_post_meta($post->ID, '_bdv_fecha', true) ?: get_the_date('d/m/Y', $post->ID),
                    'descripcion' => $post->post_content,
                    'horas'       => $horas,
                    'estado'      => $estado,
                    'actividad'   => $actividad_id ? get_the_title($actividad_id) : '',
                    'actividad_id'=> $actividad_id,
                    'proyecto'    => $proyecto_id ? get_the_title($proyecto_id) : '',
                    'proyecto_id' => $proyecto_id,
                    'tareas'      => $tareas,
                    'nota_admin'  => get_post_meta($post->ID, '_bdv_nota_admin', true) ?: '',
                ];
            }
        }

        return new \WP_REST_Response([
            'items'       => $items,
            'total_horas' => $total_horas
        ]);
    }

    /**
     * Submit new hours record.
     */
    public function submit_hours(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $data = [
            'miembro_id'   => $member_id,
            'fecha'        => sanitize_text_field($request->get_param('fecha')),
            'descripcion'  => sanitize_textarea_field($request->get_param('descripcion')),
            'horas'        => (float) $request->get_param('horas'),
            'actividad_id' => (int) $request->get_param('actividad_id'),
            'proyecto_id'  => (int) $request->get_param('proyecto_id'),
            'tareas'       => sanitize_textarea_field($request->get_param('tareas')),
        ];

        $post_id = Hours_Manager::save_hours_record($data, false);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $post_id->get_error_message()
            ], 400);
        }

        // Notification to admin via action
        \Convoca\Core\Utils::do_action('convoca_members_hours_submitted', 'biodevas_hours_submitted', $post_id, $member_id);

        return new \WP_REST_Response(['success' => true, 'id' => $post_id]);
    }

    /**
     * Get active projects for dropdown.
     */
    public function get_proyectos(): \WP_REST_Response
    {
        if (!\Convoca\Core\Utils::check_rate_limit('bdv_get_proyectos', 30, 60)) {
            return new \WP_REST_Response(['error' => __('Demasiadas peticiones.', 'convoca-members')], 429);
        }

        $items = \Convoca\Core\Utils::rest_cache_get('proyectos', 120, function () {
            $proyectos = CPT_Proyecto::get_active_proyectos();
            $items = [];
            foreach ($proyectos as $id => $title) {
                $items[] = ['id' => $id, 'title' => $title];
            }
            return $items;
        });

        return new \WP_REST_Response($items);
    }

    /**
     * Admin: search members by name or email (autocomplete).
     */
    /**
     * Unified full-text search across members and activities.
     *
     * GET /convoca-members/v1/search?q=...&type=members|activities&limit=20
     *
     * Public endpoint — returns only non-sensitive data.
     * Members search requires auth to see personal info.
     * Activities search is public.
     */
    public function fulltext_search(\WP_REST_Request $req): \WP_REST_Response
    {
        $q     = sanitize_text_field($req->get_param('q') ?? '');
        $type  = sanitize_text_field($req->get_param('type') ?? ''); // 'members', 'activities', or '' (both)
        $limit = min(absint($req->get_param('limit') ?: 20), 50);

        if (strlen($q) < 2) {
            return new \WP_REST_Response([
                'results' => [],
                'total'   => 0,
                'query'   => $q,
            ]);
        }

        $results = [];

        // ── Search members (admin-accessible via REST) ──────────────
        if (!$type || $type === 'members') {
            $member_args = [
                'post_type'      => 'miembro',
                'post_status'    => 'any',
                'posts_per_page' => $limit,
                's'              => $q,
                'meta_query'     => [
                    'relation' => 'OR',
                    ['key' => '_bdv_email',       'value' => $q, 'compare' => 'LIKE'],
                    ['key' => '_bdv_dni',         'value' => $q, 'compare' => 'LIKE'],
                    ['key' => '_bdv_telefono',    'value' => $q, 'compare' => 'LIKE'],
                    ['key' => '_bdv_numero_socio','value' => $q, 'compare' => 'LIKE'],
                    ['key' => '_bdv_access_code', 'value' => $q, 'compare' => 'LIKE'],
                    ['key' => '_bdv_nombre',      'value' => $q, 'compare' => 'LIKE'],
                ],
            ];
            $member_query = new \WP_Query($member_args);
            foreach ($member_query->posts as $p) {
                $results[] = [
                    'type'       => 'member',
                    'id'         => $p->ID,
                    'title'      => $p->post_title,
                    'email'      => get_post_meta($p->ID, '_bdv_email', true),
                    'estado'     => get_post_meta($p->ID, '_bdv_estado_miembro', true),
                    'url'        => rest_url('convoca-members/v1/me'),
                ];
            }
        }

        // ── Search activities (publicly readable) ───────────────────
        if (!$type || $type === 'activities') {
            $act_args = [
                'post_type'      => 'actividad',
                'post_status'    => ['publish', 'future'],
                'posts_per_page' => $type === 'activities' ? $limit : max(1, $limit - count($results)),
                's'              => $q,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];
            $act_query = new \WP_Query($act_args);
            foreach ($act_query->posts as $p) {
                $fecha = get_post_meta($p->ID, '_bde_fecha_inicio', true);
                $results[] = [
                    'type'      => 'activity',
                    'id'        => $p->ID,
                    'title'     => $p->post_title,
                    'fecha'     => $fecha ? \Convoca\Core\Utils::format_date($fecha, 'd/m/Y H:i') : '',
                    'ubicacion' => get_post_meta($p->ID, '_bde_ubicacion', true),
                    'url'       => get_permalink($p->ID),
                ];
            }
        }

        // Sort: members before activities, then by title
        usort($results, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'member' ? -1 : 1;
            }
            return strcmp($a['title'], $b['title']);
        });

        // Trim to limit after sort
        $results = array_slice($results, 0, $limit);

        return new \WP_REST_Response([
            'results' => $results,
            'total'   => count($results),
            'query'   => $q,
        ]);
    }

    /**
     * Admin: search members by name or email (autocomplete).
     */
    public function admin_search_members(\WP_REST_Request $req): \WP_REST_Response
    {
        $term = sanitize_text_field($req->get_param('term') ?? '');
        if (strlen($term) < 2) {
            return new \WP_REST_Response([], 200);
        }

        $args = [
            'post_type' => 'miembro',
            'post_status' => 'any',
            'posts_per_page' => 20,
            's' => $term,
        ];
        $query = new \WP_Query($args);
        $results = [];
        foreach ($query->posts as $p) {
            $results[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'email' => get_post_meta($p->ID, '_bdv_email', true),
            ];
        }
        return new \WP_REST_Response($results, 200);
    }



    /**
     * Get certificate download URL and info.
     */
    public function get_certificate(): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $post = get_post($member_id);
        
        if (!$post || $post->post_type !== 'miembro') {
            return new \WP_REST_Response(['error' => 'Miembro no encontrado.'], 404);
        }
        
        // Verify completion by recalculating actual hours (not just meta)
        $horas_aprobadas = Voluntariado_Manager::get_horas_aprobadas($member_id);
        $objetivo = Voluntariado_Manager::get_horas_objetivo($member_id);
        $completado = $horas_aprobadas >= $objetivo;
        
        if (!$completado) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Aún no has completado las horas mínimas requeridas para generar el certificado.',
                'progress' => [
                    'aprobadas' => $horas_aprobadas,
                    'objetivo' => $objetivo,
                ],
            ], 400);
        }
        
        $cert_id = get_post_meta($member_id, '_bdv_certificado_id', true);
        
        return new \WP_REST_Response([
            'success' => true,
            'certificado_id' => $cert_id,
            'horas' => $horas_aprobadas,
            'objetivo' => $objetivo,
            'download_url' => rest_url('convoca-members/v1/me/certificate/download'),
        ]);
    }

    /**
     * Download certificate as PDF.
     */
    public function download_certificate(): void
    {
        $member_id = Member_Auth::get_current_member_id();
        $post = get_post($member_id);
        
        if (!$member_id || !$post || $post->post_type !== 'miembro') {
            wp_send_json_error('Miembro no encontrado o no autorizado', 404);
            return;
        }
        
        Certificate_Generator::serve_pdf($member_id);
    }

    /**
     * Process unsubscribe request.
     */
    public function unsubscribe_request(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        
        // Log the request and notify admin.
        Estados::change($member_id, 'baja_solicitada', 'Solicitud de baja desde el panel de socio.');
        
        // Action for Email Manager to hook into.
        \Convoca\Core\Utils::do_action('convoca_members_unsubscribe_request', 'biodevas_member_unsubscribe_request', $member_id);

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Get member card HTML.
     */
    public function get_card(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $html = PDF_Card::get_html($member_id);
        return new \WP_REST_Response(['html' => $html]);
    }

    /**
     * Get member notifications.
     */
    public function get_member_notifications(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $limit = (int) $request->get_param('limit') ?: 10;

        $notifications = \Convoca\Core\Notifications::get_member($member_id, $limit);
        $unread_count = \Convoca\Core\Notifications::count_member_unread($member_id);
        $all = get_post_meta($member_id, '_bdv_notifications', true) ?: [];

        return new \WP_REST_Response([
            'items' => $notifications,
            'unread' => $unread_count,
            'total' => count($all),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function mark_notification_read(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        $notification_id = sanitize_text_field($request->get_param('id') ?? '');

        if (empty($notification_id)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Falta ID de notificación.'], 400);
        }

        \Convoca\Core\Notifications::mark_member_read($member_id, $notification_id);

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function mark_all_notifications_read(\WP_REST_Request $request): \WP_REST_Response
    {
        $member_id = Member_Auth::get_current_member_id();
        \Convoca\Core\Notifications::mark_member_all_read($member_id);
        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Serve a document PDF securely.
     */
    public function get_document(\WP_REST_Request $request): void
    {
        $id = (int) $request['id'];
        $post = get_post($id);

        if (!$post || $post->post_type !== 'bdv_documento') {
            wp_die('Documento no encontrado.', 'Error', ['response' => 404]);
        }

        $user_id = (int) get_post_meta($id, '_bdv_usuario_id', true);
        $current_user_id = get_current_user_id();

        // Security check: Admin OR Owner
        if (!current_user_can('manage_options') && $user_id !== $current_user_id) {
            wp_die('No tienes permiso para ver este documento.', 'Acceso Denegado', ['response' => 403]);
        }

        $filepath = get_post_meta($id, '_bdv_documento_path', true);

        if (!$filepath || !file_exists($filepath)) {
            wp_die('El archivo físico no existe en el servidor.', 'Error', ['response' => 404]);
        }

        // Path traversal protection: ensure file is within the safe documents directory
        $real_path = realpath($filepath);
        $safe_base = realpath(wp_upload_dir()['basedir'] . '/biodevas-documentos');
        if ($real_path === false || $safe_base === false || !str_starts_with($real_path, $safe_base)) {
            \Convoca\Core\Logger::warning("Intento de path traversal detectado: $filepath", 'Members/Security');
            wp_die('Archivo no válido.', 'Acceso Denegado', ['response' => 403]);
        }

        // Clear output buffer to avoid corruption
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filepath);
        exit;
    }
}
