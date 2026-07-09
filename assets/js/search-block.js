( function ( blocks, blockEditor, components, element, i18n ) {
	if ( ! blocks || ! blockEditor || ! components || ! element || ! i18n ) {
		return;
	}

	const { createElement: el } = element;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { CheckboxControl, PanelBody } = components;
	const { __ } = i18n;
	const featureOptions = {
		answers: __( 'Answers', 'progress-agentic-rag' ),
		rephrase: __( 'Rephrase', 'progress-agentic-rag' ),
		filter: __( 'Filters', 'progress-agentic-rag' ),
		suggestions: __( 'Suggestions', 'progress-agentic-rag' ),
	};
	const defaultFeatures = Object.keys( featureOptions );

	blocks.registerBlockType( 'progress-agentic-rag/search', {
		apiVersion: 2,
		title: __( 'Progress Agentic RAG Search', 'progress-agentic-rag' ),
		icon: 'search',
		category: 'widgets',
		attributes: {
			features: {
				type: 'array',
				default: defaultFeatures,
			},
		},
		edit( { attributes, setAttributes } ) {
			const features = Array.isArray( attributes.features ) && attributes.features.length ? attributes.features : defaultFeatures;
			const blockProps = useBlockProps( { className: 'progress-agentic-rag-search-widget-placeholder' } );
			const toggleFeature = ( feature, checked ) => {
				const next = checked ? [ ...features, feature ] : features.filter( ( item ) => item !== feature );
				setAttributes( { features: next.length ? Array.from( new Set( next ) ) : defaultFeatures } );
			};

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Widget options', 'progress-agentic-rag' ) },
						defaultFeatures.map( ( feature ) =>
							el( CheckboxControl, {
								key: feature,
								label: featureOptions[ feature ],
								checked: features.includes( feature ),
								onChange: ( checked ) => toggleFeature( feature, checked ),
							} )
						)
					)
				),
					el( 'strong', {}, __( 'Progress Agentic RAG Search', 'progress-agentic-rag' ) ),
					el( 'p', {}, __( 'Progress RAG Agentic Search', 'progress-agentic-rag' ) )
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp && window.wp.blocks, window.wp && window.wp.blockEditor, window.wp && window.wp.components, window.wp && window.wp.element, window.wp && window.wp.i18n );
