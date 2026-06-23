import React from 'react';

interface SectionHeaderProps {
	title: string;
	details?: string;
	progressValue?: number;
	actionButtons?: React.ReactNode[];
}

export const SectionHeader: React.FC<SectionHeaderProps> = ({
	title,
	details,
	progressValue,
	actionButtons,
}) => {
	return (
		<div className="header">
			<div className="title-details">
				<h3>{title}</h3>
				<span>{details}</span>
			</div>
			{typeof progressValue === 'number' && (
				<label className="task-progress" htmlFor="progress">
					<progress id="progress" value={progressValue} max="100">
						{Math.round(progressValue)}%
					</progress>
					<span> {Math.round(progressValue)}%</span>
				</label>
			)}
			{actionButtons && (
				<ul className="actions">
					{actionButtons.map((button, index) => (
						<li key={index}>{button}</li>
					))}
				</ul>
			)}
		</div>
	);
};

export default SectionHeader;
