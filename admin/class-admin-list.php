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

		// Reconciliación (members#39): la acción va montada con las demás en `column_nombre()` (en esta pantalla
		// `post_row_actions` NO se pinta) y su manejador se engancha en el bootstrap del plugin, no aquí: este
		// constructor solo corre al pintar la lista, y el manejador tiene que estar cuando llega la petición.
	}

	/**
	 * Pone la ficha de acuerdo con sus datos: cuota pagada ⇒ condición de socio.
	 */
	public static function handle_reconciliar(): void {
		$id = (int) ( wp_unslash( $_GET['id'] ?? 0 ) );

		if ( ! $id || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ?? '' ), 'convoca_reconciliar_' . $id ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$cambios = Roles_Miembro::reconciliar( $id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'conv-members',
					'reconciliada' => $cambios ? 1 : 0,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
			'consentimiento' => __( 'Consentimiento', 'convoca-members' ),
			'renueva_horas'  => __( 'Renueva por horas', 'convoca-members' ),
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

		// Filter by privacidad (members#42): las solicitudes de supresión sin resolver.
		$privacidad_filter = sanitize_text_field( $get_data['privacidad_filter'] ?? '' );
		if ( 'supresion' === $privacidad_filter ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					'relation' => 'AND',
					array(
						'key'     => '_convoca_supresion_solicitada',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_convoca_supresion_resuelta',
						'compare' => 'NOT EXISTS',
					),
				)
			);
		}

		// Filter by consentimiento (members#41).
		$consentimiento_filter = sanitize_text_field( $get_data['consentimiento_filter'] ?? '' );
		if ( 'con' === $consentimiento_filter || 'sin' === $consentimiento_filter ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? array(),
				array(
					array(
						'key'     => '_convoca_consent_timestamp',
						'compare' => ( 'con' === $consentimiento_filter ) ? 'EXISTS' : 'NOT EXISTS',
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
				<option value="socio" <?php selected( $get_data['tipo_filter'] ?? '', 'socio' ); ?>><?php esc_html_e( 'Socio/a', 'convoca-members' ); ?></option>
				<option value="voluntario" <?php selected( $get_data['tipo_filter'] ?? '', 'voluntario' ); ?>><?php esc_html_e( 'Voluntario/a', 'convoca-members' ); ?></option>
				<option value="socio_voluntario" <?php selected( $get_data['tipo_filter'] ?? '', 'socio_voluntario' ); ?>><?php esc_html_e( 'Socio + Vol.', 'convoca-members' ); ?></option>
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
			<select name="privacidad_filter">
				<option value="">
					<?php esc_html_e( 'Privacidad (todas)', 'convoca-members' ); ?>
				</option>
				<option value="supresion" <?php selected( $get_data['privacidad_filter'] ?? '', 'supresion' ); ?>><?php esc_html_e( 'Con supresión pedida', 'convoca-members' ); ?></option>
			</select>
			<select name="consentimiento_filter">
				<option value="">
					<?php esc_html_e( 'Consentimiento (todos)', 'convoca-members' ); ?>
				</option>
				<option value="con" <?php selected( $get_data['consentimiento_filter'] ?? '', 'con' ); ?>><?php esc_html_e( 'Con consentimiento', 'convoca-members' ); ?></option>
				<option value="sin" <?php selected( $get_data['consentimiento_filter'] ?? '', 'sin' ); ?>><?php esc_html_e( 'Sin consentimiento', 'convoca-members' ); ?></option>
			</select>
			<?php submit_button( __( 'Filtrar', 'convoca-members' ), '', 'filter_action', false ); ?>
		</div>
		<?php
		if ( 'ok' === ( $get_data['convoca_consentimiento'] ?? '' ) ) {
			?>
			<div class="notice notice-success inline" style="margin: 8px 0;">
				<p><?php esc_html_e( 'Consentimiento registrado: queda la fecha y la versión del texto aceptado.', 'convoca-members' ); ?></p>
			</div>
			<?php
		}

		// Contador de lo que falta por justificar (members#41): el RGPD pide poder DEMOSTRAR el
		// consentimiento, y hasta ahora no había ni forma de saber a quién le faltaba.
		$sin_consentimiento = GDPR_Tools::contar_sin_consentimiento();
		if ( $sin_consentimiento > 0 ) :
			?>
		<div class="notice notice-warning inline" style="margin: 8px 0;">
			<p>
				<?php
				printf(
					/* translators: %d: number of members without registered consent. */
					esc_html( _n( '%d socio activo sin consentimiento registrado.', '%d socios activos sin consentimiento registrado.', $sin_consentimiento, 'convoca-members' ) ),
					(int) $sin_consentimiento
				);
				?>
				<a href="<?php echo esc_url( add_query_arg( 'consentimiento_filter', 'sin', remove_query_arg( array( 'paged', 'convoca_consentimiento' ) ) ) ); ?>"><?php esc_html_e( 'Ver cuáles', 'convoca-members' ); ?></a>
			</p>
		</div>
			<?php
		endif;
		?>
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

		// Reconciliación con los datos de la ficha (members#39): cuota pagada y al día, pero sin la condición
		// de socio. Se ofrece aquí, en la pantalla donde la junta cuenta socios.
		if ( Roles_Miembro::tiene_cuota_pagada( (int) $item->ID ) && ! Roles_Miembro::es_socio( (int) $item->ID ) ) {
			$actions['convoca_reconciliar'] = '<a href="' . esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=convoca_reconciliar_ficha&id=' . $item->ID ),
					'convoca_reconciliar_' . $item->ID
				)
			) . '" style="color:#d63638" aria-label="' . esc_attr(
				sprintf(
					/* translators: %s: member name */
					__( 'Reconciliar la ficha de %s', 'convoca-members' ),
					$item->post_title
				)
			) . '">' . esc_html__( '⚠ Reconciliar', 'convoca-members' ) . '</a>';
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
		$actions['baja'] = '<a href="' . esc_url( $baja_url ) . '" onclick="return confirm(\'' . esc_js( __( '¿Dar de baja a este socio? La ficha se conserva y deja de contar como socio activo.', 'convoca-members' ) ) . '\')" aria-label="' . esc_attr(
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
		$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" style="color:#a00" onclick="return confirm(\'' . esc_js( __( 'Se borran la ficha, sus cuotas, sus horas y sus inscripciones. No se puede deshacer. ¿Seguir?', 'convoca-members' ) ) . '\')" aria-label="' . esc_attr(
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

	/**
	 * La columna «Tipo», con tres fuentes por orden de fiabilidad (issue #28).
	 *
	 * Salía en blanco en todas las filas porque leía la taxonomía `tipo_miembro`, que está registrada y
	 * vacía: no había ningún término que enseñar. Ahora se mira primero la taxonomía (que ahora sí se
	 * escribe al conceder una condición), después las condiciones de la ficha y, si aún no hay nada, el
	 * plan, que es el dato que existe desde siempre. Así la columna dice algo con los datos de hoy y
	 * dirá lo correcto cuando la ficha tenga las condiciones.
	 *
	 * @param \WP_Post $item Ficha de la fila.
	 * @return string
	 */
	public function column_tipo( $item ): string {
		if ( ! class_exists( 'Convoca\\Members\\Roles_Miembro' ) ) {
			return '—';
		}

		$id     = (int) $item->ID;
		$salida = esc_html( Roles_Miembro::etiqueta_tipo_miembro( $id ) );

		// El descuadre del informe members#39: la cuota está pagada y la ficha no dice «socio». Se avisa aquí,
		// en la pantalla donde la junta cuenta socios, en vez de dejar que lo descubra el socio al quejarse.
		if ( Roles_Miembro::tiene_cuota_pagada( $id ) && ! Roles_Miembro::es_socio( $id ) ) {
			$salida .= ' <span class="convoca-alerta-tipo" title="' . esc_attr__( 'Tiene la cuota pagada y al día, pero a la ficha le falta la condición de socio. Se arregla con «Reconciliar».', 'convoca-members' ) . '">⚠</span>';
		}

		return $salida;
	}

	/**
	 * Etiqueta legible de una condición de la ficha.
	 *
	 * Delega en `Roles_Miembro` para que el listado y el panel del socio no puedan decir cosas distintas
	 * de la misma condición.
	 *
	 * @param string $rol Condición (`socio`, `voluntario` o `monitor`).
	 * @return string
	 */
	public static function etiqueta_rol( $rol ): string {
		return Roles_Miembro::etiqueta( (string) $rol );
	}

	public function column_estado( $item ): string {
		$estado = get_post_meta( $item->ID, '_convoca_estado_miembro', true ) ?: 'pendiente_documentacion';
		$salida = Estados::badge_html( $estado );

		// La supresión de datos pedida desde el panel del socio (members#42): la junta tiene que verla en el
		// listado, no solo recibir el correo. Si no, la solicitud se queda sin resolver sin que nadie lo sepa.
		$privacidad = GDPR_Tools::estado_privacidad( (int) $item->ID );
		if ( $privacidad['pendiente'] ) {
			$salida .= '<br><span style="color:#b32d2e;" title="' . esc_attr( sprintf( /* translators: %s: date of the request. */ __( 'Pidió la supresión de sus datos el %s', 'convoca-members' ), $privacidad['solicitada'] ) ) . '">🗑️ ' . esc_html__( 'Pide supresión', 'convoca-members' ) . '</span>';
		}

		return $salida;
	}

	/**
	 * La columna «Consentimiento» (members#41).
	 *
	 * El consentimiento se registraba SOLO al darse de alta, así que la mitad del censo podía estar sin
	 * justificar y no había forma de verlo. Aquí se enseña la fecha y la versión del texto aceptado; si no
	 * hay constancia, se dice en rojo y bien claro, que es lo que la junta necesita saber.
	 *
	 * @param \WP_Post $item Ficha de la fila.
	 * @return string
	 */
	/**
	 * La columna «Renueva por horas» (members#45): quién ya ha hecho las horas de su plan y, por tanto, no hay
	 * que cobrarle. Es lo que la junta necesita para no mandar recibos de más.
	 *
	 * Sin filtro a propósito: las horas se **calculan** (horas del plan + horas aprobadas del ciclo), no son
	 * una meta que se pueda consultar en SQL. Un filtro obligaría a recalcular ficha a ficha en cada página.
	 *
	 * @param \WP_Post $item Ficha.
	 * @return string
	 */
	public function column_renueva_horas( $item ): string {
		$ciclo = \Convoca\Members\CPT_Miembro::ciclo_voluntariado( (int) $item->ID );

		if ( $ciclo['sin_plan'] ) {
			return '<span style="color:#646970;" title="' . esc_attr__( 'Su plan no pide horas: renueva pagando.', 'convoca-members' ) . '">—</span>';
		}

		$texto = esc_html( sprintf( '%s/%s h', $ciclo['horas'], $ciclo['objetivo'] ) );

		if ( $ciclo['cumple'] ) {
			return '<span style="color:#1a7f37;font-weight:600;" title="'
				. esc_attr__( 'Ya cumple: su próxima renovación es sin pago.', 'convoca-members' ) . '">✓ ' . $texto . '</span>';
		}

		return '<span title="' . esc_attr__( 'Todavía no llega: le faltan horas para renovar sin pago.', 'convoca-members' ) . '">'
			. $texto . ' <small>(' . esc_html( sprintf( /* translators: %s: hours still missing. */ __( 'faltan %s h', 'convoca-members' ), $ciclo['faltan'] ) ) . ')</small></span>';
	}

	public function column_consentimiento( $item ): string {
		$consentimiento = GDPR_Tools::etiqueta_consentimiento( (int) $item->ID );

		if ( ! $consentimiento['registrado'] ) {
			return '<span style="color:#b32d2e;" title="' . esc_attr__( 'Sin constancia de consentimiento', 'convoca-members' ) . '">'
				. esc_html__( 'Sin registrar', 'convoca-members' ) . '</span>'
				. $this->enlace_consentimiento( (int) $item->ID );
		}

		$texto = esc_html( $consentimiento['fecha'] );
		if ( '' !== $consentimiento['version'] ) {
			$texto .= '<br><small>' . esc_html( sprintf( /* translators: %s: version of the accepted text. */ __( 'texto v%s', 'convoca-members' ), $consentimiento['version'] ) ) . '</small>';
		}

		return $texto . $this->enlace_consentimiento( (int) $item->ID );
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
	/**
	 * El enlace para registrar el consentimiento de una fila (members#41).
	 *
	 * Va DENTRO de la columna y no en `post_row_actions`: medido en la demo, esa acción no se pinta en la
	 * pantalla propia del plugin (que no es la tabla de `edit.php`), así que el botón no aparecía por
	 * ninguna parte. Aquí se ve, que es de lo que se trata.
	 *
	 * @param int $miembro Ficha del socio.
	 * @return string HTML del enlace (vacío si no hay permiso).
	 */
	private function enlace_consentimiento( int $miembro ): string {
		if ( ! current_user_can( 'edit_post', $miembro ) ) {
			return '';
		}

		$registrado = GDPR_Tools::etiqueta_consentimiento( $miembro )['registrado'];

		return sprintf(
			'<br><a href="%s">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=convoca_registrar_consentimiento&miembro=' . $miembro ),
					'convoca_consentimiento_' . $miembro
				)
			),
			esc_html( $registrado ? __( 'Volver a registrar', 'convoca-members' ) : __( 'Registrar consentimiento', 'convoca-members' ) )
		);
	}

	/**
	 * Guarda el consentimiento desde el listado (members#41): deja fecha, la versión del texto vigente y
	 * una nota de que se registró a mano, para que se sepa de dónde sale el dato.
	 *
	 * Es ESTÁTICO a propósito: esta clase solo se instancia al pintar su pantalla, así que un gancho de
	 * instancia no llega nunca a `admin-post.php` y el botón no haría nada (medido en la demo, que es como
	 * se descubrió). El gancho se registra siempre desde el arranque del plugin.
	 */
	public static function registrar_consentimiento(): void {
		$miembro = (int) ( $_GET['miembro'] ?? 0 );

		if ( $miembro <= 0 || ! current_user_can( 'edit_post', $miembro ) ) {
			wp_die( esc_html__( 'No tienes permiso para registrar el consentimiento de este socio.', 'convoca-members' ) );
		}

		check_admin_referer( 'convoca_consentimiento_' . $miembro );

		$ajustes = get_option( 'convoca_members_settings', array() );
		GDPR_Tools::log_consent(
			$miembro,
			__( 'Consentimiento registrado por la junta desde el listado de socios (soporte: papel, correo o presencial).', 'convoca-members' ),
			(string) ( $ajustes['rgpd_version'] ?? '1.0' )
		);

		wp_safe_redirect(
			add_query_arg(
				'convoca_consentimiento',
				'ok',
				wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=conv-members' )
			)
		);
		exit;
	}

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
