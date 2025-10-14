import React, {useEffect} from "react";
import {useTasks} from "../../context/TaskContext";
import {Board} from "./components/Board";

export const KanbanView: React.FC = () => {
    const {tasks, reloadTasks} = useTasks();
    const [isInitialized, setIsInitialized] = React.useState(false);
    const [loading, setLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    useEffect(() => {
        const initializeTasks = async () => {
            try {
                await reloadTasks();
            } catch (error) {
                console.error("Failed to load tasks:", error);
            }
            setIsInitialized(true);
        };

        if (!isInitialized) {
            initializeTasks();
        }
    }, [reloadTasks, isInitialized]);

    if (!isInitialized || loading) {
        return (
            <div className="kanban-view">
                <div className="loading-spinner">Loading tasks...</div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="kanban-view">
                <div className="error-message">
                    Error loading tasks: {error.message}
                    <button onClick={() => reloadTasks()} className="retry-button">
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="kanban-view">
            {tasks.length === 0 ? (
                <div className="empty-state">
                    <p>No tasks available. Please add some tasks to get started.</p>
                </div>
            ) : (
                <Board/>
            )}
        </div>
    );
};