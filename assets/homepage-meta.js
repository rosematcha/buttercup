/* global jQuery */
( function ( $ ) {
	const frames = new Map();

	function ratioWarnings(
		label,
		width,
		height,
		targetRatio,
		minWidth,
		minHeight
	) {
		const warnings = [];
		if ( ! width || ! height ) {
			return warnings;
		}

		if ( width < minWidth || height < minHeight ) {
			warnings.push(
				`${ label } image is ${ width }x${ height }. Suggested minimum is ${ minWidth }x${ minHeight }.`
			);
		}

		const ratio = width / height;
		const diff = Math.abs( ratio - targetRatio ) / targetRatio;
		if ( diff > 0.07 ) {
			warnings.push(
				`${ label } image ratio is about ${ ratio.toFixed(
					2
				) }:1. Suggested ratio is ${ targetRatio.toFixed( 2 ) }:1.`
			);
		}

		return warnings;
	}

	function renderField( $field, media ) {
		const $id = $field.find( '.buttercup-homepage-image-id' );
		const $preview = $field.find( '.buttercup-homepage-image-preview' );
		const $clear = $field.find( '.buttercup-homepage-image-clear' );
		const $dimensions = $field.find(
			'.buttercup-homepage-image-dimensions'
		);

		if ( ! media ) {
			$id.val( '' );
			$preview.html( '<em>No image selected.</em>' );
			$clear.hide();
			$dimensions.empty();
			return;
		}

		const imageUrl =
			media.sizes && media.sizes.medium
				? media.sizes.medium.url
				: media.url;
		const width = Number( media.width || 0 );
		const height = Number( media.height || 0 );

		$id.val( media.id || '' );
		$preview.html(
			`<img src="${ imageUrl }" alt="" style="max-width:100%;height:auto;">`
		);
		$clear.show();

		const label = String( $field.data( 'label' ) || 'Selected' );
		const targetRatio = Number( $field.data( 'targetRatio' ) || 0 );
		const minWidth = Number( $field.data( 'minWidth' ) || 0 );
		const minHeight = Number( $field.data( 'minHeight' ) || 0 );
		const warnings = ratioWarnings(
			label,
			width,
			height,
			targetRatio,
			minWidth,
			minHeight
		);

		$dimensions.empty();
		warnings.forEach( ( warning ) => {
			$dimensions.append(
				`<p class="description" style="color:#b45309;">${ warning }</p>`
			);
		} );
	}

	function getFieldFromEvent( target ) {
		const $field = $( target ).closest( '.buttercup-homepage-image-field' );
		return $field.length ? $field : null;
	}

	function getOrCreateFrame( $field ) {
		const key = $field.get( 0 );
		if ( frames.has( key ) ) {
			return frames.get( key );
		}

		const frame = wp.media( {
			title: 'Select image',
			button: { text: 'Use image' },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			const selection = frame.state().get( 'selection' ).first();
			if ( ! selection ) {
				return;
			}
			renderField( $field, selection.toJSON() );
		} );

		frames.set( key, frame );
		return frame;
	}

	$( document ).on(
		'click',
		'.buttercup-homepage-image-select',
		function ( event ) {
			event.preventDefault();
			const $field = getFieldFromEvent( event.currentTarget );
			if ( ! $field ) {
				return;
			}

			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}

			const frame = getOrCreateFrame( $field );
			frame.open();
		}
	);

	$( document ).on(
		'click',
		'.buttercup-homepage-image-clear',
		function ( event ) {
			event.preventDefault();
			const $field = getFieldFromEvent( event.currentTarget );
			if ( ! $field ) {
				return;
			}

			renderField( $field, null );
		}
	);
} )( jQuery );
