"use client";

import React from "react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  Legend,
} from "recharts";
import { Project, TaskPriority } from "../types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { motion } from "framer-motion";
import { ProjectTrendChart } from "./ProjectTrendChart";

interface DashboardChartsProps {
  projects: Project[];
}

const STATUS_COLORS: Record<string, string> = {
  planning: "hsl(var(--warning))",
  active: "hsl(var(--success))",
  on_hold: "hsl(var(--destructive))",
  completed: "hsl(var(--primary))",
  archived: "hsl(var(--muted))",
};

const PRIORITY_COLORS: Record<TaskPriority, string> = {
  urgent: "hsl(var(--destructive))",
  high: "hsl(var(--warning))",
  medium: "hsl(var(--primary))",
  low: "hsl(var(--success))",
};

export function DashboardCharts({ projects }: DashboardChartsProps) {
  // Data for Project Progress Bar Chart
  const progressData = projects
    .slice(0, 6)
    .map((p) => ({
      name: p.name,
      progress: p.progress || 0,
      fullMark: 100,
    }))
    .sort((a, b) => b.progress - a.progress);

  // Data for Status Distribution Donut Chart
  const statusCounts = projects.reduce((acc, p) => {
    acc[p.status] = (acc[p.status] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);

  const statusData = Object.entries(statusCounts).map(([status, count]) => ({
    name: status.replace("_", " ").toUpperCase(),
    value: count,
    status,
  }));

  // Data for Priority Distribution
  const priorityCounts = projects.reduce((acc, p) => {
    acc[p.priority] = (acc[p.priority] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);

  const priorityData = Object.entries(priorityCounts).map(([priority, count]) => ({
    name: priority.toUpperCase(),
    value: count,
    priority: priority as TaskPriority,
  }));

  // Data for Resource Allocation (Manager Workload)
  const managerCounts = projects.reduce((acc, p) => {
    const managerName = p.project_manager?.name || "Unassigned";
    acc[managerName] = (acc[managerName] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);

  const resourceData = Object.entries(managerCounts)
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 5);

  // Data for Timeline Health (Risk Assessment)
  const timelineHealth = projects.reduce((acc, p) => {
    if (p.status === 'completed') {
      acc.onTrack++;
      return acc;
    }

    const dueDate = p.end_date ? new Date(p.end_date) : null;
    const now = new Date();
    
    if (dueDate && dueDate < now) {
      acc.overdue++;
    } else if (dueDate) {
      const diffDays = Math.ceil((dueDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
      if (diffDays <= 3 && (p.progress || 0) < 80) {
        acc.atRisk++;
      } else {
        acc.onTrack++;
      }
    } else {
      acc.onTrack++;
    }
    return acc;
  }, { onTrack: 0, atRisk: 0, overdue: 0 });

  const timelineData = [
    { name: "ON TRACK", value: timelineHealth.onTrack, color: "hsl(var(--success))" },
    { name: "AT RISK", value: timelineHealth.atRisk, color: "hsl(var(--warning))" },
    { name: "OVERDUE", value: timelineHealth.overdue, color: "hsl(var(--destructive))" },
  ].filter(d => d.value > 0);

  const CustomTooltip = ({ active, payload, label }: any) => {
    if (active && payload && payload.length) {
      return (
        <div className="bg-background/80 backdrop-blur-md border border-border p-3 rounded-xl shadow-xl">
          <p className="text-sm font-bold">{label || payload[0].name}</p>
          <p className="text-xs text-primary font-mono">
            {payload[0].name === "progress" ? `Progress: ${payload[0].value}%` : `Count: ${payload[0].value}`}
          </p>
        </div>
      );
    }
    return null;
  };

  return (
    <div className="space-y-6">
      {/* Primary Analytics Row */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <ProjectTrendChart projects={projects} />
        
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: 0.1 }}
          className="xl:col-span-2"
        >
          <Card className="bg-card/30 backdrop-blur-md border-muted-foreground/10 h-full rounded-[2rem]">
            <CardHeader className="pt-8 px-8">
              <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                Manager Allocation
              </CardTitle>
            </CardHeader>
            <CardContent className="h-[300px] w-full pb-8 px-8">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={resourceData} margin={{ top: 20, right: 30, left: 20, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} opacity={0.05} />
                  <XAxis 
                    dataKey="name" 
                    axisLine={false} 
                    tickLine={false} 
                    tick={{ fontSize: 11, fill: "hsl(var(--muted-foreground))" }} 
                  />
                  <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 11, fill: "hsl(var(--muted-foreground))" }} />
                  <Tooltip content={<CustomTooltip />} cursor={{ fill: "hsl(var(--primary)/0.05)" }} />
                  <Bar
                    dataKey="count"
                    fill="hsl(var(--primary))"
                    radius={[6, 6, 0, 0]}
                    barSize={40}
                    animationDuration={1500}
                  />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </motion.div>
      </div>

      {/* Secondary Distribution Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <Card className="bg-card/30 backdrop-blur-md border-muted-foreground/10 h-[400px] rounded-[2rem]">
            <CardHeader className="pt-8 px-8">
              <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                Timeline Health
              </CardTitle>
            </CardHeader>
            <CardContent className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={timelineData}
                    cx="50%"
                    cy="45%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={8}
                    dataKey="value"
                    animationDuration={1500}
                  >
                    {timelineData.map((entry, index) => (
                      <Cell
                        key={`timeline-cell-${index}`}
                        fill={entry.color}
                        stroke="none"
                      />
                    ))}
                  </Pie>
                  <Tooltip content={<CustomTooltip />} />
                  <Legend
                    verticalAlign="bottom"
                    align="center"
                    layout="horizontal"
                    iconType="circle"
                    iconSize={8}
                    formatter={(value) => <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground ml-1">{value}</span>}
                  />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <Card className="bg-card/30 backdrop-blur-md border-muted-foreground/10 h-[400px] rounded-[2rem]">
            <CardHeader className="pt-8 px-8">
              <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                Project Completion (%)
              </CardTitle>
            </CardHeader>
            <CardContent className="h-[300px] w-full pb-8 px-8">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={progressData} layout="vertical" margin={{ left: 20, right: 30 }}>
                  <CartesianGrid strokeDasharray="3 3" horizontal={true} vertical={false} opacity={0.1} />
                  <XAxis type="number" domain={[0, 100]} hide />
                  <YAxis
                    dataKey="name"
                    type="category"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fontSize: 11, fill: "hsl(var(--muted-foreground))" }}
                    width={80}
                  />
                  <Tooltip content={<CustomTooltip />} cursor={{ fill: "hsl(var(--primary)/0.05)" }} />
                  <Bar
                    dataKey="progress"
                    fill="hsl(var(--primary))"
                    radius={[0, 4, 4, 0]}
                    barSize={16}
                    animationDuration={1500}
                  />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
        >
          <Card className="bg-card/30 backdrop-blur-md border-muted-foreground/10 h-[400px] rounded-[2rem]">
            <CardHeader className="pt-8 px-8">
              <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                Status Mix
              </CardTitle>
            </CardHeader>
            <CardContent className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={statusData}
                    cx="50%"
                    cy="45%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={8}
                    dataKey="value"
                    animationDuration={1500}
                  >
                    {statusData.map((entry, index) => (
                      <Cell
                        key={`cell-${index}`}
                        fill={STATUS_COLORS[entry.status] || "hsl(var(--primary))"}
                        stroke="none"
                      />
                    ))}
                  </Pie>
                  <Tooltip content={<CustomTooltip />} />
                  <Legend
                    verticalAlign="bottom"
                    align="center"
                    layout="horizontal"
                    iconType="circle"
                    iconSize={8}
                    formatter={(value) => <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground ml-1">{value}</span>}
                  />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
        >
          <Card className="bg-card/30 backdrop-blur-md border-muted-foreground/10 h-[400px] rounded-[2rem]">
            <CardHeader className="pt-8 px-8">
              <CardTitle className="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                Priority Distribution
              </CardTitle>
            </CardHeader>
            <CardContent className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={priorityData}
                    cx="50%"
                    cy="45%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={8}
                    dataKey="value"
                    animationDuration={1500}
                  >
                    {priorityData.map((entry, index) => (
                      <Cell
                        key={`priority-cell-${index}`}
                        fill={PRIORITY_COLORS[entry.priority] || "hsl(var(--primary))"}
                        stroke="none"
                      />
                    ))}
                  </Pie>
                  <Tooltip content={<CustomTooltip />} />
                  <Legend
                    verticalAlign="bottom"
                    align="center"
                    layout="horizontal"
                    iconType="circle"
                    iconSize={8}
                    formatter={(value) => <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground ml-1">{value}</span>}
                  />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </motion.div>
      </div>
    </div>
  );
}
