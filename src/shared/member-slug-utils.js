import { stripHtmlText } from './block-tree-utils';

function cleanForSlug( value ) {
	return String( value || '' )
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9\s-]/g, '' )
		.replace( /\s+/g, '-' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' );
}

function splitName( rawName ) {
	const cleanName = stripHtmlText( rawName );
	const tokens = cleanName.split( /\s+/ ).filter( Boolean );
	return {
		first: tokens[ 0 ] || '',
		last: tokens.length > 1 ? tokens[ tokens.length - 1 ] : '',
	};
}

export function buildMemberSlugAssignments( members ) {
	const counts = {};
	const prepared = [];
	const used = new Set();
	const assignments = {};

	( Array.isArray( members ) ? members : [] ).forEach( ( member ) => {
		const clientId = member?.clientId;
		if ( ! clientId ) {
			return;
		}
		const existingSlug = cleanForSlug( member?.memberSlug || '' );
		if ( existingSlug ) {
			used.add( existingSlug );
		}

		const parts = splitName( member?.name || '' );
		if ( parts.first ) {
			const key = parts.first.toLowerCase();
			counts[ key ] = ( counts[ key ] || 0 ) + 1;
		}
		prepared.push( {
			clientId,
			existingSlug,
			...parts,
		} );
	} );

	prepared.forEach( ( item ) => {
		if ( item.existingSlug || ! item.first ) {
			return;
		}

		const needsLast = ( counts[ item.first.toLowerCase() ] || 0 ) > 1;
		const base =
			needsLast && item.last
				? `${ item.first } ${ item.last }`
				: item.first;
		const seed = cleanForSlug( base );
		if ( ! seed ) {
			assignments[ item.clientId ] = '';
			return;
		}

		let unique = seed;
		let index = 2;
		while ( used.has( unique ) ) {
			unique = `${ seed }-${ index }`;
			index += 1;
		}
		used.add( unique );
		assignments[ item.clientId ] = unique;
	} );

	return assignments;
}
