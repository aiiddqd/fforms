<?php
// This file is generated. Do not modify it manually.
return array(
	'field-checkbox' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-checkbox',
		'title' => 'Checkbox field',
		'category' => 'widgets',
		'icon' => 'yes-alt',
		'description' => 'Collect one or more confirmed options.',
		'keywords' => array(
			'checkbox',
			'consent',
			'choice'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-checkbox'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'consent',
				'name' => 'consent',
				'label' => 'I agree to the privacy policy',
				'required' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-email' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-email',
		'title' => 'Email field',
		'category' => 'widgets',
		'icon' => 'email',
		'description' => 'Collect and validate an email address.',
		'keywords' => array(
			'email',
			'mail',
			'address'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-email'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'email',
				'name' => 'email',
				'label' => 'Email',
				'placeholder' => 'name@example.com',
				'required' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-hidden' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-hidden',
		'title' => 'Hidden field',
		'category' => 'widgets',
		'icon' => 'hidden',
		'description' => 'Send a value without showing a control to visitors.',
		'keywords' => array(
			'hidden',
			'metadata',
			'tracking'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'source',
				'name' => 'source',
				'label' => 'Campaign source'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-number' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-number',
		'title' => 'Number field',
		'category' => 'widgets',
		'icon' => 'editor-ol',
		'description' => 'Collect a numeric value.',
		'keywords' => array(
			'number',
			'numeric',
			'quantity'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-number'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'guests',
				'name' => 'guests',
				'label' => 'Guests',
				'placeholder' => '1'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-radio' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-radio',
		'title' => 'Radio field',
		'category' => 'widgets',
		'icon' => 'editor-ul',
		'description' => 'Let visitors choose one visible option.',
		'keywords' => array(
			'radio',
			'choice',
			'option'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-radio'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'contact',
				'name' => 'contact',
				'label' => 'Preferred contact method',
				'required' => true,
				'options' => array(
					array(
						'value' => 'email',
						'label' => 'Email'
					),
					array(
						'value' => 'phone',
						'label' => 'Phone'
					)
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-select' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-select',
		'title' => 'Select field',
		'category' => 'widgets',
		'icon' => 'arrow-down-alt2',
		'description' => 'Let visitors choose one option from a menu.',
		'keywords' => array(
			'select',
			'dropdown',
			'choice'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-select'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'topic',
				'name' => 'topic',
				'label' => 'Topic',
				'required' => true,
				'options' => array(
					array(
						'value' => 'sales',
						'label' => 'Sales'
					),
					array(
						'value' => 'support',
						'label' => 'Support'
					)
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-tel' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-tel',
		'title' => 'Phone field',
		'category' => 'widgets',
		'icon' => 'phone',
		'description' => 'Collect a telephone number.',
		'keywords' => array(
			'phone',
			'telephone',
			'tel'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-tel'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'phone',
				'name' => 'phone',
				'label' => 'Phone',
				'placeholder' => '+1 234 567 890',
				'required' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-text' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-text',
		'title' => 'Text field',
		'category' => 'widgets',
		'icon' => 'editor-paragraph',
		'description' => 'Collect a short line of text.',
		'keywords' => array(
			'text',
			'input',
			'name'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-text'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'name',
				'name' => 'name',
				'label' => 'Name',
				'placeholder' => 'Your name',
				'required' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-textarea' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-textarea',
		'title' => 'Textarea field',
		'category' => 'widgets',
		'icon' => 'editor-alignleft',
		'description' => 'Collect a longer multi-line response.',
		'keywords' => array(
			'textarea',
			'message',
			'text'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-textarea'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'message',
				'name' => 'message',
				'label' => 'Message',
				'placeholder' => 'How can we help?',
				'required' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'field-url' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/field-url',
		'title' => 'URL field',
		'category' => 'widgets',
		'icon' => 'admin-links',
		'description' => 'Collect and validate a web address.',
		'keywords' => array(
			'url',
			'link',
			'website'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'fieldId' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'name' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'label' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'required' => array(
				'type' => 'boolean',
				'role' => 'content',
				'default' => false
			),
			'placeholder' => array(
				'type' => 'string',
				'role' => 'content'
			),
			'maxLength' => array(
				'type' => 'number',
				'role' => 'content',
				'default' => 0
			),
			'options' => array(
				'type' => 'array',
				'role' => 'content',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'text' => true,
				'background' => false,
				'gradients' => false
			),
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true
			)
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-field-url'
		),
		'example' => array(
			'attributes' => array(
				'fieldId' => 'website',
				'name' => 'website',
				'label' => 'Website',
				'placeholder' => 'https://example.com'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/form',
		'version' => '1.0.0',
		'title' => 'FForms Form',
		'category' => 'widgets',
		'icon' => 'feedback',
		'description' => 'Build a reusable FForms form or insert a published form.',
		'keywords' => array(
			'form',
			'contact',
			'lead'
		),
		'textdomain' => 'fforms',
		'usesContext' => array(
			'postId'
		),
		'attributes' => array(
			'ref' => array(
				'type' => 'integer',
				'default' => 0
			),
			'formId' => array(
				'type' => 'integer',
				'default' => 0
			),
			'showTitle' => array(
				'type' => 'boolean',
				'default' => false
			),
			'submitLabel' => array(
				'type' => 'string',
				'role' => 'content',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'color' => array(
				'background' => true,
				'text' => true,
				'gradients' => true,
				'__experimentalDefaultControls' => array(
					'background' => true,
					'text' => true
				)
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true,
				'__experimentalDefaultControls' => array(
					'margin' => true,
					'padding' => true
				)
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true
				)
			),
			'__experimentalBorder' => array(
				'color' => true,
				'radius' => true,
				'style' => true,
				'width' => true,
				'__experimentalDefaultControls' => array(
					'color' => true,
					'radius' => true,
					'style' => true,
					'width' => true
				)
			),
			'shadow' => true,
			'interactivity' => true
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-form'
		),
		'allowedBlocks' => array(
			'fforms/field-text',
			'fforms/field-textarea',
			'fforms/field-email',
			'fforms/field-tel',
			'fforms/field-url',
			'fforms/field-number',
			'fforms/field-select',
			'fforms/field-radio',
			'fforms/field-checkbox',
			'fforms/field-hidden',
			'fforms/submit',
			'core/paragraph',
			'core/heading',
			'core/group',
			'core/columns',
			'core/column',
			'core/spacer',
			'core/separator'
		),
		'example' => array(
			'viewportWidth' => 640,
			'innerBlocks' => array(
				array(
					'name' => 'fforms/field-text',
					'attributes' => array(
						'fieldId' => 'name',
						'name' => 'name',
						'label' => 'Name',
						'required' => true
					)
				),
				array(
					'name' => 'fforms/field-email',
					'attributes' => array(
						'fieldId' => 'email',
						'name' => 'email',
						'label' => 'Email',
						'required' => true
					)
				),
				array(
					'name' => 'fforms/submit',
					'attributes' => array(
						'label' => 'Send'
					)
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewStyle' => 'file:./view.css',
		'viewScriptModule' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'submit' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'fforms/submit',
		'title' => 'Submit button',
		'category' => 'widgets',
		'icon' => 'controls-play',
		'description' => 'Add the button that submits this FForms form.',
		'keywords' => array(
			'submit',
			'button',
			'send'
		),
		'textdomain' => 'fforms',
		'ancestor' => array(
			'fforms/form'
		),
		'attributes' => array(
			'label' => array(
				'type' => 'string',
				'role' => 'content',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false,
			'visibility' => false,
			'color' => array(
				'background' => true,
				'text' => true,
				'gradients' => true,
				'__experimentalDefaultControls' => array(
					'background' => true,
					'text' => true
				)
			),
			'spacing' => array(
				'padding' => array(
					'horizontal',
					'vertical'
				),
				'__experimentalDefaultControls' => array(
					'padding' => true
				)
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true
				)
			),
			'__experimentalBorder' => array(
				'color' => true,
				'radius' => true,
				'style' => true,
				'width' => true,
				'__experimentalDefaultControls' => array(
					'color' => true,
					'radius' => true,
					'style' => true,
					'width' => true
				)
			),
			'shadow' => true
		),
		'selectors' => array(
			'root' => '.wp-block-fforms-submit'
		),
		'example' => array(
			'attributes' => array(
				'label' => 'Send'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	)
);
