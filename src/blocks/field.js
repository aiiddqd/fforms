import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const choiceTypes = [ 'select', 'radio', 'checkbox' ];

const preventPreviewInteraction = ( event ) => event.preventDefault();

function FieldLabel( { attributes } ) {
	return (
		<span className="fforms-label">
			{ attributes.label || attributes.name || __( 'Поле', 'fforms' ) }
			{ attributes.required && ' *' }
		</span>
	);
}

function ChoicePreview( { type, choices } ) {
	if ( 'select' === type ) {
		return (
			<select
				className="fforms-control"
				onChange={ preventPreviewInteraction }
				onMouseDown={ preventPreviewInteraction }
				tabIndex="-1"
			>
				<option value="">{ __( 'Выберите вариант', 'fforms' ) }</option>
				{ choices.map( ( option, index ) => (
					<option
						key={ option.value || index }
						value={ option.value }
					>
						{ option.label || __( 'Вариант', 'fforms' ) }
					</option>
				) ) }
			</select>
		);
	}

	if ( choices.length ) {
		return choices.map( ( option, index ) => {
			const id = `fforms-preview-${ type }-${ option.value || index }`;

			return (
				<label
					className="fforms-choice"
					htmlFor={ id }
					key={ option.value || index }
				>
					<input
						className="fforms-choice-control"
						id={ id }
						onChange={ preventPreviewInteraction }
						onClick={ preventPreviewInteraction }
						type={ type }
					/>
					{ option.label || __( 'Вариант', 'fforms' ) }
				</label>
			);
		} );
	}

	return (
		<label className="fforms-choice" htmlFor="fforms-preview-checkbox">
			<input
				className="fforms-choice-control"
				id="fforms-preview-checkbox"
				onChange={ preventPreviewInteraction }
				onClick={ preventPreviewInteraction }
				type="checkbox"
			/>
			<FieldLabel
				attributes={ { label: __( 'Подтверждение', 'fforms' ) } }
			/>
		</label>
	);
}

function FieldPreview( { attributes, type } ) {
	if ( 'hidden' === type ) {
		return (
			<div className="fforms-hidden-field-placeholder">
				{ __( 'Скрытое поле', 'fforms' ) }:{ ' ' }
				{ attributes.name || __( 'без имени', 'fforms' ) }
			</div>
		);
	}

	if ( choiceTypes.includes( type ) ) {
		if (
			'radio' === type ||
			( 'checkbox' === type && attributes.options?.length )
		) {
			return (
				<fieldset className="fforms-options">
					<legend>
						<FieldLabel attributes={ attributes } />
					</legend>
					<ChoicePreview
						type={ type }
						choices={ attributes.options || [] }
					/>
				</fieldset>
			);
		}

		return (
			<div className="fforms-field-preview">
				<FieldLabel attributes={ attributes } />
				<ChoicePreview
					type={ type }
					choices={ attributes.options || [] }
				/>
			</div>
		);
	}

	if ( 'textarea' === type ) {
		return (
			<div className="fforms-field-preview">
				<FieldLabel attributes={ attributes } />
				<textarea
					className="fforms-control"
					placeholder={ attributes.placeholder }
					readOnly
				/>
			</div>
		);
	}

	return (
		<div className="fforms-field-preview">
			<FieldLabel attributes={ attributes } />
			<input
				className="fforms-control"
				placeholder={ attributes.placeholder }
				readOnly
				type={ type }
			/>
		</div>
	);
}

function FieldControls( { attributes, setAttributes, type } ) {
	const choices = attributes.options || [];
	const updateOption = ( index, key, value ) => {
		setAttributes( {
			options: choices.map( ( option, current ) =>
				current === index ? { ...option, [ key ]: value } : option
			),
		} );
	};

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Настройки поля', 'fforms' ) }>
				<TextControl
					help={ __(
						'Латиница, цифры, дефисы и подчёркивания.',
						'fforms'
					) }
					label={ __( 'Имя', 'fforms' ) }
					onChange={ ( name ) =>
						setAttributes( {
							name,
							fieldId: attributes.fieldId || name,
						} )
					}
					value={ attributes.name }
				/>
				{ 'hidden' !== type && (
					<>
						<TextControl
							label={ __( 'Подпись', 'fforms' ) }
							onChange={ ( label ) => setAttributes( { label } ) }
							value={ attributes.label }
						/>
						{ ! choiceTypes.includes( type ) && (
							<TextControl
								label={ __( 'Placeholder', 'fforms' ) }
								onChange={ ( placeholder ) =>
									setAttributes( { placeholder } )
								}
								value={ attributes.placeholder }
							/>
						) }
						{ ! choiceTypes.includes( type ) &&
							'textarea' !== type && (
								<TextControl
									label={ __(
										'Максимальная длина',
										'fforms'
									) }
									onChange={ ( maxLength ) =>
										setAttributes( {
											maxLength: Number( maxLength ) || 0,
										} )
									}
									type="number"
									value={ attributes.maxLength || '' }
								/>
							) }
						<ToggleControl
							checked={ attributes.required }
							label={ __( 'Обязательное поле', 'fforms' ) }
							onChange={ ( required ) =>
								setAttributes( { required } )
							}
						/>
					</>
				) }
				{ choiceTypes.includes( type ) && (
					<>
						<h3>{ __( 'Варианты', 'fforms' ) }</h3>
						{ choices.map( ( option, index ) => (
							<div className="fforms-editor-option" key={ index }>
								<TextControl
									label={ __( 'Значение', 'fforms' ) }
									onChange={ ( value ) =>
										updateOption( index, 'value', value )
									}
									value={ option.value }
								/>
								<TextControl
									label={ __( 'Подпись', 'fforms' ) }
									onChange={ ( label ) =>
										updateOption( index, 'label', label )
									}
									value={ option.label }
								/>
								<Button
									isDestructive
									onClick={ () =>
										setAttributes( {
											options: choices.filter(
												( _, current ) =>
													current !== index
											),
										} )
									}
									variant="tertiary"
								>
									{ __( 'Удалить', 'fforms' ) }
								</Button>
							</div>
						) ) }
						<Button
							onClick={ () =>
								setAttributes( {
									options: [
										...choices,
										{
											value: `option-${
												choices.length + 1
											}`,
											label: __( 'Вариант', 'fforms' ),
										},
									],
								} )
							}
							variant="secondary"
						>
							{ __( 'Добавить вариант', 'fforms' ) }
						</Button>
					</>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

export function registerField( metadata ) {
	const type = metadata.name.replace( 'fforms/field-', '' );

	function EditField( { attributes, setAttributes } ) {
		const blockProps = useBlockProps( {
			className: `fforms-field fforms-field--${ type }`,
		} );

		return (
			<>
				<FieldControls
					attributes={ attributes }
					setAttributes={ setAttributes }
					type={ type }
				/>
				<div { ...blockProps }>
					<FieldPreview attributes={ attributes } type={ type } />
				</div>
			</>
		);
	}

	registerBlockType( metadata, {
		edit: EditField,
		save: () => null,
	} );
}
