import { redirect } from "next/navigation";

// The Document Converter is a sub-mode of the PDF & Documents page.
export default function DocumentConverterPage() {
  redirect("/dashboard/tools/converters/pdf");
}
