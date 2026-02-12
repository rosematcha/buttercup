import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InnerBlocks,
    InspectorControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
    Button,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

const ALLOWED_BLOCKS = ['buttercup/team-member'];

const TEMPLATE = [
    ['buttercup/team-member'],
    ['buttercup/team-member'],
    ['buttercup/team-member'],
];

function AddMemberButton({ clientId }) {
    const { insertBlock } = useDispatch(blockEditorStore);
    return (
        <div className="buttercup-team__add-member">
            <button
                className="buttercup-team__add-member-btn"
                onClick={() => {
                    const block = createBlock('buttercup/team-member');
                    insertBlock(block, undefined, clientId);
                }}
                aria-label={__('Add team member', 'buttercup')}
            >
                <span className="buttercup-team__add-member-icon">+</span>
                <span className="buttercup-team__add-member-label">
                    {__('Add Member', 'buttercup')}
                </span>
            </button>
        </div>
    );
}

export default function Edit({ attributes, setAttributes, clientId }) {
    const {
        imageShape,
        imageSize,
        minCardWidth,
        columnGap,
        rowGap,
        textAlign,
        showBio,
        showSocial,
    } = attributes;

    const innerBlocks = useSelect(
        (select) => select(blockEditorStore).getBlocks(clientId),
        [clientId]
    );

    const { replaceInnerBlocks } = useDispatch(blockEditorStore);

    const memberCount = innerBlocks.length;

    const sortBlocks = (key) => {
        const sorted = [...innerBlocks].sort((a, b) => {
            const valA = (a.attributes[key] || '').replace(/<[^>]*>/g, '').toLowerCase();
            const valB = (b.attributes[key] || '').replace(/<[^>]*>/g, '').toLowerCase();
            return valA.localeCompare(valB);
        });
        replaceInnerBlocks(clientId, sorted, false);
    };

    const blockProps = useBlockProps({
        className: [
            'buttercup-team',
            `buttercup-team--${imageShape}`,
            `buttercup-team--align-${textAlign}`,
        ].join(' '),
        style: {
            '--buttercup-min-card': `${minCardWidth}px`,
            '--buttercup-col-gap': `${columnGap}px`,
            '--buttercup-row-gap': `${rowGap}px`,
            '--buttercup-img-size': `${imageSize}px`,
        },
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Layout', 'buttercup')}>
                    <p style={{ fontSize: 13, color: '#757575', margin: '0 0 12px' }}>
                        {memberCount} {memberCount === 1 ? __('member', 'buttercup') : __('members', 'buttercup')}
                    </p>
                    <RangeControl
                        label={__('Min card width (px)', 'buttercup')}
                        value={minCardWidth}
                        onChange={(v) => setAttributes({ minCardWidth: v })}
                        min={120}
                        max={400}
                        step={10}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Column gap (px)', 'buttercup')}
                        value={columnGap}
                        onChange={(v) => setAttributes({ columnGap: v })}
                        min={0}
                        max={80}
                        step={4}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Row gap (px)', 'buttercup')}
                        value={rowGap}
                        onChange={(v) => setAttributes({ rowGap: v })}
                        min={0}
                        max={80}
                        step={4}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Text Alignment', 'buttercup')}
                        value={textAlign}
                        options={[
                            { label: __('Center', 'buttercup'), value: 'center' },
                            { label: __('Left', 'buttercup'), value: 'left' },
                        ]}
                        onChange={(v) => setAttributes({ textAlign: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Appearance', 'buttercup')}>
                    <SelectControl
                        label={__('Image Shape', 'buttercup')}
                        value={imageShape}
                        options={[
                            { label: __('Circle', 'buttercup'), value: 'circle' },
                            { label: __('Square', 'buttercup'), value: 'square' },
                            { label: __('Squircle', 'buttercup'), value: 'squircle' },
                        ]}
                        onChange={(v) => setAttributes({ imageShape: v })}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Image Size (px)', 'buttercup')}
                        value={imageSize}
                        onChange={(v) => setAttributes({ imageSize: v })}
                        min={40}
                        max={240}
                        step={8}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show Bio', 'buttercup')}
                        checked={showBio}
                        onChange={(v) => setAttributes({ showBio: v })}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Show Social Links', 'buttercup')}
                        checked={showSocial}
                        onChange={(v) => setAttributes({ showSocial: v })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelBody title={__('Sort Members', 'buttercup')} initialOpen={false}>
                    <p style={{ fontSize: 13, color: '#757575', marginTop: 0 }}>
                        {__('Reorder all members at once, or drag individual cards.', 'buttercup')}
                    </p>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        <Button variant="secondary" onClick={() => sortBlocks('name')}>
                            {__('Sort A→Z by Name', 'buttercup')}
                        </Button>
                        <Button variant="secondary" onClick={() => sortBlocks('position')}>
                            {__('Sort A→Z by Position', 'buttercup')}
                        </Button>
                    </div>
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <InnerBlocks
                    allowedBlocks={ALLOWED_BLOCKS}
                    template={TEMPLATE}
                    orientation="horizontal"
                    renderAppender={() => <AddMemberButton clientId={clientId} />}
                />
            </div>
        </>
    );
}
