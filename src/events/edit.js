import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import { buildEventsStatusQuery } from '../shared/rest-query-utils';

function getEventCountText( count ) {
	if ( count === 1 ) {
		return `1 ${ __( 'event', 'buttercup' ) }`;
	}
	return `${ count } ${ __( 'events', 'buttercup' ) }`;
}

export default function Edit( { attributes, setAttributes } ) {
	const { displayMode, eventsToShow, showPastEventsLink } = attributes;

	const [ status, setStatus ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ errorMessage, setErrorMessage ] = useState( '' );
	const requestRef = useRef( 0 );

	useEffect( () => {
		const requestId = ++requestRef.current;
		setIsLoading( true );
		setErrorMessage( '' );

		const controller =
			typeof AbortController !== 'undefined'
				? new AbortController()
				: null;

		const timer = setTimeout( () => {
			const query = buildEventsStatusQuery( {
				displayMode,
				eventsToShow,
			} );

			apiFetch( {
				path: `/buttercup/v1/events-status?${ query.toString() }`,
				signal: controller?.signal,
			} )
				.then( ( data ) => {
					if ( requestId !== requestRef.current ) {
						return;
					}
					setStatus( data );
					setIsLoading( false );
				} )
				.catch( ( error ) => {
					if (
						requestId !== requestRef.current ||
						error?.name === 'AbortError'
					) {
						return;
					}
					setStatus( null );
					setIsLoading( false );
					setErrorMessage(
						error?.message ||
							__( 'Unable to load events status.', 'buttercup' )
					);
				} );
		}, 280 );

		return () => {
			clearTimeout( timer );
			controller?.abort();
		};
	}, [ displayMode, eventsToShow ] );

	const blockProps = useBlockProps( {
		className: 'buttercup-events-editor',
	} );

	const modeLabel =
		displayMode === 'past'
			? __( 'Past Events', 'buttercup' )
			: __( 'Upcoming Events', 'buttercup' );

	let summaryText = '';
	if ( isLoading ) {
		summaryText = __( 'Loading\u2026', 'buttercup' );
	} else if ( status?.count !== undefined ) {
		summaryText = getEventCountText( status.count );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Events Settings', 'buttercup' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Display Mode', 'buttercup' ) }
						value={ displayMode }
						options={ [
							{
								label: __( 'Upcoming Events', 'buttercup' ),
								value: 'upcoming',
							},
							{
								label: __( 'Past Events', 'buttercup' ),
								value: 'past',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { displayMode: value } )
						}
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Events to Show', 'buttercup' ) }
						value={ eventsToShow }
						onChange={ ( value ) =>
							setAttributes( { eventsToShow: value } )
						}
						min={ 1 }
						max={ 50 }
						__nextHasNoMarginBottom
					/>
					{ displayMode === 'upcoming' && (
						<ToggleControl
							label={ __( 'Show past events link', 'buttercup' ) }
							checked={ showPastEventsLink }
							onChange={ ( value ) =>
								setAttributes( {
									showPastEventsLink: value,
								} )
							}
							__nextHasNoMarginBottom
						/>
					) }
				</PanelBody>
				{ ! isLoading && status?.events?.length > 0 && (
					<PanelBody
						title={ `${ __( 'Events', 'buttercup' ) } (${
							status.events.length
						})` }
						initialOpen={ false }
					>
						<ol style={ { margin: 0, paddingLeft: 20 } }>
							{ status.events.map( ( event ) => (
								<li
									key={ event.id }
									style={ {
										fontSize: 13,
										marginBottom: 4,
									} }
								>
									{ event.title ||
										__( '(Untitled)', 'buttercup' ) }
									{ event.start && (
										<span
											style={ {
												display: 'block',
												fontSize: 11,
												color: '#757575',
											} }
										>
											{ event.start }
										</span>
									) }
								</li>
							) ) }
						</ol>
					</PanelBody>
				) }
				<PanelBody
					title={ __( 'Quick Links', 'buttercup' ) }
					initialOpen={ false }
				>
					<p>
						<a
							href="edit.php?post_type=buttercup_event"
							style={ { fontSize: 13 } }
						>
							{ __( 'Manage Events', 'buttercup' ) }
						</a>
					</p>
					<p>
						<a
							href="admin.php?page=buttercup-events-sync"
							style={ { fontSize: 13 } }
						>
							{ __( 'Sync Settings', 'buttercup' ) }
						</a>
					</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3>{ modeLabel }</h3>
				<p className="buttercup-events-editor__summary">
					{ summaryText }
				</p>

				{ errorMessage && (
					<Notice status="error" isDismissible={ false }>
						{ errorMessage }
					</Notice>
				) }
				{ isLoading && (
					<div className="buttercup-events-editor__loading">
						<Spinner />
					</div>
				) }

				<div className="buttercup-events-editor__preview">
					<ServerSideRender
						block="buttercup/events"
						attributes={ attributes }
						httpMethod="GET"
						skipBlockSupportAttributes
					/>
				</div>
			</div>
		</>
	);
}
