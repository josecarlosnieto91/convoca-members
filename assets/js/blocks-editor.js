( function( blocks, element ) {
    var el = element.createElement;

    function registerMembersBlock( name, title, icon, desc, color ) {
        blocks.registerBlockType( 'biodevas-members/' + name, {
            apiVersion: 3,
            title: title,
            icon: icon,
            category: 'biodevas-members',
            edit: function() {
                return el( 'div', {
                    style: {
                        padding: '20px',
                        background: color,
                        border: '2px dashed #059669',
                        borderRadius: '8px',
                        textAlign: 'center'
                    }
                },
                    el( 'span', { className: 'dashicons dashicons-' + icon, style: { fontSize: '36px', color: '#059669' } } ),
                    el( 'p', { style: { fontWeight: 600, marginTop: '10px' } }, title ),
                    el( 'p', { style: { fontSize: '12px', color: '#6b7280' } }, desc )
                );
            },
            save: function() { return null; }
        } );
    }

    registerMembersBlock(
        'formulario-alta',
        'Formulario de Alta',
        'id-alt',
        'Formulario de registro multi-paso para nuevos socios de Biodevas.',
        '#f0fdf4'
    );
    registerMembersBlock(
        'mi-area',
        'Área de Socios',
        'admin-users',
        'Panel privado: datos personales, carnet digital, inscripciones, voluntariado y pagos.',
        '#eff6ff'
    );
    registerMembersBlock(
        'formulario-voluntariado',
        'Formulario de Voluntariado',
        'heart',
        'Formulario de registro para nuevos voluntarios con áreas de interés.',
        '#fdf2f8'
    );
    registerMembersBlock(
        'verificar-certificado',
        'Verificar Certificado',
        'awards',
        'Página pública para verificar la autenticidad de certificados de voluntariado.',
        '#fefce8'
    );
} )( window.wp.blocks, window.wp.element );
