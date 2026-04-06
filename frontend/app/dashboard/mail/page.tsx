"use client";

import React from 'react';
import MailLayout from '@/components/mail/mail-layout';

export default function MailPage() {
  return (
    <div className="h-[calc(100vh-4rem)] p-4 lg:p-6 w-full flex flex-col items-center justify-center">
      <div className="w-full max-w-[1400px] h-full"> 
        <MailLayout />
      </div>
    </div>
  );
}
