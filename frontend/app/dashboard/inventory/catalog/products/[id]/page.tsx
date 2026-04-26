import ProductDetailPage from "@/modules/inventory/pages/product-detail-page";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const productId = Number(id);

  if (!Number.isFinite(productId) || productId <= 0) {
    return null;
  }

  return <ProductDetailPage productId={productId} />;
}
