<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Admin
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Admin list table using WP_List_Table.
 *
 * v2: added Teléfono, WhatsApp (wa.me) columns,
 *     row actions with WhatsApp link, sortable by plan/estado.
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

class Admin_List extends \WP_List_Table {


	/** Default WhatsApp message template. */
	private const WA_MSG = 'Hola {nombre}, te escribimos desde Convoca. ';

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'miembro',
				'plural'   => 'miembros',
				'ajax'     => false,
				'screen'   => 'conv-members',
			)
		);
		// El tipo no admite papelera, así que WordPress pinta «Papelera» y no hace nada: se quita de la
		// pantalla cruda para que no haya botones muertos (issue #27).
		add_filter( 'post_row_actions', array( $this, 'sin_papelera' ), 10, 2 );
		add_filter( 'bulk_actions-edit-miembro', array( $this, 'sin_papelera_bulk' ) );
	}

	public function get_columns(): array {
		return array(
			'cb'         => __( '<input type="checkbox">', 'convoca-members' ),
			'numero'     => __( 'Nº', 'convoca-members' ),
			'nombre'     => __( 'Nombre', 'convoca-members' ),
			'email'      => __( 'Email', 'convoca-members' ),
			'telefono'   => __( 'Teléfono', 'convoca-members' ),
			'whatsapp'   => __( 'WhatsApp', 'convoca-members' ),
			'tipo'       => __( 'Tipo', 'convoca-members' ),
			'estado'     => __( 'Estado', 'convoca-members' ),
			'plan'       => __( 'Plan', 'convoca-members' ),
			'cuota'      => __( 'Cuota', 'convoca-members' ),
			'recurrente' => __( 'Renov. Pago', 'convoca-members' ),
			'fecha'      => __( 'Fecha alta', 'convoca-members' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'nombre' => array( 'title', true ),
			'fecha'  => array( 'date', false ),
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'bulk-baja' => __( 'Dar de baja (la ficha se conserva)', 'convoca-members' ),
		);
	}

	public function process_bulk_action(): void {
		if ( 'bulk-baja' === $this->current_action() ) {
			check_admin_referer( 'bulk-members' );

			$ids = isset( $_REQUEST['miembros'] ) ? array_map( 'intval', $_REQUEST['miembros'] ) : array();
			if ( empty( $ids ) ) {
				return;
			}

			// La baja en lote es lógica: se cambia el estado y la ficha se conserva (issue #27). No se
			// ofrece el borrado definitivo en lote a propósito: es una excepción y va de una en una.
			foreach ( $ids as $id ) {
				if ( ! current_user_can( 'edit_post', $id ) ) {
					continue;
				}
				Estados::change( $id, 'baja', __( 'Baja en lote desde el listado', 'convoca-members' ) );
			}
		}
	}

	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->process_bulk_action();

		$per_page     = 20;
		$page         = $this->get_pagenum();
		$request_data = wp_unslash( $_REQUEST );
		$search       = isset( $request_data['s'] ) ? sanitize_text_field( $request_data['s'] ) : '';

		$args = array(
			'post_type'      => 'miembro',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future' ),
			'orderby'        => sanitize_text_field( $request_data['orderby'] ?? 'date' ),
			'order'          => strtoupper( sanitize_text_field( $request_data['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC',
			'no_found_rows'  => $page > 1, // Only count rows on first page.
		);

		// Search by name, email, DNI, phone, member number or access code.
		if ( $search ) {
			$cleaned_search     = strtoupper( str_replace( array( ' ', '-' ), '', $search ) );
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_convoca_email',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_dni',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_dni',
					'value'   => $cleaned_search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_telefono',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_numero_socio',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_access_code',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_convoca_nombre',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
			$args['s']          = $search; // Also search post title + content.
		}

		// Filter by estado.
		$get_data      = wp_unslash( $_GET );
		$estado_filter = sanitize_text_field( $get_data['estado_filter'] ?? '' );
		if ( $estado_filter ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					array(
						'key'   => '_convoca_estado_miembro',
						'value' => $estado_filter,
					),
				)
			);
		}

		// Filter by tipo.
		$tipo_filter = sanitize_text_field( $get_data['tipo_filter'] ?? '' );
		if ( $tipo_filter ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'tipo_miembro',
					'field'    => 'slug',
					'terms'    => $tipo_filter,
				),
			);
		}

		// Filter by volunteer status (from custom submenu).
		if ( ! empty( $get_data['voluntarios'] ) || ( isset( $get_data['page'] ) && $get_data['page'] === 'conv-members-voluntarios' ) ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					array(
						'key'   => '_convoca_es_voluntario',
						'value' => '1',
					),
				)
			);
		}

		// Filter by plan.
		$plan_filter = sanitize_text_field( $get_data['plan_filter'] ?? '' );
		if ( $plan_filter ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					array(
						'key'   => '_convoca_plan',
						'value' => $plan_filter,
					),
				)
			);
		}

		// Filter by estado_cuota.
		$cuota_filter = sanitize_text_field( $get_data['cuota_filter'] ?? '' );
		if ( $cuota_filter ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					array(
						'key'   => '_convoca_estado_cuota',
						'value' => $cuota_filter,
					),
				)
			);
		}

		$query = new \WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);

		// Cache for 5 minutes if no search/filter active, to reduce DB load on paginated views.
		$cache_key = 'convoca_list_members_' . md5( wp_json_encode( $args ) );
		$cached    = ! $search && ! $estado_filter && ! $tipo_filter && ! $plan_filter && ! $cuota_filter ? get_transient( $cache_key ) : false;

		if ( $cached ) {
			$this->items = $cached['items'];
			$this->set_pagination_args( $cached['pagination'] );
			return;
		}

		$query       = new \WP_Query( $args );
		$this->items = $query->posts;
		$pagination  = array(
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages,
		);
		$this->set_pagination_args( $pagination );

		if ( ! $search && ! $estado_filter && ! $tipo_filter && ! $plan_filter && ! $cuota_filter ) {
			set_transient(
				$cache_key,
				array(
					'items'      => $query->posts,
					'pagination' => $pagination,
				),
				300
			);
		}
	}

	/* ── Extra filters above the table ─────────── */

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<select name="estado_filter">
				<option value="">
					<?php esc_html_e( 'Todos los estados', 'convoca-members' ); ?>
				</option>
				<?php
				$get_data = wp_unslash( $_GET );
				foreach ( Estados::labels() as $slug => $label ) :
					?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $get_data['estado_filter'] ?? '', $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="tipo_filter">
				<option value="">
					<?php esc_html_e( 'Todos los tipos', 'convoca-members' ); ?>
				</option>
				<option value="socio" <?php selected( $get_data['tipo_filter'] ?? '', 'socio' ); ?>>Socio/a</option>
				<option value="voluntario" <?php selected( $get_data['tipo_filter'] ?? '', 'voluntario' ); ?>>Voluntario/a</option>
				<option value="socio_voluntario" <?php selected( $get_data['tipo_filter'] ?? '', 'socio_voluntario' ); ?>>Socio +
					Vol.</option>
			</select>
			<select name="plan_filter">
				<option value="">
					<?php esc_html_e( 'Todos los planes', 'convoca-members' ); ?>
				</option>
				<?php foreach ( CPT_Miembro::get_plans() as $slug => $plan ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $get_data['plan_filter'] ?? '', $slug ); ?>>
						<?php echo esc_html( $plan['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="cuota_filter">
				<option value="">
					<?php esc_html_e( 'Estado cuota', 'convoca-members' ); ?>
				</option>
				<?php foreach ( CPT_Miembro::ESTADO_CUOTA as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $get_data['cuota_filter'] ?? '', $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filtrar', 'convoca-members' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/* ── Column renderers ──────────────────────── */

	/**
	 * Render a table column.
	 *
	 * @param \WP_Post $item Row item.
	 */
	public function column_default( $item, $column_name ): string {
		return get_post_meta( $item->ID, '_convoca_' . $column_name, true ) ?: '—';
	}

	/**
	 * Render a table column.
	 *
	 * @param \WP_Post $item Row item.
	 */
	public function column_cb( $item ): string {
		return '<input type="checkbox" name="miembros[]" value="' . esc_attr( (string) $item->ID ) . '" aria-label="' . esc_attr(
			sprintf( 
					/* translators: %s: member name */
				__( 'Seleccionar %s', 'convoca-members' ),
				get_the_title( $item->ID ) 
			) 
		) . '">';
	}

	/**
	 * Render a table column.
	 *
	 * @param \WP_Post $item Row item.
	 */
	public function column_numero( $item ): string {
		$num = get_post_meta( $item->ID, '_convoca_numero_socio', true );
		if ( $num ) {
			return '<strong>' . str_pad( $num, 4, '0', STR_PAD_LEFT ) . '</strong>';
		}

		$status = get_post_meta( $item->ID, '_convoca_estado_miembro', true );
		$html   = '<span style="color:#999;font-style:italic">' . __( 'Sin asignar', 'convoca-members' ) . '</span>';

		if ( $status === 'activo' && current_user_can( 'convoca_export_members' ) ) {
			$repair_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=convoca_approve_member&member_id=' . $item->ID ),
				'convoca_approve_member_' . $item->ID
			);
			$html      .= ' <a href="' . esc_url( $repair_url ) . '" style="font-size:10px;text-decoration:none" title="' . esc_attr__( 'Asignar número ahora', 'convoca-members' ) . '">🔧</a>';
		}

		return $html;
	}

	public function column_nombre( $item ): string {
		$url       = admin_url( 'admin.php?page=conv-members&member_id=' . $item->ID );
		$name_link = '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->post_title ) . '</strong></a>';

		// Row actions.
		$actions = array(
			'edit' => '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr(
				sprintf( 
				/* translators: %s: member name */
					__( 'Ver ficha de %s', 'convoca-members' ),
					$item->post_title 
				) 
			) . '">' . __( 'Ver ficha', 'convoca-members' ) . '</a>',
		);

		// Approval action.
		$status = get_post_meta( $item->ID, '_convoca_estado_miembro', true );
		if ( $status !== 'activo' ) {
			$approve_url        = wp_nonce_url(
				admin_url( 'admin-post.php?action=convoca_approve_member&member_id=' . $item->ID ),
				'convoca_approve_member_' . $item->ID
			);
			$actions['approve'] = '<a href="' . esc_url( $approve_url ) . '" style="color:#00a32a" aria-label="' . esc_attr(
				sprintf( 
				/* translators: %s: member name */
					__( 'Aprobar alta de %s', 'convoca-members' ),
					$item->post_title 
				) 
			) . '">' . __( 'Aprobar alta', 'convoca-members' ) . '</a>';
		}

		// PDF Card action.
		$actions['card'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_pdf_card&member_id=' . $item->ID ), 'convoca_pdf_card_' . $item->ID ) ) . '" target="_blank" aria-label="' . esc_attr(
			sprintf( 
			/* translators: %s: member name */
				__( 'Ver tarjeta de %s', 'convoca-members' ),
				$item->post_title 
			) 
		) . '">🪪 ' . __( 'Tarjeta', 'convoca-members' ) . '</a>';

		// Dar de baja: la ficha se conserva, deja de contar como activo y se puede reactivar (issue #27).
		$baja_url        = wp_nonce_url(
			admin_url( 'admin-post.php?action=convoca_baja_member&member_id=' . $item->ID ),
			'convoca_baja_member_' . $item->ID
		);
		$actions['baja'] = '<a href="' . esc_url( $baja_url ) . '" onclick="return confirm(\'¿Dar de baja a este socio? La ficha se conserva y deja de contar como socio activo.\')" aria-label="' . esc_attr(
			sprintf(
				/* translators: %s: member name */
				__( 'Dar de baja a %s', 'convoca-members' ),
				$item->post_title
			)
		) . '">' . __( 'Dar de baja', 'convoca-members' ) . '</a>';

		// Eliminar definitivamente: la excepción. El aviso dice qué se pierde, no «¿seguro?».
		$delete_url        = wp_nonce_url(
			admin_url( 'admin-post.php?action=convoca_delete_member&member_id=' . $item->ID ),
			'convoca_delete_member_' . $item->ID
		);
		$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" style="color:#a00" onclick="return confirm(\'Se borran la ficha, sus cuotas, sus horas y sus inscripciones. No se puede deshacer. ¿Seguir?\')" aria-label="' . esc_attr(
			sprintf(
				/* translators: %s: member name */
				__( 'Eliminar definitivamente a %s', 'convoca-members' ),
				$item->post_title
			)
		) . '">' . __( 'Eliminar definitivamente', 'convoca-members' ) . '</a>';

		return $name_link . $this->row_actions( $actions );
	}

	public function column_email( $item ): string {
		$email = get_post_meta( $item->ID, '_convoca_email', true );
		if ( ! $email ) {
			return '—';
		}
		return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}

	public function column_telefono( $item ): string {
		$tel = get_post_meta( $item->ID, '_convoca_telefono', true );
		if ( ! $tel ) {
			return '—';
		}
		return '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $tel ) . '</a>';
	}

	public function column_whatsapp( $item ): string {
		$has_wa = get_post_meta( $item->ID, '_convoca_whatsapp', true );
		if ( $has_wa === 'no' ) {
			return '<span style="color:#999">' . esc_html__( 'No', 'convoca-members' ) . '</span>';
		}

		$wa_url = CPT_Miembro::whatsapp_link( $item->ID, self::WA_MSG );
		if ( $wa_url ) {
			return '<a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener" '
				. 'style="color:#25D366;font-weight:600" title="Abrir chat en WhatsApp">📱 Enviar</a>';
		}

		return $has_wa === 'si' ? '✅' : '—';
	}

	public function column_tipo( $item ): string {
		$terms = wp_get_object_terms( $item->ID, 'tipo_miembro', array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) ) {
			return '—';
		}
		return esc_html( implode( ', ', $terms ) );
	}

	public function column_estado( $item ): string {
		$estado = get_post_meta( $item->ID, '_convoca_estado_miembro', true ) ?: 'pendiente_documentacion';
		return Estados::badge_html( $estado );
	}

	public function column_plan( $item ): string {
		$plan = get_post_meta( $item->ID, '_convoca_plan', true );
		$sub  = get_post_meta( $item->ID, '_convoca_sub_plan', true );
		$key  = $sub ?: $plan;
		$data = CPT_Miembro::get_plan( $key );
		return esc_html( ( $data && isset( $data['label'] ) ) ? $data['label'] : ucfirst( $key ?: '—' ) );
	}

	public function column_cuota( $item ): string {
		$importe = get_post_meta( $item->ID, '_convoca_importe_cuota', true );
		$estado  = get_post_meta( $item->ID, '_convoca_estado_cuota', true );
		$label   = CPT_Miembro::ESTADO_CUOTA[ $estado ] ?? '—';
		$amount  = $importe ? number_format( (float) $importe, 0 ) . '€' : '—';

		if ( $estado === 'activa' ) {
			$badge = '<span class="convoca-badge convoca-badge--confirmed">' . esc_html( $label ) . '</span>';
		} elseif ( $estado === 'pendiente' ) {
			$badge = '<span class="convoca-badge convoca-badge--pending">' . esc_html( $label ) . '</span>';
		} elseif ( $estado === 'vencida' ) {
			$badge = '<span class="convoca-badge convoca-badge--cancelled">' . esc_html( $label ) . '</span>';
		} else {
			$badge = '—';
		}

		return $amount . ' ' . $badge;
	}

	public function column_recurrente( $item ): string {
		$recurrente = get_post_meta( $item->ID, '_convoca_pago_recurrente', true );
		if ( $recurrente === '1' ) {
			return '<span class="dashicons dashicons-yes" style="color:#2271b1"></span> ' . __( 'Sí', 'convoca-members' );
		}
		return '<span class="dashicons dashicons-no-alt" style="color:#666"></span> ' . __( 'No', 'convoca-members' );
	}

	public function column_fecha( $item ): string {
		return get_the_date( 'd/m/Y', $item );
	}

	/**
	 * Quita la acción «Papelera» del listado crudo del tipo `miembro` (issue #27).
	 *
	 * @param array   $acciones Acciones de la fila.
	 * @param \WP_Post $post     Entrada de la fila.
	 * @return array
	 */
	public function sin_papelera( $acciones, $post ) {
		if ( 'miembro' === $post->post_type ) {
			unset( $acciones['trash'] );
		}
		return $acciones;
	}

	/**
	 * Quita «Mover a la papelera» del desplegable de lote del listado crudo (issue #27).
	 *
	 * @param array $acciones Acciones de lote.
	 * @return array
	 */
	public function sin_papelera_bulk( $acciones ) {
		unset( $acciones['trash'] );
		return $acciones;
	}
}
