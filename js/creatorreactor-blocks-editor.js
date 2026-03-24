/**
 * CreatorReactor Gutenberg blocks (editor script).
 */
(function (blocks, element, blockEditor, i18n, serverSideRender, components) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InnerBlocks = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	function registerInner(name, title, description, icon) {
		blocks.registerBlockType(name, {
			apiVersion: 3,
			title: title,
			description: description,
			category: 'creatorreactor',
			icon: icon,
			edit: function () {
				var blockProps = useBlockProps({
					className: 'creatorreactor-block-editor'
				});
				return el(
					Fragment,
					null,
					el(
						'div',
						blockProps,
						el(
							'div',
							{ className: 'creatorreactor-block-hint' },
							el('strong', null, title),
							' — ',
							description
						),
						el(InnerBlocks, null)
					)
				);
			},
			save: function () {
				return el(InnerBlocks.Content, null);
			}
		});
	}

	registerInner(
		'creatorreactor/follower',
		__('CreatorReactor: Follower', 'creatorreactor'),
		__(
			'Inner blocks show only for users with an active follower entitlement.',
			'creatorreactor'
		),
		'groups'
	);

	registerInner(
		'creatorreactor/subscriber',
		__('CreatorReactor: Subscriber', 'creatorreactor'),
		__(
			'Inner blocks show only for users with an active subscriber entitlement.',
			'creatorreactor'
		),
		'star-filled'
	);

	registerInner(
		'creatorreactor/not-logged-in',
		__('CreatorReactor: Logged in no role', 'creatorreactor'),
		__('Inner blocks show only for logged-in users with no active entitlement.', 'creatorreactor'),
		'visibility'
	);

	registerInner(
		'creatorreactor/logged-out',
		__('CreatorReactor: Logged out', 'creatorreactor'),
		__('Inner blocks show only when the visitor is not logged in.', 'creatorreactor'),
		'visibility'
	);

	registerInner(
		'creatorreactor/logged-in',
		__('CreatorReactor: Logged in', 'creatorreactor'),
		__('Inner blocks show only when the visitor is logged in.', 'creatorreactor'),
		'admin-users'
	);

	registerInner(
		'creatorreactor/onboarding-incomplete',
		__('CreatorReactor: Onboarding incomplete', 'creatorreactor'),
		__('Inner blocks show only while a logged-in user still needs onboarding.', 'creatorreactor'),
		'welcome-learn-more'
	);

	registerInner(
		'creatorreactor/onboarding-complete',
		__('CreatorReactor: Onboarding complete', 'creatorreactor'),
		__('Inner blocks show only after a logged-in user has completed onboarding.', 'creatorreactor'),
		'yes-alt'
	);

	registerInner(
		'creatorreactor/fanvue-connected',
		__('CreatorReactor: Fanvue connected', 'creatorreactor'),
		__('Inner blocks show only for logged-in users with Fanvue linked.', 'creatorreactor'),
		'admin-links'
	);

	registerInner(
		'creatorreactor/fanvue-not-connected',
		__('CreatorReactor: Fanvue not connected', 'creatorreactor'),
		__('Inner blocks show only for logged-in users without Fanvue linked.', 'creatorreactor'),
		'visibility'
	);

	blocks.registerBlockType('creatorreactor/has-tier', {
		apiVersion: 3,
		title: __('CreatorReactor: Has tier', 'creatorreactor'),
		description: __('Inner blocks show only for a logged-in user with matching tier.', 'creatorreactor'),
		category: 'creatorreactor',
		icon: 'awards',
		attributes: {
			tier: {
				type: 'string',
				default: ''
			},
			product: {
				type: 'string',
				default: ''
			}
		},
		edit: function (props) {
			var blockProps = useBlockProps({
				className: 'creatorreactor-block-editor creatorreactor-has-tier-block'
			});
			var attrs = props.attributes || {};
			var tierLabel = attrs.tier ? attrs.tier : __('any tier', 'creatorreactor');
			var productLabel = attrs.product ? attrs.product : __('any product', 'creatorreactor');
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Tier conditions', 'creatorreactor'), initialOpen: true },
						el(TextControl, {
							label: __('Tier', 'creatorreactor'),
							value: attrs.tier || '',
							onChange: function (value) {
								props.setAttributes({ tier: value });
							},
							help: __('Example: premium. Leave empty to match any active tier.', 'creatorreactor')
						}),
						el(TextControl, {
							label: __('Product', 'creatorreactor'),
							value: attrs.product || '',
							onChange: function (value) {
								props.setAttributes({ product: value });
							},
							help: __('Example: fanvue. Leave empty to match across products.', 'creatorreactor')
						})
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'creatorreactor-block-hint' },
						el('strong', null, __('CreatorReactor: Has tier', 'creatorreactor')),
						' — ',
						tierLabel,
						' / ',
						productLabel
					),
					el(InnerBlocks, null)
				)
			);
		},
		save: function () {
			return el(InnerBlocks.Content, null);
		}
	});

	blocks.registerBlockType('creatorreactor/fanvue-oauth', {
		apiVersion: 3,
		title: __('CreatorReactor: Login with Fanvue', 'creatorreactor'),
		description: __(
			'Displays the Fanvue OAuth login link (Creator/direct mode).',
			'creatorreactor'
		),
		category: 'creatorreactor',
		icon: 'admin-network',
		edit: function () {
			var blockProps = useBlockProps({
				className: 'creatorreactor-block-editor creatorreactor-fanvue-oauth-block'
			});
			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'creatorreactor-block-hint' },
					el('strong', null, __('Login with Fanvue', 'creatorreactor')),
					' — ',
					__(
						'Preview matches the front end (Agency mode shows a notice).',
						'creatorreactor'
					)
				),
				el(ServerSideRender, {
					block: 'creatorreactor/fanvue-oauth'
				})
			);
		},
		save: function () {
			return null;
		}
	});

	blocks.registerBlockType('creatorreactor/onboarding', {
		apiVersion: 3,
		title: __('CreatorReactor: Fan onboarding', 'creatorreactor'),
		description: __(
			'First-time Fanvue login setup form (same as /creatorreactor-onboarding/).',
			'creatorreactor'
		),
		category: 'creatorreactor',
		icon: 'welcome-learn-more',
		edit: function () {
			var blockProps = useBlockProps({
				className: 'creatorreactor-block-editor creatorreactor-onboarding-block'
			});
			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'creatorreactor-block-hint' },
					el('strong', null, __('Fan onboarding', 'creatorreactor')),
					' — ',
					__(
						'Preview matches the front end (Agency mode shows nothing).',
						'creatorreactor'
					)
				),
				el(ServerSideRender, {
					block: 'creatorreactor/onboarding'
				})
			);
		},
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.serverSideRender,
	window.wp.components
);
