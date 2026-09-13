import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

// The block on a regular page: a reference to a published form.
export default function EditReference( { attributes, setAttributes } ) {
	const forms = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'fform', {
				per_page: 100,
				status: 'publish',
				orderby: 'title',
				order: 'asc',
			} ),
		[]
	);
	const blockProps = useBlockProps();

	const ref = attributes.ref || attributes.formId || 0;
	const options = [
		{ label: __( 'Select a published form', 'fforms' ), value: 0 },
	].concat(
		( forms || [] ).map( ( form ) => ( {
			label: form.title.rendered || `#${ form.id }`,
			value: form.id,
		} ) )
	);
	const control = (
		<SelectControl
			label={ __( 'Form', 'fforms' ) }
			value={ ref }
			options={ options }
			onChange={ ( value ) =>
				setAttributes( {
					ref: Number( value ),
					formId: Number( value ),
				} )
			}
		/>
	);
	const placeholder = (
		<Placeholder icon="feedback" label={ __( 'FForms', 'fforms' ) }>
			{ forms ? control : <Spinner /> }
		</Placeholder>
	);

	// ServerSideRender deep-compares its whole props object to decide whether to
	// refetch, so the fallback component must keep a stable identity: a fresh
	// arrow function on every render would schedule a request on every render.
	const latest = useRef( placeholder );
	latest.current = placeholder;
	const PickerFallback = useCallback( () => latest.current, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'fforms' ) }>
					{ control }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ ref ? (
					<ServerSideRender
						block="fforms/form"
						// Block supports are already applied to the wrapper
						// above; sending them to the server would apply the
						// padding, background, and border a second time.
						attributes={ { ref } }
						className="fforms-block-preview"
						EmptyResponsePlaceholder={ PickerFallback }
						ErrorResponsePlaceholder={ PickerFallback }
					/>
				) : (
					placeholder
				) }
			</div>
		</>
	);
}
