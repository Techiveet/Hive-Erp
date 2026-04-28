"use client";

import ProjectDetailPage from "@/modules/projectmanagement/pages/ProjectDetailPage";
import { use } from "react";

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function Page({ params }: PageProps) {
  const resolvedParams = use(params);
  return <ProjectDetailPage id={resolvedParams.id} />;
}
