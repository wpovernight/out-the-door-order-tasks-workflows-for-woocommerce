import React from 'react';

type RGB = [number, number, number];

const hexToRgb = (hex: string): RGB | null => {
	let c = hex.replace('#', '');

	if (c.length === 3) {
		c = c
			.split('')
			.map((ch) => ch + ch)
			.join('');
	}

	if (!/^[0-9A-Fa-f]{6}$/.test(c)) {
		return null;
	}

	return [
		parseInt(c.slice(0, 2), 16),
		parseInt(c.slice(2, 4), 16),
		parseInt(c.slice(4, 6), 16),
	];
};

const rgbToHex = (rgb: RGB): string => {
	return `#${rgb.map((c) => Math.round(c).toString(16).padStart(2, '0')).join('')}`;
};

const relativeLuminance = (rgb: RGB): number => {
	const channels = rgb.map((channel) => {
		const srgb = channel / 255;
		return srgb <= 0.03928
			? srgb / 12.92
			: Math.pow((srgb + 0.055) / 1.055, 2.4);
	});

	return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
};

const hasSufficientContrast = (
	foregroundRgb: RGB,
	backgroundRgb: RGB
): boolean => {
	const foregroundLuminance = relativeLuminance(foregroundRgb);
	const backgroundLuminance = relativeLuminance(backgroundRgb);

	const contrastRatio =
		(Math.max(foregroundLuminance, backgroundLuminance) + 0.05) /
		(Math.min(foregroundLuminance, backgroundLuminance) + 0.05);

	return contrastRatio >= 4.5;
};

/**
 * Gets foreground color using PHP-style fixed adjustment
 * Matches the original PHP implementation
 * @param hexBg
 */
export const getForegroundColor = (hexBg?: string): string => {
	if (!hexBg) {
		return '#000';
	}

	const bgRgb = hexToRgb(hexBg);
	if (!bgRgb) {
		return '#000';
	}

	const brightness =
		(bgRgb[0] * 299 + bgRgb[1] * 587 + bgRgb[2] * 114) / 1000;

	const adjust: RGB = [175, 175, 150];

	let fgRgb: RGB;

	if (brightness > 125) {
		// Light background: darken color
		fgRgb = [
			Math.max(0, bgRgb[0] - adjust[0]),
			Math.max(0, bgRgb[1] - adjust[1]),
			Math.max(0, bgRgb[2] - adjust[2]),
		];
	} else {
		// Dark background: lighten color
		fgRgb = [
			Math.min(255, bgRgb[0] + adjust[0]),
			Math.min(255, bgRgb[1] + adjust[1]),
			Math.min(255, bgRgb[2] + adjust[2]),
		];
	}

	// Ensure sufficient contrast - fallback to black or white
	if (!hasSufficientContrast(fgRgb, bgRgb)) {
		fgRgb = brightness > 125 ? [0, 0, 0] : [255, 255, 255];
	}

	return rgbToHex(fgRgb);
};

export const calculateTextColor = (hex?: string): string => {
	if (!hex) {
		return '#000';
	}

	const rgb = hexToRgb(hex);
	if (!rgb) {
		return '#000';
	}

	const brightness = (299 * rgb[0] + 587 * rgb[1] + 114 * rgb[2]) / 1000;
	return brightness > 128 ? '#000' : '#fff';
};

export const getColorStyle = (
	backgroundColor?: string,
	useAdjustedColor = true
): React.CSSProperties => {
	if (!backgroundColor) {
		return {};
	}

	return {
		backgroundColor,
		color: useAdjustedColor
			? getForegroundColor(backgroundColor)
			: calculateTextColor(backgroundColor),
	};
};
