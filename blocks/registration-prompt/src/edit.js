import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import './editor.scss';

export default function Edit({ attributes, setAttributes, context }) {
	const { displayMode, showQuickLogin } = attributes;

	// Fetch settings from WooCommerce
	const { autoDisplayEnabled, customMessage } = useSelect((select) => {
		const { getEntityRecord } = select(coreStore);
		const settings = getEntityRecord('root', 'site');

		return {
			autoDisplayEnabled:
				settings?.woocommerce_enable_post_checkout_registration === 'yes',
			customMessage:
				settings?.wc_pcr_new_account_msg ||
				__(
					"Ensure checkout is fast and easy next time! Create an account and we'll save your address details from this order.",
					'wc-pcr'
				),
		};
	}, []);

	// Detect if we're on a checkout/thank you page context
	const isCheckoutContext =
		context?.postType === 'page' &&
		(context?.['woocommerce/is-checkout'] ||
			context?.['woocommerce/is-order-received']);

	const blockProps = useBlockProps({
		className: 'wc-pcr-registration-prompt-block',
	});

	// Show conflict warning if both automatic and block are active on checkout
	const hasConflict =
		autoDisplayEnabled && isCheckoutContext && displayMode === 'auto';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Display Settings', 'wc-pcr')}
					initialOpen={true}
				>
					{autoDisplayEnabled && (
						<Notice
							status="info"
							isDismissible={false}
							className="wc-pcr-info-notice"
						>
							{__(
								'Automatic display is enabled in WooCommerce settings. This block provides manual placement control.',
								'wc-pcr'
							)}
						</Notice>
					)}

					<SelectControl
						label={__('Display Mode', 'wc-pcr')}
						value={displayMode}
						options={[
							{
								label: __(
									'Auto (order confirmation only)',
									'wc-pcr'
								),
								value: 'auto',
							},
							{
								label: __('Always show', 'wc-pcr'),
								value: 'always',
							},
							{
								label: __('Never show', 'wc-pcr'),
								value: 'never',
							},
						]}
						onChange={(value) =>
							setAttributes({ displayMode: value })
						}
						help={__(
							'Control when the registration prompt appears.',
							'wc-pcr'
						)}
					/>

					<ToggleControl
						label={__('Show Quick Login Form', 'wc-pcr')}
						checked={showQuickLogin}
						onChange={(value) =>
							setAttributes({ showQuickLogin: value })
						}
						help={__(
							'Display login form for existing customers.',
							'wc-pcr'
						)}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{!autoDisplayEnabled && (
					<Notice status="warning" isDismissible={false}>
						{__(
							'⚠️ Post-checkout registration is disabled. Please enable it in WooCommerce → Settings → Accounts & Privacy for this block to work.',
							'wc-pcr'
						)}
					</Notice>
				)}

				{hasConflict && (
					<Notice status="warning" isDismissible={false}>
						{__(
							'⚠️ Automatic display is already enabled for checkout pages. This block will be hidden to prevent duplication.',
							'wc-pcr'
						)}
					</Notice>
				)}

				<div className="wc-pcr-block-preview">
				<span
				className="dashicons dashicons-info"
				style={{
				color: '#2271b1',
				fontSize: '20px',
				marginRight: '8px',
				}}
				></span>
				<div className="wc-pcr-block-preview__content">
				<p>{customMessage}</p>
				<button
				type="button"
				className="wc-pcr-block-preview__button"
				disabled
				>
				{__('Create Account', 'wc-pcr')}
				</button>
				</div>
				</div>
			</div>
		</>
	);
}
