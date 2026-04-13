import ProductDetailPage from "@/modules/inventory/pages/product-detail-page";

export default function Page({ params }: { params: { id: string } }) {
  const productId = Number(params.id);

  if (!Number.isFinite(productId) || productId <= 0) {
    return null;
  }

  return <ProductDetailPage productId={productId} />;
}
