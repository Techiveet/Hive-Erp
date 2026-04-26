"use client";

import React, { useRef, useState } from "react";
import SignatureCanvas from "react-signature-canvas";
import { Button } from "./button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "./dialog";
import { Eraser, PenTool } from "lucide-react";

interface SignaturePadProps {
  onSave: (base64Image: string) => void;
  trigger?: React.ReactNode;
}

export function SignaturePad({ onSave, trigger }: SignaturePadProps) {
  const [isOpen, setIsOpen] = useState(false);
  const padRef = useRef<SignatureCanvas>(null);

  const handleClear = () => {
    padRef.current?.clear();
  };

  const handleSave = () => {
    if (padRef.current?.isEmpty()) {
      return;
    }
    const dataUrl = padRef.current?.getTrimmedCanvas().toDataURL("image/png");
    if (dataUrl) {
      onSave(dataUrl);
      setIsOpen(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={setIsOpen}>
      <DialogTrigger asChild>
        {trigger || (
          <Button variant="outline" size="sm">
            <PenTool className="h-4 w-4 mr-2" />
            Add Signature
          </Button>
        )}
      </DialogTrigger>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Draw Signature</DialogTitle>
          <DialogDescription>
             Use your mouse or touch screen to draw your signature below.
          </DialogDescription>
        </DialogHeader>

        <div className="border rounded-md bg-muted/20 relative cursor-crosshair overflow-hidden touch-none">
          <SignatureCanvas
            ref={padRef}
            penColor="black"
            canvasProps={{
              className: "w-full h-[200px] signature-canvas",
            }}
          />
        </div>

        <DialogFooter className="flex items-center justify-between mt-4">
          <Button variant="destructive" size="sm" onClick={handleClear} type="button">
            <Eraser className="h-4 w-4 mr-2" />
            Clear
          </Button>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => setIsOpen(false)} type="button">
              Cancel
            </Button>
            <Button size="sm" onClick={handleSave} type="button">
              Insert Signature
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
