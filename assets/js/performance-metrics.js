(function () {
  'use strict';

  if (!('performance' in window)) return;

  const metrics = window.__zsemMetrics = window.__zsemMetrics || {
    cls: 0,
    inp: 0,
    marks: []
  };

  performance.mark('zsem:app-script-start');

  window.addEventListener('DOMContentLoaded', () => {
    performance.mark('zsem:dom-ready');
    try {
      performance.measure('zsem:dom-ready-time', 'navigationStart', 'zsem:dom-ready');
    } catch (error) {}
  }, { once: true });

  window.addEventListener('load', () => {
    performance.mark('zsem:window-load');
    try {
      performance.measure('zsem:load-time', 'navigationStart', 'zsem:window-load');
    } catch (error) {}
  }, { once: true });

  if ('PerformanceObserver' in window) {
    try {
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.hadRecentInput) metrics.cls += entry.value || 0;
        }
      }).observe({ type: 'layout-shift', buffered: true });
    } catch (error) {}

    try {
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          const latency = Math.max(0, (entry.processingStart || 0) - entry.startTime);
          metrics.inp = Math.max(metrics.inp, latency);
        }
      }).observe({ type: 'event', buffered: true, durationThreshold: 40 });
    } catch (error) {}
  }

  metrics.marks.push('zsem:app-script-start');
})();
