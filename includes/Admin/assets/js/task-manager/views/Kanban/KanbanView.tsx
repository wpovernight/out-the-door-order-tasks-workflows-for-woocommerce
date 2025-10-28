import React, {useEffect} from "react";
import {useTasks} from "../../context/TaskContext";
import {Board} from "./components/Board";

export const KanbanView: React.FC = () => {
    const {loadTasks, loadStatuses} = useTasks();
    const [isInitialized, setIsInitialized] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    useEffect(() => {
        let isCancelled = false;

        // Initialize by loading tasks and columns.
        const initialize = async () => {
            setLoading(true);
            try {
                await Promise.all([loadTasks(), loadStatuses()]);
                if (!isCancelled) setIsInitialized(true);
            } catch (error: unknown) {
                if (!isCancelled) {
                    setError(
                        error instanceof Error
                            ? error
                            : new Error("Unknown initialization error")
                    );
                }
            } finally {
                if (!isCancelled) setLoading(false);
            }
        };

        if (!isInitialized) {
            console.log("Initializing Kanban View...");
            initialize();
        }

        return () => {
            isCancelled = true;
        };
    }, [ isInitialized]);

    if (!isInitialized || loading) {
        return (
            <div className="loading-spinner">
                {(window as any).WPO_AOM_TaskManager.loading}
            </div>
        );
    }

    if (error) {
        return (
            <div className="error-message">
                {(window as any).WPO_AOM_TaskManager.errorLoading}
            </div>
        );
    }

    return (
        <Board/>
    );
};