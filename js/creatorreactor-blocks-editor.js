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
	var SelectControl = components.SelectControl;

	function registerInner(name, title, description, icon) {
		blocks.registerBlockType(name, {
			apiVersion: 3,
			title: title,
			description: description,
			category: 'creatorreactor',
			icon: icon,
			attributes: {
				container_logic: {
					type: 'string',
					default: 'and'
				}
			},
			edit: function (props) {
				var blockProps = useBlockProps({
					className: 'creatorreactor-block-editor'
				});
				var attrs = props.attributes || {};
				var containerLogic = attrs.container_logic ? attrs.container_logic : 'and';

				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('Container visibility', 'creatorreactor'), initialOpen: false },
							el(SelectControl, {
								label: __('Container visibility logic', 'creatorreactor'),
								value: containerLogic,
								options: [
									{
										label: __('AND (current): hide container if any gate fails', 'creatorreactor'),
										value: 'and'
									},
									{
										label: __('OR: show container if any gate passes', 'creatorreactor'),
										value: 'or'
									}
								],
								onChange: function (value) {
									props.setAttributes({ container_logic: value });
								}
							})
						)
					),
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
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.serverSideRender,
	window.wp.components
);
