import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Notice,
	Spinner,
	FormTokenField,
	Button,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { cleanForSlug } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import { buildHomepageFeedStatusQuery } from '../shared/rest-query-utils';

const feedDefaults = window.buttercupDefaults?.feed || {};

function normalizeSlug( value, fallback ) {
	const slug = cleanForSlug( String( value || '' ).trim() );
	return slug || fallback;
}

function normalizeTokenSlug( tokens ) {
	if ( ! Array.isArray( tokens ) || tokens.length === 0 ) {
		return '';
	}
	return normalizeSlug( tokens[ tokens.length - 1 ], '' );
}

export default function Edit( { attributes, setAttributes } ) {
	const { ctaLabel, homeTagSlug, mastTagSlug, renderMode } = attributes;

	const mode = renderMode || 'all';
	const isMast = mode === 'mast';
	const isHomeAll = mode === 'home-all';

	const tags = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'post_tag', {
				per_page: 100,
				hide_empty: false,
			} ) || [],
		[]
	);

	const [ status, setStatus ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ errorMessage, setErrorMessage ] = useState( '' );
	const [ previewViewport, setPreviewViewport ] = useState( 'desktop' );
	const statusRequestRef = useRef( 0 );

	const tagSuggestions = useMemo(
		() =>
			tags
				.map( ( term ) => term.slug )
				.filter( Boolean )
				.sort( ( a, b ) => a.localeCompare( b ) ),
		[ tags ]
	);

	// Fetch status from REST API.
	useEffect( () => {
		const mast = normalizeSlug(
			mastTagSlug,
			feedDefaults.mastTagSlug || 'mast'
		);
		const home = normalizeSlug(
			homeTagSlug,
			feedDefaults.homeTagSlug || 'home'
		);

		const requestId = statusRequestRef.current + 1;
		statusRequestRef.current = requestId;
		setIsLoading( true );
		setErrorMessage( '' );

		const controller =
			typeof AbortController !== 'undefined'
				? new AbortController()
				: null;

		const timer = setTimeout( () => {
			const query = buildHomepageFeedStatusQuery( {
				mastTagSlug: mast,
				homeTagSlug: home,
			} );

			apiFetch( {
				path: `/buttercup/v1/homepage-feed-status?${ query.toString() }`,
				signal: controller?.signal,
			} )
				.then( ( data ) => {
					if ( requestId !== statusRequestRef.current ) {
						return;
					}
					setStatus( data );
					setIsLoading( false );
				} )
				.catch( ( error ) => {
					if (
						requestId !== statusRequestRef.current ||
						error?.name === 'AbortError'
					) {
						return;
					}
					setStatus( null );
					setIsLoading( false );
					setErrorMessage(
						error?.message ||
							__( 'Unable to load feed status.', 'buttercup' )
					);
				} );
		}, 280 );

		return () => {
			clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}
		};
	}, [ mastTagSlug, homeTagSlug ] );

	const blockProps = useBlockProps( {
		className: 'buttercup-homepage-feed-editor',
	} );

	/* ── Derive summary info ── */
	const mastTitle =
		! isLoading && status?.mastSelected?.length
			? status.mastSelected[ 0 ].title
			: null;

	const homeCount = status?.homeSelected?.length || 0;
	const homeItems = status?.homeSelected || [];

	/* ── Title and description per mode ── */
	let blockTitle, blockSummary;

	if ( isMast ) {
		blockTitle = __( 'Homepage Mast', 'buttercup' );
		blockSummary = mastTitle
			? `${ __( 'Showing:', 'buttercup' ) } ${ mastTitle }`
			: __(
					'Will show the newest mast-tagged post or page.',
					'buttercup'
			  );
	} else if ( isHomeAll ) {
		blockTitle = __( 'Homepage Items', 'buttercup' );
		if ( isLoading ) {
			blockSummary = __( 'Loading\u2026', 'buttercup' );
		} else if ( homeCount > 0 ) {
			const itemLabel =
				homeCount === 1
					? __( 'item', 'buttercup' )
					: __( 'items', 'buttercup' );
			blockSummary = `${ homeCount } ${ itemLabel } ${ __(
				'from home-tagged content.',
				'buttercup'
			) }`;
		} else {
			blockSummary = __(
				'No home-tagged content found. Tag posts or pages to populate this area.',
				'buttercup'
			);
		}
	} else {
		blockTitle = __( 'Homepage Feed', 'buttercup' );
		blockSummary = __(
			'Renders mast and home items together.',
			'buttercup'
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Tag Settings', 'buttercup' ) }
					initialOpen={ true }
				>
					{ tagSuggestions.length === 0 && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'No tags found. Create tags in Posts \u2192 Tags, then assign them to posts or pages.',
								'buttercup'
							) }
						</Notice>
					) }
					<p
						style={ {
							fontSize: 12,
							color: '#757575',
							margin: '0 0 16px',
						} }
					>
						{ isMast
							? __(
									'Tag a post or page with a "mast" tag to feature it here as the hero item.',
									'buttercup'
							  )
							: __(
									'Tag posts or pages with a "home" tag and they will automatically appear here.',
									'buttercup'
							  ) }
					</p>
					{ ( isMast || ( ! isMast && ! isHomeAll ) ) && (
						<>
							<FormTokenField
								label={ __( 'Mast Tag', 'buttercup' ) }
								value={ mastTagSlug ? [ mastTagSlug ] : [] }
								onChange={ ( tokens ) =>
									setAttributes( {
										mastTagSlug:
											normalizeTokenSlug( tokens ),
									} )
								}
								suggestions={ tagSuggestions }
								help={ `${ __(
									'Defaults to',
									'buttercup'
								) } "${
									feedDefaults.mastTagSlug || 'mast'
								}".` }
								__nextHasNoMarginBottom
							/>
						</>
					) }
					{ ( isHomeAll || ( ! isMast && ! isHomeAll ) ) && (
						<FormTokenField
							label={ __( 'Home Tag', 'buttercup' ) }
							value={ homeTagSlug ? [ homeTagSlug ] : [] }
							onChange={ ( tokens ) =>
								setAttributes( {
									homeTagSlug: normalizeTokenSlug( tokens ),
								} )
							}
							suggestions={ tagSuggestions }
							help={ `${ __( 'Defaults to', 'buttercup' ) } "${
								feedDefaults.homeTagSlug || 'home'
							}".` }
							__nextHasNoMarginBottom
						/>
					) }
					<TextControl
						label={ __( 'CTA Label', 'buttercup' ) }
						value={ ctaLabel }
						onChange={ ( v ) => setAttributes( { ctaLabel: v } ) }
						help={ __(
							'Button text on items with no hero image.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				{ isHomeAll && ! isLoading && homeCount > 0 && (
					<PanelBody
						title={ `${ __(
							'Active Items',
							'buttercup'
						) } (${ homeCount })` }
						initialOpen={ false }
					>
						<ol style={ { margin: 0, paddingLeft: 20 } }>
							{ homeItems.map( ( item ) => (
								<li
									key={ item.id }
									style={ {
										fontSize: 13,
										marginBottom: 4,
									} }
								>
									{ item.title ||
										__( '(Untitled)', 'buttercup' ) }
								</li>
							) ) }
						</ol>
						<p
							style={ {
								fontSize: 12,
								color: '#757575',
								marginTop: 12,
							} }
						>
							{ __(
								'Ordered by publish date (newest first). Change a post\u2019s date to reorder.',
								'buttercup'
							) }
						</p>
					</PanelBody>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				<h3>{ blockTitle }</h3>
				<p className="buttercup-homepage-feed-editor__summary">
					{ blockSummary }
				</p>

				{ errorMessage && (
					<Notice status="error" isDismissible={ false }>
						{ errorMessage }
					</Notice>
				) }
				{ isLoading && (
					<div className="buttercup-homepage-feed-editor__loading">
						<Spinner />
					</div>
				) }

				{ status?.mastOverflow && ( isMast || ! isHomeAll ) && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'More than one mast-tagged item found. Only the newest will render.',
							'buttercup'
						) }
					</Notice>
				) }
				{ status?.dualTagged?.length > 0 && ! isMast && ! isHomeAll && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Some items have both mast and home tags. They are treated as mast-only.',
							'buttercup'
						) }
					</Notice>
				) }

				<div className="buttercup-homepage-feed-editor__preview">
					<div className="buttercup-homepage-feed-editor__preview-controls">
						{ [ 'desktop', 'tablet', 'mobile' ].map( ( vp ) => (
							<Button
								key={ vp }
								variant={
									previewViewport === vp
										? 'primary'
										: 'secondary'
								}
								onClick={ () => setPreviewViewport( vp ) }
							>
								{ vp.charAt( 0 ).toUpperCase() + vp.slice( 1 ) }
							</Button>
						) ) }
					</div>
					<div
						className={ `buttercup-homepage-feed-editor__preview-frame is-${ previewViewport }` }
					>
						<div className="buttercup-homepage-feed-editor__preview-body">
							<ServerSideRender
								block="buttercup/homepage-feed"
								attributes={ {
									...attributes,
									mastTagSlug: normalizeSlug(
										mastTagSlug,
										feedDefaults.mastTagSlug || 'mast'
									),
									homeTagSlug: normalizeSlug(
										homeTagSlug,
										feedDefaults.homeTagSlug || 'home'
									),
									renderMode: mode,
								} }
								httpMethod="GET"
								skipBlockSupportAttributes
							/>
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
