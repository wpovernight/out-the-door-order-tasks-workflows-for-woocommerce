import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { CustomOrderStatus } from '@sdk';

interface StatusFormProps {
	status?: CustomOrderStatus;
	onSave: (data: {
		label: string;
		status_key: string;
		background: string;
	}) => Promise<void>;
	onCancel: () => void;
	isSaving: boolean;
}

function slugify(text: string): string {
	return text
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

function getForegroundColor(hexBg: string): string {
	const hex = hexBg.replace('#', '');
	const r = parseInt(hex.substring(0, 2), 16);
	const g = parseInt(hex.substring(2, 4), 16);
	const b = parseInt(hex.substring(4, 6), 16);
	const yiq = (r * 299 + g * 587 + b * 114) / 1000;
	return yiq >= 128 ? '#000' : '#fff';
}

export const StatusForm = ({
	status,
	onSave,
	onCancel,
	isSaving,
}: StatusFormProps) => {
	const isEditing = !!status;
	const [label, setLabel] = useState(status?.label ?? '');
	const [statusKey, setStatusKey] = useState(status?.status_key ?? '');
	const [background, setBackground] = useState(
		status?.background ?? '#6E1EDC'
	);
	const [keyManuallyEdited, setKeyManuallyEdited] = useState(isEditing);

	useEffect(() => {
		if (!keyManuallyEdited && label) {
			setStatusKey(slugify(label));
		}
	}, [label, keyManuallyEdited]);

	const foreground = getForegroundColor(background);

	let submitLabel = __(
		'Create',
		'out-the-door-order-tasks-workflows-for-woocommerce'
	);
	if (isSaving) {
		submitLabel = __(
			'Saving…',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		);
	} else if (isEditing) {
		submitLabel = __(
			'Update',
			'out-the-door-order-tasks-workflows-for-woocommerce'
		);
	}

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault();
		if (!label.trim() || !statusKey.trim()) {
			return;
		}
		onSave({
			label: label.trim(),
			status_key: statusKey.trim(),
			background,
		});
	};

	return (
		<tr className="cos-form-row">
			<td colSpan={4}>
				<form onSubmit={handleSubmit}>
					<fieldset>
						<div id="label-field" className="form-field">
							<label htmlFor="cos-label">
								{__(
									'Status Name',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</label>
							<input
								id="cos-label"
								type="text"
								value={label}
								onChange={(e) => setLabel(e.target.value)}
								placeholder={__(
									'e.g., In production',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
								required
							/>
						</div>
						<div id="preview-field" className="form-field">
							<span className="label">
								{__(
									'Preview',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</span>
							<span
								className="status-preview"
								style={{
									backgroundColor: background,
									color: foreground,
								}}
							>
								{label ||
									__(
										'Status',
										'out-the-door-order-tasks-workflows-for-woocommerce'
									)}
							</span>
						</div>
						<div id="color-field" className="form-field">
							<label htmlFor="cos-background">
								{__(
									'Color',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</label>
							<div
								className="color-input-group"
								role="button"
								tabIndex={0}
								onClick={() =>
									document
										.getElementById('cos-background')
										?.click()
								}
								onKeyDown={(e) => {
									if (e.key === 'Enter' || e.key === ' ') {
										e.preventDefault();
										document
											.getElementById('cos-background')
											?.click();
									}
								}}
								style={{ cursor: 'pointer' }}
							>
								<input
									id="cos-background"
									type="color"
									value={background}
									onChange={(e) =>
										setBackground(e.target.value)
									}
								/>
								<span className="cos-color-hex">
									{background.toUpperCase()}
								</span>
							</div>
						</div>
						<div id="slug-field" className="form-field">
							<label htmlFor="cos-key">
								{__(
									'Slug',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</label>
							{/*
								`pattern` is compiled with the regex `v` flag, which treats a
								bare `-` as reserved inside a character class, so it is escaped.
							*/}
							<input
								id="cos-key"
								type="text"
								value={statusKey}
								onChange={(e) => {
									setKeyManuallyEdited(true);
									setStatusKey(e.target.value);
								}}
								pattern="^[a-z0-9_\-]+$"
								title={__(
									'Only lowercase letters, numbers, hyphens, and underscores.',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
								required
								disabled={isEditing}
							/>
						</div>
					</fieldset>

					<ul className="wpo-otd-form-actions">
						<li>
							<button
								type="submit"
								className="wpo-button wpo-button-primary add-status-button"
								disabled={
									isSaving ||
									!label.trim() ||
									!statusKey.trim()
								}
							>
								{submitLabel}
							</button>
						</li>
						<li>
							<button
								type="button"
								className="wpo-button wpo-button-secondary cancel-button"
								onClick={onCancel}
								disabled={isSaving}
							>
								{__(
									'Cancel',
									'out-the-door-order-tasks-workflows-for-woocommerce'
								)}
							</button>
						</li>
					</ul>
				</form>
			</td>
		</tr>
	);
};
