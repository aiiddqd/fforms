import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const choiceTypes = [ 'select', 'radio', 'checkbox' ];

export function registerField( metadata ) {
  const type = metadata.name.replace( 'fforms/field-', '' );
  registerBlockType( metadata, {
    edit: ( { attributes, setAttributes } ) => {
      const choices = attributes.options || [];
      const updateOption = ( index, key, value ) => setAttributes( { options: choices.map( ( option, current ) => current === index ? { ...option, [ key ]: value } : option ) } );
      const preview = choiceTypes.includes( type ) && choices.length ? choices.map( ( option ) => <label className="fforms-choice" key={ option.value || option.label }><input disabled type={ type === 'select' ? 'radio' : type } /> { option.label }</label> ) : type === 'textarea' ? <textarea disabled placeholder={ attributes.placeholder } /> : <input disabled type={ type === 'hidden' ? 'hidden' : type === 'text' ? 'text' : type } placeholder={ attributes.placeholder } />;
      return <>
        <InspectorControls><PanelBody title={ __( 'Настройки поля', 'fforms' ) }><TextControl label={ __( 'Имя', 'fforms' ) } value={ attributes.name } onChange={ ( name ) => setAttributes( { name, fieldId: attributes.fieldId || name } ) } help={ __( 'Латиница, цифры, дефисы и подчёркивания.', 'fforms' ) } /><TextControl label={ __( 'Подпись', 'fforms' ) } value={ attributes.label } onChange={ ( label ) => setAttributes( { label } ) } /><TextControl label={ __( 'Placeholder', 'fforms' ) } value={ attributes.placeholder } onChange={ ( placeholder ) => setAttributes( { placeholder } ) } /><TextControl label={ __( 'Максимальная длина', 'fforms' ) } type="number" value={ attributes.maxLength || '' } onChange={ ( maxLength ) => setAttributes( { maxLength: Number( maxLength ) || 0 } ) } /><ToggleControl label={ __( 'Обязательное поле', 'fforms' ) } checked={ attributes.required } onChange={ ( required ) => setAttributes( { required } ) } />{ choiceTypes.includes( type ) && <><h3>{ __( 'Варианты', 'fforms' ) }</h3>{ choices.map( ( option, index ) => <div className="fforms-editor-option" key={ index }><TextControl label={ __( 'Значение', 'fforms' ) } value={ option.value } onChange={ ( value ) => updateOption( index, 'value', value ) } /><TextControl label={ __( 'Подпись', 'fforms' ) } value={ option.label } onChange={ ( label ) => updateOption( index, 'label', label ) } /><Button isDestructive variant="tertiary" onClick={ () => setAttributes( { options: choices.filter( ( _, current ) => current !== index ) } ) }>{ __( 'Удалить', 'fforms' ) }</Button></div> ) }<Button variant="secondary" onClick={ () => setAttributes( { options: [ ...choices, { value: `option-${ choices.length + 1 }`, label: __( 'Вариант', 'fforms' ) } ] } ) }>{ __( 'Добавить вариант', 'fforms' ) }</Button></>}</PanelBody></InspectorControls>
        <div { ...useBlockProps( { className: 'fforms-field' } ) }>{ type !== 'hidden' && <label>{ attributes.label || attributes.name }{ attributes.required && ' *' }</label> }{ preview }</div>
      </>;
    },
    save: () => null,
  } );
}
