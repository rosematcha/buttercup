import { __, sprintf } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import {
	Button,
	CheckboxControl,
	ComboboxControl,
	Notice,
	PanelRow,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useState, useEffect } from '@wordpress/element';

/**
 * Parse a MySQL datetime string into separate date and time parts.
 * @param {string} mysqlStr e.g. "2026-04-03 19:00:00"
 * @return {{ date: string, time: string }} date = "2026-04-03", time = "19:00"
 */
function parseDatetime( mysqlStr ) {
	if ( ! mysqlStr ) {
		return { date: '', time: '' };
	}
	const parts = mysqlStr.split( ' ' );
	return {
		date: parts[ 0 ] || '',
		time: parts[ 1 ] ? parts[ 1 ].slice( 0, 5 ) : '',
	};
}

/**
 * Combine a date and time string into MySQL DATETIME format.
 * @param {string} date "YYYY-MM-DD"
 * @param {string} time "HH:MM" (optional)
 * @return {string} MySQL DATETIME string, e.g. "2026-04-03 19:00:00".
 */
function buildMySQLDatetime( date, time ) {
	if ( ! date ) {
		return '';
	}
	return date + ' ' + ( time || '00:00' ) + ':00';
}

const inputStyle = {
	width: '100%',
	padding: '6px 8px',
	fontSize: 13,
	border: '1px solid #757575',
	borderRadius: 2,
	boxSizing: 'border-box',
	marginBottom: 8,
};

const PAGE_MODE_HELP = {
	template: __(
		'Uses the standard event layout with date, location, and details. Best for most events.',
		'buttercup'
	),
	editor: __(
		'Design the event page freely with the block editor. Still lives under /events/.',
		'buttercup'
	),
	standalone: __(
		'Give this event its own dedicated page — either link an existing page or create a new root-level URL like /blockparty.',
		'buttercup'
	),
};

/**
 * Card shown when a page is linked — shows the page name with
 * "Edit Page", "Change", and "Unlink" actions.
 * @param {Object}   root0                 Props.
 * @param {string}   root0.pageTitle       Title of the linked page.
 * @param {number}   root0.pageId          Post ID of the linked page.
 * @param {Function} root0.onUnlink        Called when the user clicks Unlink.
 * @param {Function} root0.onChangeRequest Called when the user clicks Change.
 * @param {boolean}  root0.isChanging      Whether the change picker is open.
 */
function LinkedPageCard( {
	pageTitle,
	pageId,
	onUnlink,
	onChangeRequest,
	isChanging,
} ) {
	const editUrl = `/wp-admin/post.php?post=${ pageId }&action=edit`;

	return (
		<div
			style={ {
				marginTop: 12,
				padding: 12,
				background: '#f0f6fc',
				border: '1px solid #c3d4e6',
				borderRadius: 4,
			} }
		>
			<div
				style={ {
					fontSize: 11,
					fontWeight: 600,
					textTransform: 'uppercase',
					letterSpacing: '0.04em',
					color: '#757575',
					marginBottom: 4,
				} }
			>
				{ __( 'Linked Page', 'buttercup' ) }
			</div>
			<div style={ { fontWeight: 600, fontSize: 13, marginBottom: 8 } }>
				{ pageTitle }
			</div>
			<div style={ { display: 'flex', gap: 8 } }>
				<Button variant="primary" size="small" href={ editUrl }>
					{ __( 'Edit Page', 'buttercup' ) }
				</Button>
				<Button
					variant="secondary"
					size="small"
					onClick={ onChangeRequest }
				>
					{ isChanging
						? __( 'Cancel', 'buttercup' )
						: __( 'Change', 'buttercup' ) }
				</Button>
				<Button
					variant="tertiary"
					size="small"
					isDestructive
					onClick={ onUnlink }
				>
					{ __( 'Unlink', 'buttercup' ) }
				</Button>
			</div>
		</div>
	);
}

