import React, { useEffect, useId, useRef } from 'react';

/**
 * Modal dialog.
 *  - Phones (< 640px): full-screen sheet; title bar stays put, the body scrolls.
 *  - sm and up: centred dialog, at most 90% of the visible height, body scrolls.
 * Escape and the backdrop close it, the page behind doesn't scroll, focus moves
 * into the dialog and back to whatever opened it. It sits below the WordPress
 * admin bar (--hm-top-offset, see frontend.css).
 */
const Modal = ({
  isOpen,
  onClose,
  title,
  children,
  size = 'md',
  showCloseButton = true
}) => {
  const panelRef = useRef(null);
  const titleId = useId();

  useEffect(() => {
    if (!isOpen) return undefined;
    const previouslyFocused = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const onKey = (e) => {
      if (e.key === 'Escape') onClose && onClose();
    };
    document.addEventListener('keydown', onKey);

    // Focus the first focusable element (the close button or first field)
    const panel = panelRef.current;
    const first = panel && panel.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    (first || panel) && (first || panel).focus({ preventScroll: true });

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', onKey);
      if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
        previouslyFocused.focus({ preventScroll: true });
      }
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const sizeClasses = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
    '2xl': 'sm:max-w-6xl'
  };

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  return (
    <div
      className="fixed inset-x-0 bottom-0 top-[var(--hm-top-offset,0px)] z-[60] flex sm:items-center sm:justify-center sm:p-6 bg-gray-500/75"
      onClick={handleBackdropClick}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        tabIndex={-1}
        className={`flex flex-col w-full h-full sm:h-auto sm:max-h-[90dvh] bg-white text-left shadow-xl sm:rounded-lg overflow-hidden focus:outline-none ${sizeClasses[size] || sizeClasses.md}`}
      >
        {/* Header */}
        {(title || showCloseButton) && (
          <div className="flex items-center justify-between gap-3 flex-shrink-0 bg-white pl-4 pr-2 py-2 sm:pl-6 sm:pr-3 sm:py-3 border-b border-gray-200">
            {title ? (
              <h3 className="text-lg leading-6 font-medium text-gray-900 text-balance min-w-0" id={titleId}>
                {title}
              </h3>
            ) : <span />}
            {showCloseButton && (
              <button
                type="button"
                className="inline-flex items-center justify-center size-11 flex-shrink-0 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                onClick={onClose}
              >
                <span className="sr-only">Close</span>
                <svg className="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>
        )}

        {/* Body (scrolls inside the dialog) */}
        <div className="flex-1 overflow-y-auto overscroll-contain bg-white px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:p-6">
          {children}
        </div>
      </div>
    </div>
  );
};

export default Modal;
