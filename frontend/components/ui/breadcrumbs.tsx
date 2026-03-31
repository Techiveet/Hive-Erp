import * as React from "react";
import Link from "next/link";
import { ChevronRight, MoreHorizontal } from "lucide-react";
import { cn } from "@/lib/utils";

export interface BreadcrumbItem {
    label: React.ReactNode;
    href?: string;
    icon?: React.ReactNode;
}

interface BreadcrumbsProps extends React.ComponentPropsWithoutRef<"nav"> {
    items: BreadcrumbItem[];
    separator?: React.ReactNode;
    /** If true, collapses middle items into an ellipsis if there are more than 4 items */
    collapse?: boolean; 
}

export function Breadcrumbs({
    items,
    separator = <ChevronRight className="h-3.5 w-3.5 mx-1 opacity-50" />,
    collapse = false,
    className,
    ...props
}: BreadcrumbsProps) {
    if (!items || items.length === 0) return null;

    // Logic to handle collapsing long breadcrumb trails
    const renderItems = () => {
        if (collapse && items.length > 4) {
            return [
                items[0],
                { label: <MoreHorizontal className="h-4 w-4 opacity-50" /> },
                items[items.length - 2],
                items[items.length - 1],
            ];
        }
        return items;
    };

    const displayItems = renderItems();

    return (
        <nav
            aria-label="breadcrumb"
            className={cn("flex items-center text-sm text-muted-foreground font-medium", className)}
            {...props}
        >
            <ol className="flex items-center flex-wrap gap-1">
                {displayItems.map((item, index) => {
                    const isLast = index === displayItems.length - 1;
                    
                    // 🚀 THE FIX: Use 'in' operator to safely check for properties on the Union type
                    const href = 'href' in item ? item.href : undefined;
                    const icon = 'icon' in item ? item.icon : undefined;
                    
                    const isCollapsedIcon = !href && !icon && React.isValidElement(item.label) && item.label.type === MoreHorizontal;

                    return (
                        <li key={index} className="flex items-center">
                            {/* Item */}
                            {href && !isLast ? (
                                <Link
                                    href={href}
                                    className="flex items-center gap-1.5 transition-colors hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 rounded-sm px-1"
                                >
                                    {icon && <span className="shrink-0">{icon}</span>}
                                    <span className="truncate max-w-[150px] sm:max-w-[200px]">{item.label}</span>
                                </Link>
                            ) : (
                                <span
                                    className={cn(
                                        "flex items-center gap-1.5 px-1",
                                        isLast ? "text-foreground font-semibold" : "cursor-default",
                                        isCollapsedIcon && "px-0"
                                    )}
                                    aria-current={isLast ? "page" : undefined}
                                >
                                    {icon && <span className="shrink-0">{icon}</span>}
                                    <span className="truncate max-w-[150px] sm:max-w-[250px]">{item.label}</span>
                                </span>
                            )}

                            {/* Separator */}
                            {!isLast && (
                                <span className="flex items-center justify-center pointer-events-none select-none shrink-0" aria-hidden="true">
                                    {separator}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}