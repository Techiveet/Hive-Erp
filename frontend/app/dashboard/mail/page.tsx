"use client";

import React from 'react';
import MailLayout from '@/components/mail/mail-layout';

export default function MailPage() {
  return (
    <div className="h-[calc(100vh-14rem)] min-h-[600px] w-full bg-background flex flex-col overflow-hidden pt-0 rounded-xl">
      <div className="w-full h-full max-w-none overflow-hidden"> 
        <MailLayout />
      </div>
    </div>
  );
}
