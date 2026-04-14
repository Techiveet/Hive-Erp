"use client";

import React, { createContext, useContext, useState, useCallback, useEffect } from "react";
import Joyride, { Step, CallBackProps, STATUS, EVENTS, TooltipRenderProps } from "react-joyride";
import { Button } from "@/components/ui/button";
import { X } from "lucide-react";

interface TourContextType {
    startTour: (steps: Step[], type?: 'welcome' | 'system') => void;
    stopTour: () => void;
    currentStepTarget: string | null;
    isActive: boolean;
}

const TourContext = createContext<TourContextType | undefined>(undefined);

export const useTour = () => {
    const context = useContext(TourContext);
    if (!context) throw new Error("useTour must be used within a TourProvider");
    return context;
};

const CustomTooltip = React.forwardRef<HTMLDivElement, TooltipRenderProps>(
    ({ index, step, backProps, closeProps, primaryProps, skipProps, tooltipProps, isLastStep }, ref) => {
        if (!step) return null;

        const combinedStyle = {
            ...tooltipProps.style,
            zIndex: 1000000,
            backgroundColor: 'hsl(var(--card))',
            borderRadius: '1.5rem',
            boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
        };

        return (
            <div 
                {...tooltipProps} 
                ref={ref} 
                style={combinedStyle}
                className="w-[380px] p-6 border-2 border-primary/30 flex flex-col relative overflow-hidden"
            >
                {/* 🚀 BRANDED GLOW EFFECT */}
                <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-primary to-transparent opacity-60" />
                <div className="absolute -top-24 -right-24 h-48 w-48 bg-primary/10 blur-[100px] rounded-full pointer-events-none" />

                <Button 
                    variant="ghost" 
                    size="icon" 
                    className="absolute top-5 right-5 h-8 w-8 text-muted-foreground hover:bg-destructive/10 hover:text-destructive rounded-full transition-all border border-transparent hover:border-destructive/20" 
                    {...closeProps}
                >
                    <X className="h-4 w-4" />
                </Button>

                <div className="mb-8 mt-2 pr-6">
                    {step.title && (
                        <h3 className="font-space font-black text-xl tracking-tight mb-3 text-foreground flex items-center gap-3">
                            <span className="relative flex h-3 w-3">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
                            </span>
                            {step.title}
                        </h3>
                    )}
                    <div className="text-sm text-muted-foreground font-medium leading-relaxed tracking-wide">
                        {step.content}
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-border/50 pt-5 mt-auto relative z-10">
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        className="h-9 text-xs font-bold text-muted-foreground hover:text-foreground hover:bg-foreground/5 px-3 rounded-xl transition-colors" 
                        {...skipProps}
                    >
                        Dismiss
                    </Button>
                    
                    <div className="flex items-center gap-2">
                        {index > 0 && (
                            <Button 
                                variant="outline" 
                                size="sm" 
                                className="h-9 rounded-xl text-xs font-bold px-4 border-border/60 hover:bg-muted/50 transition-all active:scale-95" 
                                {...backProps}
                            >
                                Back
                            </Button>
                        )}
                        <Button 
                            size="sm" 
                            className="h-9 rounded-xl text-xs font-black px-6 shadow-lg shadow-primary/20 bg-primary hover:bg-primary/90 text-primary-foreground transition-all hover:scale-[1.02] active:scale-95 flex items-center gap-2" 
                            {...primaryProps}
                        >
                            {isLastStep ? (
                                <>Mission Complete</>
                            ) : (
                                <>Next Protocol</>
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        );
    }
);
CustomTooltip.displayName = "CustomTooltip";

export const TourProvider = ({ children }: { children: React.ReactNode }) => {
    const [isMounted, setIsMounted] = useState(false);
    const [run, setRun] = useState(false);
    const [steps, setSteps] = useState<Step[]>([]);
    const [stepIndex, setStepIndex] = useState(0);
    const [tourType, setTourType] = useState<'welcome' | 'system'>('system');

    useEffect(() => setIsMounted(true), []);

    const startTour = useCallback((newSteps: Step[], type: 'welcome' | 'system' = 'system') => {
        setTourType(type);
        setSteps(newSteps.map(step => ({ ...step, disableBeacon: true })));
        setStepIndex(0);
        setTimeout(() => setRun(true), 300); 
    }, []);

    const stopTour = useCallback(() => {
        setRun(false);
        setStepIndex(0);
    }, []);

    const syncTourCompletion = async () => {
        try {
            const token = localStorage.getItem('hive_token');
            if (!token) return;

            const { getBackendApiRoot, getTenantHeaders, isTenantSession } = await import("@/lib/runtime-context");
            const baseUrl = getBackendApiRoot();
            const endpoint = isTenantSession() ? `${baseUrl}/tenant/profile/tour-complete` : `${baseUrl}/profile/tour-complete`;

            await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    ...getTenantHeaders()
                }
            });
        } catch (error) {
            console.error("Failed to sync tour completion:", error);
        }
    };

    const handleJoyrideCallback = (data: CallBackProps) => {
        const { status, type, action, index } = data;

        if (type === EVENTS.TARGET_NOT_FOUND) {
            setStepIndex(index + (action === 'prev' ? -1 : 1));
        } else if (type === EVENTS.STEP_AFTER) {
            setStepIndex(index + (action === 'prev' ? -1 : 1));
        } else if ([STATUS.FINISHED, STATUS.SKIPPED].includes(status as any)) {
            const isFinished = status === STATUS.FINISHED;
            setRun(false);
            setStepIndex(0);
            
            // Persist locally for immediate feedback
            if (tourType === 'welcome') {
                localStorage.setItem('hive_welcome_tour_completed', 'true');
                if (isFinished) syncTourCompletion();
            } else {
                localStorage.setItem('hive_tour_completed', 'true');
            }
        }
    };

    const currentStepTarget = steps[stepIndex]?.target as string | null;

    return (
        <TourContext.Provider value={{ startTour, stopTour, currentStepTarget, isActive: run }}>
            {children}
            {isMounted && (
                <Joyride
                    steps={steps}
                    run={run}
                    stepIndex={stepIndex}
                    callback={handleJoyrideCallback}
                    continuous={true}
                    hideCloseButton={true}
                    disableOverlayClose={true}
                    showSkipButton={true}
                    showProgress={false}
                    scrollOffset={150} 
                    disableScrollParentFix={true}
                    tooltipComponent={CustomTooltip}
                    floaterProps={{ 
                        hideArrow: true,
                        offset: 20,
                        disableAnimation: true, // Prevents laggy tooltip repositioning
                        styles: { popper: { zIndex: 999999 } }
                    } as any}
                    styles={{
                        options: { 
                            overlayColor: 'rgba(0, 0, 0, 0.5)', 
                            zIndex: 999999,
                            primaryColor: 'hsl(var(--primary))'
                        },
                        spotlight: { borderRadius: '2rem' }
                    }}
                />
            )}
        </TourContext.Provider>
    );
};