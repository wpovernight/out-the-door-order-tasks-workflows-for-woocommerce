export const truncateText = (text: string, maxLength: number = 140): string => {
	if (!text || text.length <= maxLength) {
		return text;
	}

	// Find the last space within maxLength.
	const truncated = text.substring(0, maxLength);
	const lastSpaceIndex = truncated.lastIndexOf(' ');

	// If there's a space, cut there; otherwise cut at maxLength.
	if (lastSpaceIndex > 0) {
		return truncated.substring(0, lastSpaceIndex).trim() + '...';
	}

	return truncated.trim() + '...';
};
