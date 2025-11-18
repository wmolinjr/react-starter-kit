import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PageBlock } from '@/types';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Edit,
    GripVertical,
    MoreVertical,
    Trash2,
} from 'lucide-react';
import { ReactNode } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { motion } from 'framer-motion';

interface BlockItemProps {
    block: PageBlock;
    isFirst: boolean;
    isLast: boolean;
    onEdit: (block: PageBlock) => void;
    onMoveUp: (blockId: number) => void;
    onMoveDown: (blockId: number) => void;
    onDuplicate: (blockId: number) => void;
    onDelete: (blockId: number) => void;
    children: ReactNode;
}

export function BlockItem({
    block,
    isFirst,
    isLast,
    onEdit,
    onMoveUp,
    onMoveDown,
    onDuplicate,
    onDelete,
    children,
}: BlockItemProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: block.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <motion.div
            ref={setNodeRef}
            style={style}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            className={isDragging ? 'z-50 opacity-50' : ''}
        >
            <Card className="group relative overflow-hidden transition-all hover:shadow-md">
                {/* Drag Handle */}
                <div
                    {...attributes}
                    {...listeners}
                    className="absolute left-0 top-0 flex h-full w-8 cursor-grab items-center justify-center border-r bg-muted/30 opacity-0 transition-opacity hover:bg-muted/50 active:cursor-grabbing group-hover:opacity-100"
                >
                    <GripVertical className="h-4 w-4 text-muted-foreground" />
                </div>

            <CardHeader className="border-b bg-muted/30 py-3 pl-10 pr-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium capitalize">
                            {block.block_type} Block
                        </span>
                        <span className="text-xs text-muted-foreground">
                            #{block.id}
                        </span>
                    </div>

                    <div className="flex items-center gap-1">
                        {/* Move Up/Down Buttons */}
                        <div className="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                onClick={() => onMoveUp(block.id)}
                                disabled={isFirst}
                                title="Move up"
                            >
                                <ArrowUp className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                onClick={() => onMoveDown(block.id)}
                                disabled={isLast}
                                title="Move down"
                            >
                                <ArrowDown className="h-3.5 w-3.5" />
                            </Button>
                        </div>

                        {/* More Actions Menu */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7"
                                >
                                    <MoreVertical className="h-3.5 w-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => onEdit(block)}
                                >
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit Block
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => onDuplicate(block.id)}
                                >
                                    <Copy className="mr-2 h-4 w-4" />
                                    Duplicate
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => onDelete(block.id)}
                                    className="text-destructive focus:text-destructive"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="p-6 pl-10">
                {children}
            </CardContent>
        </Card>
        </motion.div>
    );
}
