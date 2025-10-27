import React, {createContext, useContext, useEffect, useState} from "react";
import {Task, FieldOption} from "../types/task";
import {fetchTasks, fetchStatus, updateTask} from "../utils/api";

interface TaskContextType {
    tasks: Task[],
    setTasks: React.Dispatch<React.SetStateAction<Task[]>>,
    loadTasks: () => Promise<void>,
    saveTask: (taskId: number, updates: Partial<Task>) => Promise<void>,
    statuses: FieldOption[],
    loadStatuses: () => Promise<void>,
    // ToDo: add other CRUD operations here
}

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const TaskProvider: React.FC<{ children: React.ReactNode }> = ({children}) => {
    const [tasks, setTasks] = useState<Task[]>([]);
    const [statuses, setStatuses] = useState<FieldOption[]>([]);

    const loadTasks = async () => {
        try {
            const data = await fetchTasks();
            setTasks(data);
        } catch (error) {
            console.error("Failed to fetch tasks:", error);
        }
    }

    // ToDo: Finish this.
    const saveTask = async (taskId: number, updates: Partial<Task>) => {
        // try {
        // 	const updated = await updateTask(taskId, updates);
        // 	setTasks((prev) =>
        // 		prev.map((t) => (t.id === taskId ? { ...t, ...updated } : t))
        // 	);
        // } catch (err) {
        // 	console.error("Failed to update task:", err);
        // }
    };

    const loadStatuses = async () => {
        try {
            const data = await fetchStatus();
            setStatuses(data);
        } catch (error) {
            console.error("Failed to fetch columns:", error);
        }
    }

    return (
        <TaskContext.Provider value={{
            tasks: tasks,
            setTasks: setTasks,
            loadTasks: loadTasks,
            saveTask: saveTask,
            statuses: statuses,
            loadStatuses: loadStatuses,
        }}>
            {children}
        </TaskContext.Provider>
    );
}

/**
 * Custom hook to use the TaskContext.
 *
 * @returns {TaskContextType} The current tasks and a functions to do CRUD operations on tasks
 * @throws Will throw an error if used outside a TaskProvider
 */
export const useTasks = (): TaskContextType => {
    const context = useContext(TaskContext);
    if (!context) {
        throw new Error('useTasks must be used within a TaskProvider');
    }

    return context;
}
