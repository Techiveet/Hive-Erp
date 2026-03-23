"use client";

import { useEffect } from "react";

export default function CustomCursor() {
  useEffect(() => {
    const cursor = document.querySelector(".cursor") as HTMLElement | null;

    if (!cursor) return;

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let currentX = mouseX;
    let currentY = mouseY;
    let rafId = 0;

    const onMouseMove = (e: MouseEvent) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
    };

    const onMouseOver = (e: MouseEvent) => {
      const target = e.target as HTMLElement | null;

      if (
        target?.closest(
          'a, button, input, select, textarea, label, [role="button"], .hover-target'
        )
      ) {
        document.body.classList.add("cursor-hovering");
      } else {
        document.body.classList.remove("cursor-hovering");
      }
    };

    const onMouseDown = () => {
      document.body.classList.add("cursor-clicking");
    };

    const onMouseUp = () => {
      document.body.classList.remove("cursor-clicking");
    };

    const render = () => {
      const dx = mouseX - currentX;
      const dy = mouseY - currentY;

      currentX += dx * 0.55;
      currentY += dy * 0.55;

      if (Math.abs(dx) < 0.01) currentX = mouseX;
      if (Math.abs(dy) < 0.01) currentY = mouseY;

      cursor.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;

      rafId = window.requestAnimationFrame(render);
    };

    rafId = window.requestAnimationFrame(render);

    window.addEventListener("mousemove", onMouseMove, { passive: true });
    window.addEventListener("mouseover", onMouseOver, { passive: true });
    window.addEventListener("mousedown", onMouseDown, { passive: true });
    window.addEventListener("mouseup", onMouseUp, { passive: true });

    return () => {
      window.cancelAnimationFrame(rafId);
      window.removeEventListener("mousemove", onMouseMove);
      window.removeEventListener("mouseover", onMouseOver);
      window.removeEventListener("mousedown", onMouseDown);
      window.removeEventListener("mouseup", onMouseUp);

      document.body.classList.remove("cursor-hovering", "cursor-clicking");
    };
  }, []);

  return (
    <div className="cursor" aria-hidden="true">
      <div className="cursor-outer" />
      <div className="cursor-inner" />
    </div>
  );
}