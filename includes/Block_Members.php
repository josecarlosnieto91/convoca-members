<?php
/**
 * Gutenberg block registration for all Members blocks.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Members {


	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_assets(): void {
		wp_register_style(
			'convoca-mi-area',
			CONV_MEMBERS_URL . 'public/assets/mi-area.css',
			array(),
			CONV_MEMBERS_VERSION
		);

		wp_register_script(
			'conv-blocks-editor',
			CONV_MEMBERS_URL . 'assets/js/blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ),
			CONV_MEMBERS_VERSION,
			true
		);
	}

	public function register_blocks(): void {
		// 1. Formulario de Alta
		register_block_type(
			'convoca-members/formulario-alta',
			array(
				'apiVersion'      => 3,
				'title'           => __( 'Formulario de Alta', 'convoca-members' ),
				'category'        => 'convoca-members',
				'icon'            => 'id-alt',
				'description'     => __( 'Formulario de registro multi-paso para nuevos socios.', 'convoca-members' ),
				'keywords'        => array( 'alta', 'socio', 'registro', 'convoca-core' ),
				'render_callback' => array( $this, 'render_alta' ),
				'editor_script'   => 'conv-blocks-editor',
				'style'           => 'convoca-mi-area', // Reusing the style handle from Mi_Area if registered.
			)
		);

		// 2. Área de Socios
		register_block_type(
			'convoca-members/mi-area',
			array(
				'apiVersion'      => 3,
				'title'           => __( 'Área de Socios', 'convoca-members' ),
				'category'        => 'convoca-members',
				'icon'            => 'admin-users',
				'description'     => __( 'Panel privado de socios: datos, carnet, inscripciones, voluntariado y pagos.', 'convoca-members' ),
				'keywords'        => array( 'socios', 'panel', 'área privada' ),
				'render_callback' => array( $this, 'render_mi_area' ),
				'editor_script'   => 'conv-blocks-editor',
				'style'           => 'convoca-mi-area',
				'editor_style'    => 'convoca-mi-area',
			)
		);

		// 3. Formulario de Voluntariado
		register_block_type(
			'convoca-members/formulario-voluntariado',
			array(
				'apiVersion'      => 3,
				'title'           => __( 'Formulario de Voluntariado', 'convoca-members' ),
				'category'        => 'convoca-members',
				'icon'            => 'heart',
				'description'     => __( 'Formulario de registro para nuevos voluntarios.', 'convoca-members' ),
				'keywords'        => array( 'voluntariado', 'registro', 'voluntario' ),
				'render_callback' => array( $this, 'render_voluntariado' ),
				'editor_script'   => 'conv-blocks-editor',
				'style'           => 'convoca-mi-area',
			)
		);

		// 4. Verificar Certificado
		register_block_type(
			'convoca-members/verificar-certificado',
			array(
				'apiVersion'      => 3,
				'title'           => __( 'Verificar Certificado', 'convoca-members' ),
				'category'        => 'convoca-members',
				'icon'            => 'awards',
				'description'     => __( 'Página de verificación de certificados de voluntariado.', 'convoca-members' ),
				'keywords'        => array( 'certificado', 'verificar', 'voluntariado' ),
				'render_callback' => array( $this, 'render_verificar_certificado' ),
				'editor_script'   => 'conv-blocks-editor',
				'style'           => 'convoca-mi-area',
			)
		);
	}

	public function editor_assets(): void {
		// Handled by register_assets and block registration.
	}

	public function render_alta( array $attrs ): string {
		$handler = new Form_Handler();
		return $handler->render();
	}

	public function render_mi_area( array $attrs ): string {
		$area = new Mi_Area();
		return $area->render();
	}

	public function render_voluntariado( array $attrs ): string {
		$form = new Form_Voluntariado();
		return $form->render();
	}

	public function render_verificar_certificado( array $attrs ): string {
		$verifier = new Verificar_Certificado();
		return $verifier->render();
	}
}
