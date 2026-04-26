"use client";

import React from 'react';

export default function RetryButton() {
    return (
        <button 
            onClick={() => window.location.reload()}
            className="mt-6 px-6 py-2 bg-gray-900 text-white rounded-lg font-bold hover:bg-black transition-colors"
        >
            Retry Connection
        </button>
    );
}