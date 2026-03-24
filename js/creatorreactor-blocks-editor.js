/**
 * CreatorReactor Gutenberg blocks (editor script).
 */
(function (blocks, element, blockEditor, i18n, serverSideRender) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InnerBlocks = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;
	var __ = i18n.__;
	var ServerSideRender = serverSideRender;

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
		__('CreatorReactor: Not logged in', 'creatorreactor'),
		__('Inner blocks show only when the visitor is not logged in.', 'creatorreactor'),
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
	window.wp.serverSideRender
);
