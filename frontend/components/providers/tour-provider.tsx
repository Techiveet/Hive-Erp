"use client";

import React, { createContext, useContext, useState, useCallback, useEffect } from "react";
import Joyride, { Step, CallBackProps, STATUS, EVENTS, TooltipRenderProps } from "react-joyride";
import { Button } from "@/components/ui/button";
import { X } from "lucide-react";

interface TourContextType {
    startTour: (steps: Step[]) => void;
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
        return (
            <div 
                {...tooltipProps} 
                ref={ref} 
                className="w-[340px] p-6 border border-amber-500/20 dark:border-amber-500/30 bg-white dark:bg-[#0a0a0b] shadow-[0_0_40px_-10px_rgba(245,158,11,0.15)] rounded-[1.5rem] flex flex-col relative overflow-hidden z-[100001]"
            >
                <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-50" />

                <Button 
                    variant="ghost" 
                    size="icon" 
                    className="absolute top-4 right-4 h-7 w-7 text-slate-400 hover:text-slate-900 dark:text-zinc-500 dark:hover:text-zinc-50 hover:bg-red-500/10 hover:text-red-500 rounded-full transition-all" 
                    {...closeProps}
                >
                    <X className="h-4 w-4" />
                </Button>

                <div className="mb-6 mt-1 pr-8">
                    {step.title && (
                        <h3 className="font-space font-black text-lg tracking-tight mb-2 text-slate-900 dark:text-zinc-50 flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse" />
                            {step.title}
                        </h3>
                    )}
                    <div className="text-sm text-slate-600 dark:text-zinc-400 font-medium leading-relaxed">
                        {step.content}
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-slate-100 dark:border-zinc-800/80 pt-5 mt-auto">
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        className="h-8 text-xs font-semibold text-slate-400 hover:text-slate-900 dark:text-zinc-500 dark:hover:text-zinc-100 px-2" 
                        {...skipProps}
                    >
                        Skip Tour
                    </Button>
                    
                    <div className="flex items-center gap-2">
                        {index > 0 && (
                            <Button 
                                variant="outline" 
                                size="sm" 
                                className="h-8 rounded-xl text-xs font-bold px-4 shadow-sm border-slate-200 dark:border-zinc-800 text-slate-900 dark:text-zinc-100 hover:bg-slate-50 dark:hover:bg-zinc-900" 
                                {...backProps}
                            >
                                Back
                            </Button>
                        )}
                        <Button 
                            size="sm" 
                            className="h-8 rounded-xl text-xs font-bold px-5 shadow-lg shadow-amber-500/20 bg-amber-500 hover:bg-amber-400 text-zinc-950 transition-all" 
                            {...primaryProps}
                        >
                            {isLastStep ? 'Finish' : 'Next Protocol'}
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

    useEffect(() => setIsMounted(true), []);

    const startTour = useCallback((newSteps: Step[]) => {
        setSteps(newSteps.map(step => ({ ...step, disableBeacon: true })));
        setStepIndex(0);
        setTimeout(() => setRun(true), 300); 
    }, []);

    const stopTour = useCallback(() => {
        setRun(false);
        setStepIndex(0);
    }, []);

    const handleJoyrideCallback = (data: CallBackProps) => {
        const { status, type, action, index, step } = data;

        if (type === EVENTS.TARGET_NOT_FOUND) {
            setStepIndex(index + (action === 'prev' ? -1 : 1));
        } else if (type === EVENTS.STEP_AFTER) {
            setStepIndex(index + (action === 'prev' ? -1 : 1));
        } else if ([STATUS.FINISHED, STATUS.SKIPPED].includes(status as any)) {
            setRun(false);
            setStepIndex(0);
            
            if (window.location.pathname.includes('/tenants')) {
                localStorage.setItem('hive_tour_tenants_completed', 'true');
            } else if (window.location.pathname.includes('/security')) {
                localStorage.setItem('hive_tour_security_completed', 'true');
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
                    tooltipComponent={CustomTooltip} 
                    // 🚀 THE FIX: Cast the entire object to 'any'
                    floaterProps={{ 
                        disableTransform: true, 
                        hideArrow: true,
                        offset: 15,
                        styles: { popper: { zIndex: 100000 } }
                    } as any}
                    styles={{
                        options: { overlayColor: 'rgba(0, 0, 0, 0.75)', zIndex: 100000 },
                        spotlight: { borderRadius: '1.2rem' }
                    }}
                />
            )}
        </TourContext.Provider>
    );
};