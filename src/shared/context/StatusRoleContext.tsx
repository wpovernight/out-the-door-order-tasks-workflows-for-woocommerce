import React, { createContext, useCallback, useContext, useState } from 'react';
import { StatusRoles } from '@shared/types/task';
import { getInitialStatusRoles } from '@shared/hooks/getInitialStatusRoles';
import { updateStatusRoles as updateStatusRolesAPI } from '@shared/utils/api';

interface StatusRoleContextType {
	statusRoles: StatusRoles;
	/**
	 * Persist a partial update to the role assignments and update local state.
	 * Returns the new state from the server (which may differ from `updates`
	 * if the server applied any normalization).
	 */
	updateStatusRoles: (updates: Partial<StatusRoles>) => Promise<StatusRoles>;
}

const StatusRoleContext = createContext<StatusRoleContextType | undefined>(
	undefined
);

export const StatusRoleProvider: React.FC<{ children: React.ReactNode }> = ({
	children,
}) => {
	// Seed from the bootstrap data on first render; from then on, state owns
	// the truth so consumers re-render reactively when the user changes roles.
	const [statusRoles, setStatusRoles] = useState<StatusRoles>(
		getInitialStatusRoles
	);

	const updateStatusRoles = useCallback(
		async (updates: Partial<StatusRoles>): Promise<StatusRoles> => {
			const updated = await updateStatusRolesAPI(updates);
			setStatusRoles(updated);
			return updated;
		},
		[]
	);

	return (
		<StatusRoleContext.Provider value={{ statusRoles, updateStatusRoles }}>
			{children}
		</StatusRoleContext.Provider>
	);
};

export const useStatusRoles = (): StatusRoleContextType => {
	const context = useContext(StatusRoleContext);
	if (!context) {
		throw new Error(
			'useStatusRoles must be used within a StatusRoleProvider'
		);
	}
	return context;
};
