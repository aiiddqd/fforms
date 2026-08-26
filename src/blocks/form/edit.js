import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Placeholder, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
  [ 'fforms/field-text', { fieldId: 'name', name: 'name', label: __( 'Имя', 'fforms' ), required: true } ],
  [ 'fforms/field-email', { fieldId: 'email', name: 'email', label: __( 'Email', 'fforms' ), required: true } ],
  [ 'fforms/field-textarea', { fieldId: 'message', name: 'message', label: __( 'Сообщение', 'fforms' ), required: true } ],
  [ 'fforms/submit', { label: __( 'Отправить', 'fforms' ) } ],
];

export default function Edit( { attributes, setAttributes } ) {
  const isFormEditor = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType() === 'fform', [] );
  const forms = useSelect( ( select ) => ! isFormEditor && select( 'core' ).getEntityRecords( 'postType', 'fform', { per_page: 100, status: 'publish', orderby: 'title', order: 'asc' } ), [ isFormEditor ] );
  const blockProps = useBlockProps();

  if ( isFormEditor ) {
    const innerBlocksProps = useInnerBlocksProps( blockProps, { template: TEMPLATE } );
    return <div { ...innerBlocksProps } />;
  }

  const ref = attributes.ref || attributes.formId || 0;
  const options = [ { label: __( 'Выберите опубликованную форму', 'fforms' ), value: 0 } ].concat( ( forms || [] ).map( ( form ) => ( { label: form.title.rendered || `#${ form.id }`, value: form.id } ) ) );
  const control = <SelectControl label={ __( 'Форма', 'fforms' ) } value={ ref } options={ options } onChange={ ( value ) => setAttributes( { ref: Number( value ), formId: Number( value ) } ) } />;
  return <>
    <InspectorControls><PanelBody title={ __( 'Настройки формы', 'fforms' ) }>{ control }<ToggleControl label={ __( 'Показывать заголовок', 'fforms' ) } checked={ attributes.showTitle } onChange={ ( showTitle ) => setAttributes( { showTitle } ) } /><TextControl label={ __( 'Текст кнопки (legacy)', 'fforms' ) } value={ attributes.submitLabel } onChange={ ( submitLabel ) => setAttributes( { submitLabel } ) } /></PanelBody></InspectorControls>
    <div { ...blockProps }><Placeholder icon="feedback" label={ __( 'FForms', 'fforms' ) }>{ forms === null ? <Spinner /> : control }</Placeholder></div>
  </>;
}
