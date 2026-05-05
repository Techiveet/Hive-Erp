"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { 
  Users, 
  Search, 
  CheckCircle2, 
  Clock, 
  UserPlus, 
  ArrowLeft,
  Loader2,
  Filter,
  UserCheck
} from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { toast } from "sonner";
import { fetchGuestList, guestCheckIn, fetchHospitalityOverview } from "../api";
import { HospitalityGuestList } from "../types";
import { motion, AnimatePresence } from "framer-motion";
import { cn } from "@/lib/utils";

export default function DoorManagementPage() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState("");
  const [filter, setFilter] = useState<"all" | "pending" | "arrived">("all");

  const { data: overview } = useQuery({
    queryKey: ["hospitality", "overview"],
    queryFn: fetchHospitalityOverview,
  });

  const { data: guests, isLoading } = useQuery({
    queryKey: ["hospitality", "guest-list"],
    queryFn: fetchGuestList,
  });

  const checkInMutation = useMutation({
    mutationFn: ({ id, count }: { id: number; count: number }) => 
      guestCheckIn(id, count),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["hospitality", "guest-list"] });
      toast.success("Guest checked in successfully");
    },
    onError: (error: any) => {
      toast.error(error.message || "Failed to check in guest");
    }
  });

  const filteredGuests = guests?.filter((guest: HospitalityGuestList) => {
    const matchesSearch = guest.guest_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                        guest.promoter?.name.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesFilter = filter === "all" || guest.status === filter;
    return matchesSearch && matchesFilter;
  });

  if (overview && overview.business_type !== "nightclub") {
    // Optionally redirect or show a message if not a nightclub
  }

  return (
    <div className="space-y-8 p-6 pb-20">
      <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div className="space-y-2">
          <div className="flex items-center gap-3">
            <Button asChild variant="ghost" size="sm" className="rounded-full">
              <Link href="/dashboard/hospitality">
                <ArrowLeft className="mr-1 h-4 w-4" />
                Back
              </Link>
            </Button>
            <h1 className="text-3xl font-black tracking-tight">Door & Guest List</h1>
          </div>
          <p className="text-muted-foreground font-medium">
            Manage entries, check-in guests, and track promoter performance.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Button className="rounded-full px-6 font-black uppercase tracking-widest gap-2">
            <UserPlus className="h-4 w-4" />
            Walk-in Entry
          </Button>
        </div>
      </div>

      <div className="grid gap-8 lg:grid-cols-4">
        {/* Stats Column */}
        <div className="space-y-4">
          <div className="rounded-[2rem] border border-border/40 bg-card/30 backdrop-blur-md p-6">
            <h3 className="text-xs font-black uppercase tracking-widest text-muted-foreground mb-4">Capacity Status</h3>
            <div className="space-y-4">
              <div className="flex justify-between items-end">
                <p className="text-4xl font-black">{guests?.filter((g: HospitalityGuestList) => g.status === 'arrived').reduce((acc: number, g: HospitalityGuestList) => acc + g.actual_arrived_count, 0) || 0}</p>
                <p className="text-xs font-bold opacity-50 mb-1">In Venue</p>
              </div>
              <div className="h-2 w-full bg-white/5 rounded-full overflow-hidden">
                <div className="h-full bg-primary w-[45%]" />
              </div>
              <p className="text-[10px] font-bold text-muted-foreground uppercase tracking-widest">45% of nightly capacity</p>
            </div>
          </div>

          <div className="rounded-[2rem] border border-border/40 bg-card/30 backdrop-blur-md p-6">
            <h3 className="text-xs font-black uppercase tracking-widest text-muted-foreground mb-4">Quick Filters</h3>
            <div className="grid grid-cols-1 gap-2">
              {(["all", "pending", "arrived"] as const).map((f) => (
                <Button
                  key={f}
                  variant={filter === f ? "default" : "ghost"}
                  onClick={() => setFilter(f)}
                  className="justify-start rounded-xl font-bold uppercase text-[10px] tracking-widest h-10"
                >
                  <Filter className="mr-2 h-3 w-3" />
                  {f}
                </Button>
              ))}
            </div>
          </div>
        </div>

        {/* Guest List Column */}
        <div className="lg:col-span-3 space-y-6">
          <div className="relative">
            <Search className="absolute left-6 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
            <Input
              placeholder="Search by name or promoter..."
              className="pl-14 h-16 rounded-[2rem] bg-card/30 backdrop-blur-md border-border/40 text-lg font-medium focus-visible:ring-primary/20"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          {isLoading ? (
            <div className="flex h-64 items-center justify-center">
              <Loader2 className="h-8 w-8 animate-spin text-primary opacity-20" />
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              <AnimatePresence mode="popLayout">
                {filteredGuests?.map((guest: HospitalityGuestList) => (
                  <motion.div
                    key={guest.id}
                    layout
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 0.9 }}
                    className={cn(
                      "rounded-[2.5rem] p-6 border transition-all duration-300",
                      guest.status === 'arrived' 
                        ? "bg-primary/5 border-primary/20" 
                        : "bg-card/30 backdrop-blur-md border-border/40 hover:border-primary/30"
                    )}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div className="space-y-3">
                        <div>
                          <h4 className="text-xl font-black tracking-tight">{guest.guest_name}</h4>
                          {guest.promoter && (
                            <p className="text-[10px] font-bold text-primary uppercase tracking-widest mt-1 flex items-center gap-1.5">
                              <UserCheck className="h-3 w-3" />
                              Promoter: {guest.promoter.name}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge variant="outline" className="rounded-full py-0.5 px-3 h-6 text-[10px] font-black uppercase border-border/60">
                            {guest.expected_party_size} Guests
                          </Badge>
                          <Badge className={cn(
                            "rounded-full py-0.5 px-3 h-6 text-[10px] font-black uppercase",
                            guest.status === 'arrived' ? "bg-emerald-500" : "bg-amber-500"
                          )}>
                            {guest.status}
                          </Badge>
                        </div>
                      </div>

                      {guest.status !== 'arrived' ? (
                        <Button 
                          onClick={() => checkInMutation.mutate({ id: guest.id, count: guest.expected_party_size })}
                          disabled={checkInMutation.isPending}
                          className="rounded-2xl h-12 w-12 p-0 shadow-lg"
                        >
                          <CheckCircle2 className="h-6 w-6" />
                        </Button>
                      ) : (
                        <div className="h-12 w-12 rounded-2xl bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                          <CheckCircle2 className="h-6 w-6" />
                        </div>
                      )}
                    </div>

                    {guest.status === 'arrived' && (
                      <div className="mt-4 pt-4 border-t border-primary/10 flex items-center gap-2 text-[10px] font-black uppercase text-muted-foreground">
                        <Clock className="h-3 w-3" />
                        Arrived with {guest.actual_arrived_count} pax
                      </div>
                    )}
                  </motion.div>
                ))}
              </AnimatePresence>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
