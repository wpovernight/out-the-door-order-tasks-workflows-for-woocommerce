import React, {useEffect, useRef, useState} from "react";
import invariant from "tiny-invariant";
import {combine} from "@atlaskit/pragmatic-drag-and-drop/combine";
import {draggable, dropTargetForElements} from "@atlaskit/pragmatic-drag-and-drop/element/adapter";
import {autoScrollForElements} from "@atlaskit/pragmatic-drag-and-drop-auto-scroll/element";

import {useTasks} from "../../../context/TaskContext";
import {Task} from "../../../types/task";
import {getColumnData, isCardData} from "../data";
import {Card} from "./Card";

interface ColumnProps {
    column: string;
    tasks: Task[];
}

type ColumnState =
    | { type: "idle" }
    | { type: "drag-over-card"; draggingRect: DOMRect }
    | { type: "drag-over-empty" }
    | { type: "dragging" };

const IDLE: ColumnState = {type: "idle"};

export const Column: React.FC<ColumnProps> = ({column, tasks}) => {
    const scrollableRef = useRef<HTMLDivElement | null>(null);
    const headerRef = useRef<HTMLDivElement | null>(null);
    const containerRef = useRef<HTMLDivElement | null>(null);

    const [state, setState] = useState<ColumnState>(IDLE);
    // const {saveTask} = useTasks();

    useEffect(() => {
        console.log("[Column state]", state);
    }, []);

    useEffect(() => {
        const scrollable = scrollableRef.current;
        const header = headerRef.current;
        const container = containerRef.current;
        invariant(scrollable && header && container);

        const columnData = getColumnData({column});

        return combine(
            // Make the column draggable (for future enhancement)
            draggable({
                element: header,
                getInitialData: () => columnData,
                onDragStart() {
                    setState({type: "dragging"});
                },
                onDrop() {
                    setState(IDLE);
                }
            }),

            // Make column a valid drop target for cards.
            dropTargetForElements({
                element: container,
                canDrop: ({source}) => isCardData(source.data),
                getData: () => columnData,
                onDragEnter({source}) {
                    if (isCardData(source.data)) {
                        setState({type: "drag-over-card", draggingRect: source.data.rect});
                    }
                },
                onDropTargetChange({source, location}) {
                    if (isCardData(source.data)) {
                        const hasNoTasks = tasks.length === 0;
                        if (hasNoTasks) {
                            setState({type: "drag-over-empty"});
                        } else {
                            setState({type: "drag-over-card", draggingRect: source.data.rect});
                        }
                    }
                },
                onDragLeave() {
                    setState(IDLE);
                },
                async onDrop({source}) {
                    if (!isCardData(source.data)) return;
                    const {task, fromColumn} = source.data;
                    if (fromColumn !== column) {
                        // await saveTask(task.id, {column});
                    }
                    setState(IDLE);
                },
            }),

            // Auto-scroll while dragging cards.
            autoScrollForElements({
                element: scrollable,
                canScroll: ({source}) => isCardData(source.data),
            }),
        );
    }, [column, tasks]);

    return (
        <div className="kanban-column">
            <div ref={headerRef} className="kanban-column-header">
                <h2>{column}</h2>
            </div>
            <div ref={scrollableRef} className="kanban-column-scrollable">
                <div ref={containerRef} className="kanban-column-container">
                    {tasks.map((task) => (
                        <Card key={task.id} task={task} />
                    ))}

                    {/* Separator line at the bottom when dragging over empty space */}
                    {state.type === "drag-over-empty" && (
                        <div className="kanban-drop-indicator" />
                    )}
                </div>
            </div>
        </div>
    );
}