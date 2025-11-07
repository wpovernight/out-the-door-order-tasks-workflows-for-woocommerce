import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';

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
	if (!isOpen) {
		return null;
	}

	return (
		<AnimatePresence>
			<>
				{/* Backdrop */}
				<motion.div
					className="wpo-aom-sidebar-backdrop"
					initial={{ opacity: 0 }}
					animate={{ opacity: 1 }}
					exit={{ opacity: 0 }}
					transition={{ duration: 0.3 }}
					onClick={onClose}
				/>

				{/* Sidebar Modal */}
				<motion.aside
					key="sidebar-modal"
					className="wpo-aom-sidebar-modal"
					initial={{ x: '100%' }}
					animate={{ x: 0 }}
					exit={{ x: '100%' }}
					transition={{ type: 'tween', duration: 0.3 }}
				>
					<div className="wpo-aom-sidebar-header">
						{title && (
							<h2 className="wpo-aom-sidebar-title">{title}</h2>
						)}
						<button
							className="wpo-aom-sidebar-close"
							onClick={onClose}
						>
							<span className="screenReader">Close</span>
						</button>
					</div>
					<div className="wpo-aom-sidebar-content">{children}</div>
				</motion.aside>
			</>
		</AnimatePresence>
	);
};
