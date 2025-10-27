import React, {useEffect} from "react";
import {useTasks} from "../../context/TaskContext";
import {Board} from "./components/Board";

export const KanbanView: React.FC = () => {
    const {loadTasks, loadStatuses} = useTasks();
    const [isInitialized, setIsInitialized] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    useEffect(() => {
        // Initialize by loading tasks and columns.
        const initialize = async () => {
            try {
                await loadTasks();
                await loadStatuses();
            } catch (error) {
                console.error("Failed to load tasks:", error);
            }
            setIsInitialized(true);
        };

        if (!isInitialized) {
            initialize();
        }
    }, [loadTasks, loadStatuses, isInitialized]);

    // ToDo: Improve and translatable
    if (!isInitialized || loading) {
        return (
            <div className="loading-spinner">{(window as any).WPO_AOM_TaskManager.loading}</div>
        );
    }

    // ToDo: Improve and translatable
    if (error) {
        return (
            <div className="error-message">
                Error loading tasks: {error.message}
            </div>
        );
    }

    return (
        <Board/>
    );
};