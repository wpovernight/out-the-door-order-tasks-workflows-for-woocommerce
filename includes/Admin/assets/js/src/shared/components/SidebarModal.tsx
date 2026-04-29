import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { __ } from '@wordpress/i18n';

interface SidebarModalProps {
	isOpen: boolean;
	onClose: () => void;
	title?: string;
	children: React.ReactNode;
}

export const SidebarModal: React.FC<SidebarModalProps> = ({
	isOpen,
	onClose,
	title,
	children,
}) => {
	return (
		<AnimatePresence>
			{isOpen && (
				<>
					{/* Backdrop */}
					<motion.div
						key="backdrop"
						className="wpo-aom-sidebar-backdrop"
						initial={{ opacity: 0 }}
						animate={{ opacity: 1 }}
						exit={{ opacity: 0 }}
						transition={{ duration: 0.3 }}
						onClick={onClose}
					/>

					{/* Sidebar Modal */}
					<motion.div
						key="sidebar-modal"
						className="wpo-aom-sidebar-modal"
						initial={{ x: '100%' }}
						animate={{ x: 0 }}
						exit={{ x: '100%' }}
						transition={{ type: 'tween', duration: 0.3 }}
					>
						<div className="inner">
							<div className="wpo-aom-sidebar-header">
								{title && <h2>{title}</h2>}
								<button
									type="button"
									className="wpo-button wpo-button-icon wpo-aom-sidebar-close"
									onClick={onClose}
								>
									<span className="screenReader">
										{__('Close', 'wpo-advanced-order-manager')}
									</span>
								</button>
							</div>

							<div className="wpo-aom-sidebar-content">
								{children}
							</div>
						</div>
					</motion.div>
				</>
			)}
		</AnimatePresence>
	);
};
