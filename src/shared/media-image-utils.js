export function getMediaSizeCandidates( mediaItem ) {
	if ( ! mediaItem ) {
		return [];
	}
	const sizes = mediaItem.media_details?.sizes || mediaItem.sizes || {};
	const candidates = Object.values( sizes )
		.map( ( size ) => ( {
			url: size?.source_url || size?.url,
			width: size?.width,
			height: size?.height,
		} ) )
		.filter( ( size ) => size.url && size.width );

	if ( mediaItem.source_url && mediaItem.media_details?.width ) {
		candidates.push( {
			url: mediaItem.source_url,
			width: mediaItem.media_details.width,
			height: mediaItem.media_details.height,
		} );
	}

	return candidates;
}

export function filterByAspectRatio( candidates, mediaItem, tolerance = 0.08 ) {
	const originalWidth = mediaItem?.media_details?.width || 0;
	const originalHeight = mediaItem?.media_details?.height || 0;
	if ( ! originalWidth || ! originalHeight ) {
		return candidates;
	}
	const originalRatio = originalWidth / originalHeight;

	return candidates.filter( ( item ) => {
		if ( ! item.width || ! item.height ) {
			return false;
		}
		const ratio = item.width / item.height;
		if ( Math.abs( originalRatio - 1 ) <= 0.05 ) {
			return true;
		}
		return Math.abs( ratio - originalRatio ) / originalRatio <= tolerance;
	} );
}

export function filterOutCropped( candidates, mediaItem ) {
	const sizes = mediaItem?.media_details?.sizes || mediaItem?.sizes || {};
	const cropMap = new Map();
	Object.values( sizes ).forEach( ( size ) => {
		const url = size?.source_url || size?.url;
		if ( ! url ) {
			return;
		}
		cropMap.set( url, !! size?.crop );
	} );
	return candidates.filter( ( item ) => ! cropMap.get( item.url ) );
}

export function getResponsiveCandidates( mediaItem ) {
	return filterOutCropped(
		filterByAspectRatio( getMediaSizeCandidates( mediaItem ), mediaItem ),
		mediaItem
	);
}

export function buildSrcSetFromMedia( mediaItem ) {
	const seen = new Set();
	return getResponsiveCandidates( mediaItem )
		.filter( ( item ) => {
			if ( seen.has( item.url ) ) {
				return false;
			}
			seen.add( item.url );
			return true;
		} )
		.sort( ( a, b ) => a.width - b.width )
		.map( ( item ) => `${ item.url } ${ item.width }w` )
		.join( ', ' );
}

export function pickBestCandidate( mediaItem, targetWidth ) {
	const candidates = getResponsiveCandidates( mediaItem ).sort(
		( a, b ) => a.width - b.width
	);
	if ( ! candidates.length ) {
		return { url: mediaItem?.url || '', width: 0, height: 0 };
	}
	return (
		candidates.find( ( item ) => item.width >= targetWidth ) ||
		candidates[ candidates.length - 1 ]
	);
}
