"use client";

import { useEffect } from "react";

export default function CustomCursor() {
  useEffect(() => {
    const outer = document.querySelector(".cursor-outer") as HTMLElement;
    const inner = document.querySelector(".cursor-inner") as HTMLElement;

    // Movement Logic
    const moveCursor = (e: MouseEvent) => {
      if (inner) {
        inner.style.left = `${e.clientX}px`;
        inner.style.top = `${e.clientY}px`;
      }
      if (outer) {
        // Use standard style updates for max performance
        outer.style.left = `${e.clientX}px`;
        outer.style.top = `${e.clientY}px`;
      }
    };

    // Hover State Logic
    const addHover = () => document.body.classList.add("hovering");
    const removeHover = () => document.body.classList.remove("hovering");

    window.addEventListener("mousemove", moveCursor);
    
    // Attach listeners to interactive elements
    const targets = document.querySelectorAll("a, button, .hover-target");
    targets.forEach((el) => {
      el.addEventListener("mouseenter", addHover);
      el.addEventListener("mouseleave", removeHover);
    });

    return () => {
      window.removeEventListener("mousemove", moveCursor);
      targets.forEach((el) => {
        el.removeEventListener("mouseenter", addHover);
        el.removeEventListener("mouseleave", removeHover);
      });
    };
  }, []);

  return (
    <>
      <div className="cursor-outer"></div>
      <div className="cursor-inner"></div>
    </>
  );
}