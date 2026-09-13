import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'fforms/field-text',
		{
			fieldId: 'name',
			name: 'name',
			label: __( 'Name', 'fforms' ),
			required: true,
		},
	],
	[
		'fforms/field-email',
		{
			fieldId: 'email',
			name: 'email',
			label: __( 'Email', 'fforms' ),
			required: true,
		},
	],
	[
		'fforms/field-textarea',
		{
			fieldId: 'message',
			name: 'message',
			label: __( 'Message', 'fforms' ),
			required: true,
		},
	],
	[ 'fforms/submit', { label: __( 'Send', 'fforms' ) } ],
];

// The block inside the `fform` CPT: the form itself, edited as inner blocks.
export default function EditBuilder() {
	const innerBlocksProps = useInnerBlocksProps( useBlockProps(), {
		template: TEMPLATE,
	} );

	return <div { ...innerBlocksProps } />;
}
