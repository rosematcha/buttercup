import { registerPlugin } from '@wordpress/plugins';
import EventDetailsPanel from './event-details-panel';

registerPlugin( 'buttercup-event-meta', {
	render: EventDetailsPanel,
} );
