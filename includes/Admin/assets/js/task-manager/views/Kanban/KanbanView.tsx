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

    // ToDo: Improve and translatable
    if (!isInitialized || loading) {
        return (
            <div className="loading-spinner">Loading tasks...</div>
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