"use client";

import { useEffect } from "react";

export default function CustomCursor() {
  useEffect(() => {
    const cursor = document.querySelector(".cursor") as HTMLElement | null;

    if (!cursor) return;

    let rafId = 0;
    let latestX = window.innerWidth / 2;
    let latestY = window.innerHeight / 2;
    let frameQueued = false;

    const renderCursor = () => {
      frameQueued = false;
      cursor.style.transform = `translate3d(${latestX}px, ${latestY}px, 0)`;
    };

    const queueRender = () => {
      if (frameQueued) return;
      frameQueued = true;
      rafId = window.requestAnimationFrame(renderCursor);
    };

    const onMouseMove = (e: MouseEvent) => {
      latestX = e.clientX;
      latestY = e.clientY;
      queueRender();
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

    const onMouseLeave = () => {
      document.body.classList.remove("cursor-hovering", "cursor-clicking");
    };

    queueRender();

    window.addEventListener("mousemove", onMouseMove, { passive: true });
    window.addEventListener("mouseover", onMouseOver, { passive: true });
    window.addEventListener("mousedown", onMouseDown, { passive: true });
    window.addEventListener("mouseup", onMouseUp, { passive: true });
    window.addEventListener("mouseleave", onMouseLeave, { passive: true });

    return () => {
      window.cancelAnimationFrame(rafId);
      window.removeEventListener("mousemove", onMouseMove);
      window.removeEventListener("mouseover", onMouseOver);
      window.removeEventListener("mousedown", onMouseDown);
      window.removeEventListener("mouseup", onMouseUp);
      window.removeEventListener("mouseleave", onMouseLeave);

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
