function cleanForSlug( value ) {
	return String( value || '' )
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9\s-]/g, '' )
		.replace( /\s+/g, '-' )
		.replace( /-+/g, '-' )
		.replace( /^-|-$/g, '' );
}

export function sanitizeIntInRange( value, fallback, min, max ) {
	const parsed = parseInt( value, 10 );
	if ( Number.isNaN( parsed ) ) {
		return fallback;
	}
	return Math.min( max, Math.max( min, parsed ) );
}

export function sanitizeSlug( value, fallback = '' ) {
	const slug = cleanForSlug( String( value || '' ).trim() );
	return slug || fallback;
}

export function booleanToRestInt( value ) {
	return value ? '1' : '0';
}

export function buildHomepageFeedStatusQuery( { mastTagSlug, homeTagSlug } ) {
	return new URLSearchParams( {
		mastTagSlug: sanitizeSlug( mastTagSlug, 'mast' ),
		homeTagSlug: sanitizeSlug( homeTagSlug, 'home' ),
	} );
}

export function buildTagShowcaseStatusQuery( {
	tagSlugs,
	tagMatch,
	postTypes,
	excludeCurrentPost,
	offset,
	maxItems,
} ) {
	const slugs = Array.isArray( tagSlugs )
		? tagSlugs.map( ( slug ) => sanitizeSlug( slug ) ).filter( Boolean )
		: [];
	const types = Array.isArray( postTypes )
		? postTypes
				.map( ( type ) => String( type || '' ).trim() )
				.filter( Boolean )
		: [];
	const normalizedTagMatch = [ 'any', 'all' ].includes( String( tagMatch ) )
		? String( tagMatch )
		: 'any';

	return new URLSearchParams( {
		tagSlugs: slugs.join( ',' ),
		tagMatch: normalizedTagMatch,
		postTypes: types.join( ',' ),
		excludeCurrentPost: booleanToRestInt( !! excludeCurrentPost ),
		offset: String( sanitizeIntInRange( offset, 0, 0, 50 ) ),
		maxItems: String( sanitizeIntInRange( maxItems, 12, 1, 60 ) ),
	} );
}
