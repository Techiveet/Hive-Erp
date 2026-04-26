"use client";

import React, { useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import MailLayout from '@/components/mail/mail-layout';
import { useMailStore } from '@/store/mail-store';

export default function MailPage() {
  const searchParams = useSearchParams();
  const selectMail = useMailStore((state) => state.selectMail);
  const setActiveFolder = useMailStore((state) => state.setActiveFolder);

  useEffect(() => {
    const mailId = searchParams.get('id');
    const parsedMailId = Number(mailId);

    if (!mailId || Number.isNaN(parsedMailId)) {
      return;
    }

    setActiveFolder('inbox');
    selectMail(parsedMailId);
  }, [searchParams, selectMail, setActiveFolder]);

  return (
    <div className="h-[calc(100vh-14rem)] min-h-[600px] w-full bg-background flex flex-col overflow-hidden pt-0 rounded-xl">
      <div className="w-full h-full max-w-none overflow-hidden"> 
        <MailLayout />
      </div>
    </div>
  );
}
