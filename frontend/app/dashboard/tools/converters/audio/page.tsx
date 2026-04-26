import { redirect } from "next/navigation";

// Audio converter reuses the video/audio page with mode=audio
export default function AudioConverterPage() {
  redirect("/dashboard/tools/converters/video");
}