export default function EventDetailsPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const [ pageSearch, setPageSearch ] = useState( '' );
	const [ isChangingPage, setIsChangingPage ] = useState( false );
	const { createInfoNotice, removeNotice } = useDispatch( 'core/notices' );

	// Fetch published pages for the page selector.
	const { pages, linkedPageTitle } = useSelect(
		( select ) => {
			const { getEntityRecords, getEntityRecord } = select( 'core' );
			const query = {
				per_page: 20,
				status: 'publish',
				orderby: 'title',
				order: 'asc',
			};
			if ( pageSearch ) {
				query.search = pageSearch;
			}
			const results = getEntityRecords( 'postType', 'page', query ) || [];

			const linkedId = meta?._buttercup_event_linked_page
				? parseInt( meta._buttercup_event_linked_page, 10 )
				: 0;
			let title = '';
			if ( linkedId ) {
				const linked = getEntityRecord( 'postType', 'page', linkedId );
				if ( linked ) {
					title = linked.title?.rendered || '';
				}
			}

			return {
				pages: results,
				linkedPageTitle: title,
			};
		},
		[ pageSearch, meta?._buttercup_event_linked_page ]
	);

	const linkedPage = meta?._buttercup_event_linked_page
		? parseInt( meta._buttercup_event_linked_page, 10 )
		: 0;

	// Show/remove an editor-wide notice when a page is linked.
	const NOTICE_ID = 'buttercup-linked-page-notice';
	useEffect( () => {
		if ( linkedPage && linkedPageTitle ) {
			createInfoNotice(
				sprintf(
					/* translators: %s: linked page title */
					__(
						'This event links to "%s". The block editor content below is not displayed to visitors — edit the linked page instead.',
						'buttercup'
					),
					linkedPageTitle
				),
				{
					id: NOTICE_ID,
					isDismissible: false,
					actions: [
						{
							label: __( 'Edit Linked Page', 'buttercup' ),
							url: `/wp-admin/post.php?post=${ linkedPage }&action=edit`,
						},
					],
				}
			);
		} else {
			removeNotice( NOTICE_ID );
		}

		return () => removeNotice( NOTICE_ID );
	}, [ linkedPage, linkedPageTitle ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( postType !== 'buttercup_event' ) {
		return null;
	}

	const startDate = meta?._buttercup_event_start || '';
	const endDate = meta?._buttercup_event_end || '';
	const startAllDay = meta?._buttercup_event_start_allday === '1';
	const endAllDay = meta?._buttercup_event_end_allday === '1';
	const location = meta?._buttercup_event_location || '';
	const eventUrl = meta?._buttercup_event_url || '';
	const urlLabel = meta?._buttercup_event_url_label || 'more_info';
	const pageMode = meta?._buttercup_event_page_mode || 'template';
	const customSlug = meta?._buttercup_event_custom_slug || '';

	const startParsed = parseDatetime( startDate );
	const endParsed = parseDatetime( endDate );
	const hasEnd = !! endDate;

	const updateMeta = ( key, value ) => {
		setMeta( { ...meta, [ key ]: value } );
	};

	const updateStart = ( date, time ) => {
		updateMeta(
			'_buttercup_event_start',
			buildMySQLDatetime( date, time )
		);
	};

	const updateEnd = ( date, time ) => {
		updateMeta( '_buttercup_event_end', buildMySQLDatetime( date, time ) );
	};

	const hasDateError = startDate && endDate && endDate < startDate;

	const pageOptions = pages.map( ( page ) => ( {
		value: String( page.id ),
		label: page.title?.rendered || sprintf( '(#%d)', page.id ),
	} ) );

	const showPagePicker =
		pageMode === 'standalone' && ( ! linkedPage || isChangingPage );

	return (
		<PluginDocumentSettingPanel
			name="buttercup-event-details"
			title={ __( 'Event Details', 'buttercup' ) }
			className="buttercup-event-details-panel"
		>
			{ /* Inline notice when a page is linked */ }
			{ linkedPage && linkedPageTitle && (
				<Notice
					status="info"
					isDismissible={ false }
					style={ { margin: '0 0 16px' } }
				>
					{ __(
						'Visitors see the linked page, not this editor content.',
						'buttercup'
					) }
				</Notice>
			) }

			{ /* ── Start date/time ── */ }
			<PanelRow>
				<fieldset
					style={ {
						width: '100%',
						border: 'none',
						padding: 0,
						margin: 0,
					} }
				>
					<legend style={ { fontWeight: 600, marginBottom: 8 } }>
						{ __( 'Start Date', 'buttercup' ) }
					</legend>

					<input
						type="date"
						value={ startParsed.date }
						onChange={ ( e ) =>
							updateStart(
								e.target.value,
								startAllDay ? '' : startParsed.time
							)
						}
						style={ inputStyle }
					/>

					{ startParsed.date && (
						<>
							<CheckboxControl
								label={ __(
									'All day (no specific time)',
									'buttercup'
								) }
								checked={ startAllDay }
								onChange={ ( val ) => {
									updateMeta(
										'_buttercup_event_start_allday',
										val ? '1' : ''
									);
									if ( val ) {
										updateStart( startParsed.date, '' );
									}
								} }
								__nextHasNoMarginBottom
							/>

							{ ! startAllDay && (
								<input
									type="time"
									value={ startParsed.time }
									onChange={ ( e ) =>
										updateStart(
											startParsed.date,
											e.target.value
										)
									}
									style={ { ...inputStyle, marginTop: 8 } }
								/>
							) }

							<Button
								variant="tertiary"
								isDestructive
								size="small"
								style={ { marginTop: 4 } }
								onClick={ () => {
									updateMeta( '_buttercup_event_start', '' );
									updateMeta(
										'_buttercup_event_start_allday',
										''
									);
								} }
							>
								{ __( 'Clear start', 'buttercup' ) }
							</Button>
						</>
					) }
				</fieldset>
			</PanelRow>

			{ /* ── End date/time ── */ }
			<PanelRow>
				<fieldset
					style={ {
						width: '100%',
						border: 'none',
						padding: 0,
						margin: 0,
					} }
				>
					<CheckboxControl
						label={ __( 'Add end date/time', 'buttercup' ) }
						checked={ hasEnd }
						onChange={ ( val ) => {
							if ( ! val ) {
								updateMeta( '_buttercup_event_end', '' );
								updateMeta( '_buttercup_event_end_allday', '' );
							} else {
								// Default end to the current start date.
								const defaultDate =
									startParsed.date ||
									new Date().toISOString().slice( 0, 10 );
								updateEnd( defaultDate, endAllDay ? '' : '' );
							}
						} }
						__nextHasNoMarginBottom
					/>

					{ hasEnd && (
						<div style={ { marginTop: 8 } }>
							<legend
								style={ {
									fontWeight: 600,
									marginBottom: 8,
									display: 'block',
								} }
							>
								{ __( 'End Date', 'buttercup' ) }
							</legend>

							<input
								type="date"
								value={ endParsed.date }
								onChange={ ( e ) =>
									updateEnd(
										e.target.value,
										endAllDay ? '' : endParsed.time
									)
								}
								style={ inputStyle }
							/>

							<CheckboxControl
								label={ __(
									'All day (no specific time)',
									'buttercup'
								) }
								checked={ endAllDay }
								onChange={ ( val ) => {
									updateMeta(
										'_buttercup_event_end_allday',
										val ? '1' : ''
									);
									if ( val ) {
										updateEnd( endParsed.date, '' );
									}
								} }
								__nextHasNoMarginBottom
							/>

							{ ! endAllDay && (
								<input
									type="time"
									value={ endParsed.time }
									onChange={ ( e ) =>
										updateEnd(
											endParsed.date,
											e.target.value
										)
									}
									style={ { ...inputStyle, marginTop: 8 } }
								/>
							) }
						</div>
					) }
				</fieldset>
			</PanelRow>

			{ hasDateError && (
				<p
					style={ {
						color: '#cc1818',
						fontSize: 12,
						margin: '0 0 12px',
					} }
					role="alert"
				>
					{ __( 'End date is before start date.', 'buttercup' ) }
				</p>
			) }

			<TextControl
				label={ __( 'Location', 'buttercup' ) }
				value={ location }
				onChange={ ( value ) =>
					updateMeta( '_buttercup_event_location', value )
				}
				help={ __( 'Venue name or address.', 'buttercup' ) }
				__nextHasNoMarginBottom
			/>

			<TextControl
				label={ __( 'Event URL', 'buttercup' ) }
				value={ eventUrl }
				onChange={ ( value ) =>
					updateMeta( '_buttercup_event_url', value )
				}
				type="url"
				help={ __(
					'Link to tickets, registration, or external event page.',
					'buttercup'
				) }
				__nextHasNoMarginBottom
			/>

			{ eventUrl && (
				<>
					<SelectControl
						label={ __( 'Button Label', 'buttercup' ) }
						value={ urlLabel === 'custom' ? 'custom' : urlLabel }
						options={ [
							{
								label: __( 'More Info', 'buttercup' ),
								value: 'more_info',
							},
							{
								label: __( 'Get Tickets', 'buttercup' ),
								value: 'get_tickets',
							},
							{
								label: __( 'Register', 'buttercup' ),
								value: 'register',
							},
							{
								label: __( 'Custom…', 'buttercup' ),
								value: 'custom',
							},
						] }
						onChange={ ( value ) =>
							updateMeta( '_buttercup_event_url_label', value )
						}
						help={ __(
							'Text shown on the event page button.',
							'buttercup'
						) }
						__nextHasNoMarginBottom
					/>

					{ urlLabel === 'custom' && (
						<TextControl
							label={ __( 'Custom Button Text', 'buttercup' ) }
							value={
								meta?._buttercup_event_url_label_custom || ''
							}
							onChange={ ( value ) =>
								updateMeta(
									'_buttercup_event_url_label_custom',
									value
								)
							}
							placeholder={ __( 'e.g. RSVP Now', 'buttercup' ) }
							__nextHasNoMarginBottom
						/>
					) }
				</>
			) }

			<SelectControl
				label={ __( 'Page Type', 'buttercup' ) }
				value={ pageMode }
				options={ [
					{
						label: __( 'Standard Event Page', 'buttercup' ),
						value: 'template',
					},
					{
						label: __(
							'Custom Layout (Block Editor)',
							'buttercup'
						),
						value: 'editor',
					},
					{
						label: __( 'Dedicated Page', 'buttercup' ),
						value: 'standalone',
					},
				] }
				onChange={ ( value ) => {
					updateMeta( '_buttercup_event_page_mode', value );
					if ( value !== 'standalone' ) {
						updateMeta( '_buttercup_event_linked_page', '' );
						updateMeta( '_buttercup_event_custom_slug', '' );
					}
					setIsChangingPage( false );
				} }
				help={ PAGE_MODE_HELP[ pageMode ] }
				__nextHasNoMarginBottom
			/>

			{ /* Linked page card — shown when a page is already linked */ }
			{ pageMode === 'standalone' && linkedPage && linkedPageTitle && (
				<LinkedPageCard
					pageTitle={ linkedPageTitle }
					pageId={ linkedPage }
					isChanging={ isChangingPage }
					onChangeRequest={ () =>
						setIsChangingPage( ! isChangingPage )
					}
					onUnlink={ () => {
						updateMeta( '_buttercup_event_linked_page', '' );
						setIsChangingPage( false );
					} }
				/>
			) }

			{ /* Page picker — shown when no page is linked, or user clicked Change */ }
			{ showPagePicker && (
				<>
					<div style={ { marginTop: 12 } }>
						<ComboboxControl
							label={ __(
								'Link to an existing page',
								'buttercup'
							) }
							value={
								! isChangingPage && linkedPage
									? String( linkedPage )
									: ''
							}
							options={ pageOptions }
							onChange={ ( value ) => {
								updateMeta(
									'_buttercup_event_linked_page',
									value || ''
								);
								if ( value ) {
									updateMeta(
										'_buttercup_event_custom_slug',
										''
									);
									setIsChangingPage( false );
								}
							} }
							onFilterValueChange={ setPageSearch }
							help={ __(
								'Search for a page by title.',
								'buttercup'
							) }
							__nextHasNoMarginBottom
						/>
					</div>

					{ ! linkedPage && (
						<TextControl
							label={ __(
								'Or create a new root URL',
								'buttercup'
							) }
							value={ customSlug }
							onChange={ ( value ) =>
								updateMeta(
									'_buttercup_event_custom_slug',
									value
								)
							}
							placeholder="blockparty"
							help={ __(
								'Creates a page at yoursite.com/blockparty with full block editor control.',
								'buttercup'
							) }
							__nextHasNoMarginBottom
						/>
					) }
				</>
			) }
		</PluginDocumentSettingPanel>
	);
}
