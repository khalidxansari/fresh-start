( function ( wp ) {
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var blocks = [
		'hunza/song-list',
		'hunza/featured-song',
		'hunza/card-grid',
		'hunza/song-hero',
		'hunza/song-facts',
		'hunza/entity-header',
		'hunza/site-nav',
	];

	blocks.forEach( function ( name ) {
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				return el(
					'div',
					wp.blockEditor.useBlockProps(),
					el( ServerSideRender, { block: name, attributes: props.attributes } )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp );
