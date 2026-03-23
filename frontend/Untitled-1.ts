// frontend/src/app/[tenant]/page.tsx
import React from 'react';
import RetryButton from '@/components/RetryButton';

interface DashboardData {
    company: string;
    plan: string;
    stats: {
        revenue: string;
        active_users: number;
        pending_invoices: number;
    };
    recent_activity: { id: number; action: string; time: string }[];
}

export default async function TenantDashboard({ 
    params 
}: { 
    params: Promise<{ tenant: string }> 
}) {
    const { tenant } = await params;
    
    // 🚀 THE FIX: Fetch directly from the alias domain via internal port 8000
    const backendUrl = `http://${tenant}.localhost:8000/api/dashboard`;

    try {
        const res = await fetch(backendUrl, { 
            cache: 'no-store',
            headers: {
                // Next.js natively sends the correct Host header now!
                'Accept': 'application/json',
            }
        });

        // 🚨 Keep our error-catcher so we can always see Laravel's response offline
        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${errorText.substring(0, 150)}...`);
        }

        const data: DashboardData = await res.json();

        return (
            <div className="p-8 max-w-6xl mx-auto font-sans bg-gray-50 min-h-screen text-slate-900">
                <header className="mb-8 border-b border-slate-200 pb-6 flex justify-between items-center">
                    <div>
                        <h1 className="text-4xl font-black tracking-tight">{data.company}</h1>
                        <p className="text-slate-500 mt-1 uppercase text-xs font-bold tracking-widest">
                            Hive Node: {tenant}
                        </p>
                    </div>
                    <span className="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-xs font-bold uppercase shadow-lg">
                        {data.plan} plan
                    </span>
                </header>

                {/* Metrics */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div className="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm">
                        <p className="text-xs text-slate-400 uppercase font-bold tracking-wider">Revenue</p>
                        <p className="text-3xl font-black mt-2 text-slate-900">{data.stats.revenue}</p>
                    </div>
                    <div className="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm">
                        <p className="text-xs text-slate-400 uppercase font-bold tracking-wider">Active Staff</p>
                        <p className="text-3xl font-black mt-2 text-slate-900">{data.stats.active_users}</p>
                    </div>
                    <div className="p-6 bg-white border border-slate-100 rounded-2xl shadow-sm">
                        <p className="text-xs text-slate-400 uppercase font-bold tracking-wider">Pending Tasks</p>
                        <p className="text-3xl font-black mt-2 text-slate-900">{data.stats.pending_invoices}</p>
                    </div>
                </div>

                {/* Activity List */}
                <div className="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                        <h2 className="text-lg font-bold text-slate-800">Recent Load Activity</h2>
                    </div>
                    <ul className="divide-y divide-slate-100">
                        {data.recent_activity.map((activity) => (
                            <li key={activity.id} className="px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                <span className="font-semibold text-slate-700">{activity.action}</span>
                                <span className="text-xs font-medium text-slate-400">{activity.time}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        );

    } catch (error: any) {
        return (
            <div className="flex flex-col items-center justify-center min-h-screen text-center p-6 bg-white">
                <div className="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mb-4 text-2xl font-bold">!</div>
                <h1 className="text-2xl font-black text-slate-900">Node Connection Failed</h1>
                <p className="text-slate-500 mt-2 max-w-xl bg-slate-100 p-4 rounded-lg font-mono text-sm break-words">
                    {error.message}
                </p>
                <RetryButton />
            </div>
        );
    }
}