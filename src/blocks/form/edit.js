import { useSelect } from '@wordpress/data';

import EditBuilder from './edit-builder';
import EditReference from './edit-reference';

export default function Edit( props ) {
	const isFormEditor = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType() === 'fform',
		[]
	);

	return isFormEditor ? (
		<EditBuilder { ...props } />
	) : (
		<EditReference { ...props } />
	);
}
