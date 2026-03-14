import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	ColorPalette,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	TextControl,
	Notice,
	Spinner,
	FormTokenField,
	CheckboxControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { cleanForSlug } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

function sanitizeSlug( value ) {
	const normalized = cleanForSlug( String( value || '' ).trim() );
	return normalized || '';
}

function uniqueSlugs( values ) {
	const seen = new Set();
	const result = [];

	values.forEach( ( value ) => {
		const slug = sanitizeSlug( value );
		if ( ! slug || seen.has( slug ) ) {
			return;
		}
		seen.add( slug );
		result.push( slug );
	} );

	return result;
}

function booleanToInt( value ) {
	return value ? '1' : '0';
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		tagSlugs,
		tagMatch,
		enableMultiTag,
		postTypes,
		orderBy,
		order,
		maxItems,
		offset,
		excludeCurrentPost,
		showThumbnail,
		showTitle,
		showSnippet,
		showButton,
		showType,
		showDate,
		snippetWords,
		buttonLabel,
		clickMode,
		openInNewTab,
		buttonStyle,
		minWidthDesktop,
		minWidthTablet,
		minWidthMobile,
		maxColsDesktop,
		maxColsTablet,
		maxColsMobile,
		columnGap,
		rowGap,
		textAlign,
		imageAspectRatio,
		cardPadding,
		cardRadius,
		cardBackground,
	} = attributes;

	const [ status, setStatus ] = useState( null );
	const [ statusLoading, setStatusLoading ] = useState( false );
	const [ statusError, setStatusError ] = useState( '' );

	const tags = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'taxonomy', 'post_tag', {
				per_page: 100,
				hide_empty: false,
			} ) || [],
		[]
	);

	const postTypeObjects = useSelect(
		( select ) => select( 'core' ).getPostTypes( { per_page: -1 } ) || [],
		[]
	);

	const tagSuggestions = useMemo(
		() =>
			tags
				.map( ( term ) => term.slug )
				.filter( Boolean )
				.sort( ( a, b ) => a.localeCompare( b ) ),
		[ tags ]
	);

	const availablePostTypes = useMemo( () => {
		if (
			! Array.isArray( postTypeObjects ) ||
			postTypeObjects.length === 0
		) {
			return [
				{ slug: 'post', label: __( 'Post', 'buttercup' ) },
				{ slug: 'page', label: __( 'Page', 'buttercup' ) },
			];
		}

		return postTypeObjects
			.filter( ( typeObj ) => {
				if ( ! typeObj?.slug ) {
					return false;
				}
				if ( ! typeObj.viewable || ! typeObj.show_in_rest ) {
					return false;
				}
				if ( typeObj.slug === 'attachment' ) {
					return false;
				}
				if ( [ 'post', 'page' ].includes( typeObj.slug ) ) {
					return true;
				}
				return (
					Array.isArray( typeObj.taxonomies ) &&
					typeObj.taxonomies.includes( 'post_tag' )
				);
			} )
			.map( ( typeObj ) => ( {
				slug: typeObj.slug,
				label:
					typeObj?.labels?.singular_name ||
					typeObj.name ||
					typeObj.slug,
			} ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) );
	}, [ postTypeObjects ] );

	const knownTagSet = useMemo(
		() => new Set( tagSuggestions ),
		[ tagSuggestions ]
	);
	const selectedTags = useMemo(
		() => ( Array.isArray( tagSlugs ) ? tagSlugs : [] ),
		[ tagSlugs ]
	);
	const selectedPostTypes = useMemo(
		() => ( Array.isArray( postTypes ) ? postTypes : [] ),
		[ postTypes ]
	);
	const invalidTags = selectedTags.filter(
		( slug ) => ! knownTagSet.has( slug )
	);

	useEffect( () => {
		if ( selectedTags.length === 0 ) {
			setStatus( null );
			setStatusError( '' );
			setStatusLoading( false );
			return;
		}

		let cancelled = false;
		setStatusLoading( true );
		setStatusError( '' );

		const query = new URLSearchParams( {
			tagSlugs: selectedTags.join( ',' ),
			tagMatch,
			postTypes: selectedPostTypes.join( ',' ),
			excludeCurrentPost: booleanToInt( excludeCurrentPost ),
			offset: String( offset || 0 ),
			maxItems: String( maxItems || 12 ),
		} );

		apiFetch( {
			path: `/buttercup/v1/tag-showcase-status?${ query.toString() }`,
		} )
			.then( ( data ) => {
				if ( cancelled ) {
					return;
				}
				setStatus( data );
				setStatusLoading( false );
			} )
			.catch( ( error ) => {
				if ( cancelled ) {
					return;
				}
				setStatus( null );
				setStatusLoading( false );
				setStatusError(
					error?.message ||
						__( 'Unable to load Tag Showcase status.', 'buttercup' )
				);
			} );

		return () => {
			cancelled = true;
		};
	}, [
		selectedTags,
		tagMatch,
		selectedPostTypes,
		excludeCurrentPost,
		offset,
		maxItems,
	] );

	useEffect( () => {
		if ( enableMultiTag ) {
			return;
		}
		if ( selectedTags.length <= 1 ) {
			return;
		}
		setAttributes( { tagSlugs: selectedTags.slice( 0, 1 ) } );
	}, [ enableMultiTag, selectedTags, setAttributes ] );

	const onChangeTags = ( tokens ) => {
		const slugs = uniqueSlugs( tokens );
		setAttributes( {
			tagSlugs: enableMultiTag ? slugs : slugs.slice( 0, 1 ),
		} );
	};

	const onTogglePostType = ( postTypeSlug, checked ) => {
		const current = new Set( selectedPostTypes );
		if ( checked ) {
			current.add( postTypeSlug );
		} else {
			current.delete( postTypeSlug );
		}
		const next = Array.from( current );
		if ( next.length === 0 ) {
			return;
		}
		setAttributes( { postTypes: next } );
	};

	const blockProps = useBlockProps( {
		className: 'buttercup-tag-showcase-editor',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Query', 'buttercup' ) } initialOpen>
					<ToggleControl
						label={ __( 'Enable Multi-Tag Filter', 'buttercup' ) }
						checked={ !! enableMultiTag }
						onChange={ ( value ) =>
							setAttributes( { enableMultiTag: value } )
						}
						__nextHasNoMarginBottom
					/>
					<FormTokenField
						label={ __( 'Tag Slugs', 'buttercup' ) }
						value={ selectedTags }
						onChange={ onChangeTags }
						suggestions={ tagSuggestions }
						help={ __(
							'Select one tag by default. Enable multi-tag filter to add more.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					{ enableMultiTag && (
						<SelectControl
							label={ __( 'Tag Match', 'buttercup' ) }
							value={ tagMatch }
							options={ [
								{
									label: __( 'Match Any Tag', 'buttercup' ),
									value: 'any',
								},
								{
									label: __( 'Match All Tags', 'buttercup' ),
									value: 'all',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { tagMatch: value } )
							}
							__nextHasNoMarginBottom
						/>
					) }
					<div className="buttercup-tag-showcase-editor__checkboxes">
						<p>{ __( 'Post Types', 'buttercup' ) }</p>
						{ availablePostTypes.map( ( typeItem ) => (
							<CheckboxControl
								key={ typeItem.slug }
								label={ typeItem.label }
								checked={ selectedPostTypes.includes(
									typeItem.slug
								) }
								disabled={
									selectedPostTypes.length === 1 &&
									selectedPostTypes.includes( typeItem.slug )
								}
								onChange={ ( checked ) =>
									onTogglePostType( typeItem.slug, checked )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
						<p className="buttercup-tag-showcase-editor__hint">
							{ __(
								'At least one post type must remain selected.',
								'buttercup'
							) }
						</p>
					</div>
					<SelectControl
						label={ __( 'Order By', 'buttercup' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Date', 'buttercup' ), value: 'date' },
							{
								label: __( 'Title', 'buttercup' ),
								value: 'title',
							},
							{
								label: __( 'Last Modified', 'buttercup' ),
								value: 'modified',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orderBy: value } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Order', 'buttercup' ) }
						value={ order }
						options={ [
							{
								label: __( 'Descending', 'buttercup' ),
								value: 'desc',
							},
							{
								label: __( 'Ascending', 'buttercup' ),
								value: 'asc',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { order: value } )
						}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Max Items', 'buttercup' ) }
						value={ maxItems }
						onChange={ ( value ) =>
							setAttributes( {
								maxItems: parseInt( value, 10 ) || 12,
							} )
						}
						min={ 1 }
						max={ 60 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Offset', 'buttercup' ) }
						value={ offset }
						onChange={ ( value ) =>
							setAttributes( {
								offset: parseInt( value, 10 ) || 0,
							} )
						}
						min={ 0 }
						max={ 50 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Exclude Current Post/Page', 'buttercup' ) }
						checked={ !! excludeCurrentPost }
						onChange={ ( value ) =>
							setAttributes( { excludeCurrentPost: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Display', 'buttercup' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Show Thumbnail', 'buttercup' ) }
						checked={ !! showThumbnail }
						onChange={ ( value ) =>
							setAttributes( { showThumbnail: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Title', 'buttercup' ) }
						checked={ !! showTitle }
						onChange={ ( value ) =>
							setAttributes( { showTitle: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Snippet', 'buttercup' ) }
						checked={ !! showSnippet }
						onChange={ ( value ) =>
							setAttributes( { showSnippet: value } )
						}
						__nextHasNoMarginBottom
					/>
					{ showSnippet && (
						<RangeControl
							label={ __( 'Snippet Words', 'buttercup' ) }
							value={ snippetWords }
							onChange={ ( value ) =>
								setAttributes( {
									snippetWords: parseInt( value, 10 ) || 20,
								} )
							}
							min={ 5 }
							max={ 80 }
							step={ 1 }
							__nextHasNoMarginBottom
						/>
					) }
					<ToggleControl
						label={ __( 'Show Button', 'buttercup' ) }
						checked={ !! showButton }
						onChange={ ( value ) =>
							setAttributes( { showButton: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Post Type Label', 'buttercup' ) }
						checked={ !! showType }
						onChange={ ( value ) =>
							setAttributes( { showType: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show Date', 'buttercup' ) }
						checked={ !! showDate }
						onChange={ ( value ) =>
							setAttributes( { showDate: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Links & Button', 'buttercup' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Click Mode', 'buttercup' ) }
						value={ clickMode }
						options={ [
							{
								label: __( 'Card + CTA', 'buttercup' ),
								value: 'card-cta',
							},
							{
								label: __( 'CTA Only', 'buttercup' ),
								value: 'cta-only',
							},
							{
								label: __( 'Card Only', 'buttercup' ),
								value: 'card-only',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { clickMode: value } )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Button Label', 'buttercup' ) }
						value={ buttonLabel }
						onChange={ ( value ) =>
							setAttributes( { buttonLabel: value } )
						}
						help={ __(
							'Used when button display is enabled.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Button Style', 'buttercup' ) }
						value={ buttonStyle }
						options={ [
							{
								label: __( 'Solid', 'buttercup' ),
								value: 'solid',
							},
							{
								label: __( 'Outline', 'buttercup' ),
								value: 'outline',
							},
							{ label: __( 'Text', 'buttercup' ), value: 'text' },
						] }
						onChange={ ( value ) =>
							setAttributes( { buttonStyle: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Open Links In New Tab', 'buttercup' ) }
						checked={ !! openInNewTab }
						onChange={ ( value ) =>
							setAttributes( { openInNewTab: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Layout', 'buttercup' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __(
							'Desktop Min Card Width (px)',
							'buttercup'
						) }
						value={ minWidthDesktop }
						onChange={ ( value ) =>
							setAttributes( {
								minWidthDesktop: parseInt( value, 10 ) || 260,
							} )
						}
						min={ 140 }
						max={ 600 }
						step={ 10 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Desktop Max Columns', 'buttercup' ) }
						value={ maxColsDesktop }
						onChange={ ( value ) =>
							setAttributes( {
								maxColsDesktop: parseInt( value, 10 ) || 4,
							} )
						}
						min={ 1 }
						max={ 8 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __(
							'Tablet Min Card Width (px)',
							'buttercup'
						) }
						value={ minWidthTablet }
						onChange={ ( value ) =>
							setAttributes( {
								minWidthTablet: parseInt( value, 10 ) || 220,
							} )
						}
						min={ 120 }
						max={ 420 }
						step={ 10 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Tablet Max Columns', 'buttercup' ) }
						value={ maxColsTablet }
						onChange={ ( value ) =>
							setAttributes( {
								maxColsTablet: parseInt( value, 10 ) || 3,
							} )
						}
						min={ 1 }
						max={ 6 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __(
							'Mobile Min Card Width (px)',
							'buttercup'
						) }
						value={ minWidthMobile }
						onChange={ ( value ) =>
							setAttributes( {
								minWidthMobile: parseInt( value, 10 ) || 160,
							} )
						}
						min={ 100 }
						max={ 320 }
						step={ 10 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Mobile Max Columns', 'buttercup' ) }
						value={ maxColsMobile }
						onChange={ ( value ) =>
							setAttributes( {
								maxColsMobile: parseInt( value, 10 ) || 2,
							} )
						}
						min={ 1 }
						max={ 4 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Column Gap (px)', 'buttercup' ) }
						value={ columnGap }
						onChange={ ( value ) =>
							setAttributes( {
								columnGap: parseInt( value, 10 ) || 24,
							} )
						}
						min={ 0 }
						max={ 80 }
						step={ 2 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Row Gap (px)', 'buttercup' ) }
						value={ rowGap }
						onChange={ ( value ) =>
							setAttributes( {
								rowGap: parseInt( value, 10 ) || 24,
							} )
						}
						min={ 0 }
						max={ 100 }
						step={ 2 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Card Style', 'buttercup' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Text Align', 'buttercup' ) }
						value={ textAlign }
						options={ [
							{ label: __( 'Left', 'buttercup' ), value: 'left' },
							{
								label: __( 'Center', 'buttercup' ),
								value: 'center',
							},
							{
								label: __( 'Right', 'buttercup' ),
								value: 'right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { textAlign: value } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __( 'Image Aspect Ratio', 'buttercup' ) }
						value={ imageAspectRatio }
						options={ [
							{ label: __( '16:9', 'buttercup' ), value: '16/9' },
							{ label: __( '4:3', 'buttercup' ), value: '4/3' },
							{ label: __( '3:2', 'buttercup' ), value: '3/2' },
							{ label: __( '1:1', 'buttercup' ), value: '1/1' },
							{ label: __( '2:3', 'buttercup' ), value: '2/3' },
							{ label: __( 'Auto', 'buttercup' ), value: 'auto' },
						] }
						onChange={ ( value ) =>
							setAttributes( { imageAspectRatio: value } )
						}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Card Padding (px)', 'buttercup' ) }
						value={ cardPadding }
						onChange={ ( value ) =>
							setAttributes( {
								cardPadding: parseInt( value, 10 ) || 20,
							} )
						}
						min={ 0 }
						max={ 64 }
						step={ 2 }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Card Radius (px)', 'buttercup' ) }
						value={ cardRadius }
						onChange={ ( value ) =>
							setAttributes( {
								cardRadius: parseInt( value, 10 ) || 12,
							} )
						}
						min={ 0 }
						max={ 48 }
						step={ 1 }
						__nextHasNoMarginBottom
					/>
					<div style={ { marginTop: 12, marginBottom: 12 } }>
						<div style={ { fontSize: 12, marginBottom: 6 } }>
							{ __( 'Card Background', 'buttercup' ) }
						</div>
						<ColorPalette
							value={ cardBackground }
							onChange={ ( value ) =>
								setAttributes( { cardBackground: value || '' } )
							}
						/>
					</div>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<h3>{ __( 'Tag Showcase', 'buttercup' ) }</h3>
				<p className="buttercup-tag-showcase-editor__summary">
					{ __(
						'Renders a responsive grid from post/page tags with extensive layout and display controls.',
						'buttercup'
					) }
				</p>

				{ selectedTags.length === 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Add at least one tag slug to show content.',
							'buttercup'
						) }
					</Notice>
				) }

				{ invalidTags.length > 0 && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Some tag slugs do not exist yet. They may produce no results until created.',
							'buttercup'
						) }
					</Notice>
				) }

				{ statusError && (
					<Notice status="error" isDismissible={ false }>
						{ statusError }
					</Notice>
				) }

				{ statusLoading && (
					<div className="buttercup-tag-showcase-editor__loading">
						<Spinner />
					</div>
				) }

				{ ! statusLoading &&
					status &&
					status.count === 0 &&
					selectedTags.length > 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'No matching items found for the selected tag filter.',
								'buttercup'
							) }
						</Notice>
					) }

				{ ! statusLoading && status && status.count > 0 && (
					<p className="buttercup-tag-showcase-editor__status">
						{ `${ __( 'Matching items:', 'buttercup' ) } ${
							status.count
						}` }
					</p>
				) }

				{ selectedTags.length > 0 && (
					<div className="buttercup-tag-showcase-editor__preview">
						<ServerSideRender
							block="buttercup/tag-showcase"
							attributes={ attributes }
							httpMethod="GET"
							skipBlockSupportAttributes
						/>
					</div>
				) }
			</div>
		</>
	);
}
