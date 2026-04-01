import { FullScreenPlaceholder } from "@/components/ui/loading-states";

export default function RootLoading() {
  return (
    <FullScreenPlaceholder
      label="Preparing HIVE.OS"
      detail="Booting the application shell and synchronizing your environment."
    />
  );
}
