( function () {
	const preservePagePosition = ( duration = 100 ) => {
		const scrollLeft = window.scrollX;
		const scrollTop = window.scrollY;
		const body = document.body;
		const restoreScroll = () => {
			window.removeEventListener( 'scroll', restoreScroll );

			if ( 'fixed' === body.style.position ) {
				body.style.top = `-${ scrollTop }px`;
				return;
			}

			const scrollBehavior = document.documentElement.style.scrollBehavior;
			document.documentElement.style.scrollBehavior = 'auto';
			window.scrollTo( scrollLeft, scrollTop );
			document.documentElement.style.scrollBehavior = scrollBehavior;
		};
		const bodyObserver = new MutationObserver( () => {
			if ( 'fixed' === body.style.position && `-${ scrollTop }px` !== body.style.top ) {
				body.style.top = `-${ scrollTop }px`;
			}
		} );

		bodyObserver.observe( body, { attributes: true, attributeFilter: [ 'style' ] } );
		window.addEventListener( 'scroll', restoreScroll );
		window.setTimeout( () => {
			bodyObserver.disconnect();
			window.removeEventListener( 'scroll', restoreScroll );
		}, duration );
	};

	document.querySelectorAll( '[data-progress-agentic-rag-search-widget]' ).forEach( ( container ) => {
		const searchBar = container.querySelector( 'nuclia-search-bar' );
		const searchResults = container.querySelector( 'nuclia-search-results' );

		if ( ! searchBar ) {
			return;
		}

		searchBar.addEventListener( 'search', () => preservePagePosition() );

		if ( searchResults ) {
			const preserveResultOpenPosition = ( event ) => {
				if ( 'keyup' === event.type && 'Enter' !== event.key ) {
					return;
				}

				const opensDocument = event.composedPath().some( ( node ) => (
					node instanceof Element
					&& node.matches( '.result-title, .paragraph-result-container, .thumbnail-container' )
				) );

				if ( opensDocument ) {
					preservePagePosition( 250 );
				}
			};

			searchResults.addEventListener( 'click', preserveResultOpenPosition, true );
			searchResults.addEventListener( 'keyup', preserveResultOpenPosition, true );
		}
	} );
} )();
