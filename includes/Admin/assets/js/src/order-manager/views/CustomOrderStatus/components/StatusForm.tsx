import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { CustomOrderStatus } from '@shared/types/customOrderStatus';

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
								{__('Status Name', 'wpo-aom')}
							</label>
							<input
								id="cos-label"
								type="text"
								value={label}
								onChange={(e) => setLabel(e.target.value)}
								placeholder={__(
									'e.g., In production',
									'wpo-aom'
								)}
								required
								autoFocus
							/>
						</div>
						<div id="preview-field" className="form-field">
							<span className="label">
								{__('Preview', 'wpo-aom')}
							</span>
							<span
								className="status-preview"
								style={{
									backgroundColor: background,
									color: foreground,
								}}
							>
								{label || __('Status', 'wpo-aom')}
							</span>
						</div>
						<div id="color-field" className="form-field">
							<label htmlFor="cos-background">
								{__('Color', 'wpo-aom')}
							</label>
							<div
								className="color-input-group"
								onClick={() =>
									document
										.getElementById('cos-background')
										?.click()
								}
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
								{__('Slug', 'wpo-aom')}
							</label>
							<input
								id="cos-key"
								type="text"
								value={statusKey}
								onChange={(e) => {
									setKeyManuallyEdited(true);
									setStatusKey(e.target.value);
								}}
								pattern="^[a-z0-9_-]+$"
								title={__(
									'Only lowercase letters, numbers, hyphens, and underscores.',
									'wpo-aom'
								)}
								required
								disabled={isEditing}
							/>
						</div>
					</fieldset>

					<ul className="wpo-aom-form-actions">
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
								{isSaving
									? __('Saving…', 'wpo-aom')
									: isEditing
										? __('Update', 'wpo-aom')
										: __('Create', 'wpo-aom')}
							</button>
						</li>
						<li>
							<button
								type="button"
								className="wpo-button wpo-button-secondary cancel-button"
								onClick={onCancel}
								disabled={isSaving}
							>
								{__('Cancel', 'wpo-aom')}
							</button>
						</li>
					</ul>
				</form>
			</td>
		</tr>
	);
};
