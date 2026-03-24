export function stripHtmlText( value ) {
	return String( value || '' )
		.replace( /<[^>]*>/g, '' )
		.trim();
}

export function collectTeamBlocks( blocks ) {
	const teamBlocks = [];

	const walk = ( list ) => {
		list.forEach( ( block ) => {
			if ( block.name === 'buttercup/team' ) {
				teamBlocks.push( block );
			}
			if ( block.innerBlocks?.length ) {
				walk( block.innerBlocks );
			}
		} );
	};

	walk( Array.isArray( blocks ) ? blocks : [] );

	return teamBlocks;
}

export function getTeamMembersFromBlock( teamBlock ) {
	return ( teamBlock?.innerBlocks || [] ).filter(
		( block ) => block.name === 'buttercup/team-member'
	);
}

export function collectAllTeamMembers( blocks ) {
	return collectTeamBlocks( blocks ).flatMap( ( teamBlock ) =>
		getTeamMembersFromBlock( teamBlock )
	);
}
