document.addEventListener( 'DOMContentLoaded', () => {
	const isMemberPage = document.body.classList.contains(
		'buttercup-team-member-page'
	);

	document
		.querySelectorAll( '.buttercup-team[data-member-pages="1"]' )
		.forEach( ( team ) => {
			const links = team.querySelectorAll(
				'.buttercup-team-member__link[data-member-slug]'
			);
			if ( ! links.length ) {
				return;
			}

			let basePath = window.location.pathname.replace( /\/$/, '' );
			if ( isMemberPage ) {
				const parts = basePath.split( '/' ).filter( Boolean );
				if ( parts.length ) {
					parts.pop();
					basePath = parts.length ? `/${ parts.join( '/' ) }` : '';
				}
			} else {
				const slugs = new Set(
					Array.from( links )
						.map( ( link ) => link.dataset.memberSlug )
						.filter( Boolean )
				);

				slugs.forEach( ( slug ) => {
					if ( basePath.endsWith( `/${ slug }` ) ) {
						basePath = basePath.slice( 0, -( slug.length + 1 ) );
					}
				} );
			}

			basePath = basePath.replace( /\/$/, '' );
			links.forEach( ( link ) => {
				const slug = link.dataset.memberSlug;
				if ( ! slug ) {
					return;
				}
				link.setAttribute( 'href', `${ basePath }/${ slug }` );
			} );
		} );
} );
