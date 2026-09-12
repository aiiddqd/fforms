import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

const ALLOWED_BLOCKS = metadata.allowedBlocks;

function Edit() {
	const blockProps = useBlockProps( {
		className: 'fforms-headless-schema',
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'fforms-headless-schema__fields' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			templateLock: false,
		}
	);

	return (
		<div { ...blockProps }>
			<p className="fforms-headless-schema__description">
				{ __(
					'The fields below define the form schema for the REST API. This block is not rendered on the site.',
					'fforms'
				) }
			</p>
			<div { ...innerBlocksProps } />
		</div>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
