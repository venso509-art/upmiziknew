import React, { useEffect, useRef, useState } from 'react';

interface ScrollRevealProps {
  children: React.ReactNode;
  className?: string;
  delayMs?: number;
  direction?: 'up' | 'down' | 'left' | 'right' | 'none';
  distancePx?: number;
  durationMs?: number;
  once?: boolean;
}

export const ScrollReveal: React.FC<ScrollRevealProps> = ({
  children,
  className = '',
  delayMs = 0,
  direction = 'up',
  distancePx = 24,
  durationMs = 700,
  once = true
}) => {
  const [isVisible, setIsVisible] = useState(false);
  const elementRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const element = elementRef.current;
    if (!element) return;

    // Check if user prefers reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
      setIsVisible(true);
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsVisible(true);
            if (once) {
              observer.unobserve(entry.target);
            }
          } else if (!once) {
            setIsVisible(false);
          }
        });
      },
      {
        threshold: 0.08,
        rootMargin: '0px 0px -40px 0px'
      }
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [once]);

  const getTransform = () => {
    if (isVisible) return 'translate3d(0, 0, 0) scale(1)';
    switch (direction) {
      case 'up':
        return `translate3d(0, ${distancePx}px, 0) scale(0.988)`;
      case 'down':
        return `translate3d(0, -${distancePx}px, 0) scale(0.988)`;
      case 'left':
        return `translate3d(${distancePx}px, 0, 0) scale(0.988)`;
      case 'right':
        return `translate3d(-${distancePx}px, 0, 0) scale(0.988)`;
      case 'none':
      default:
        return 'translate3d(0, 0, 0) scale(0.988)';
    }
  };

  return (
    <div
      ref={elementRef}
      className={`will-change-[opacity,transform] ${className}`}
      style={{
        opacity: isVisible ? 1 : 0,
        transform: getTransform(),
        transitionProperty: 'opacity, transform',
        transitionDuration: `${durationMs}ms`,
        transitionTimingFunction: 'cubic-bezier(0.16, 1, 0.3, 1)',
        transitionDelay: `${delayMs}ms`
      }}
    >
      {children}
    </div>
  );
};

export default ScrollReveal;
