// Minimal virtualizer with optional @tanstack/virtual-core integration.
// Falls back to a simple fixed-row-height windowed renderer when tanstack is unavailable.
// Designed for Tailwind + Alpine usage without a bundler.

export async function createVirtualizer(options) {
  const {
    scrollContainer,      // HTMLElement that scrolls
    getCount,             // () => number
    estimateSize = 48,    // px per row (fixed height recommended)
    overscan = 12,        // number of rows before/after viewport
    onChange,             // ({ items, totalSize, scrollTop }) => void
  } = options;

  if (!scrollContainer || typeof getCount !== 'function' || typeof onChange !== 'function') {
    throw new Error('createVirtualizer: invalid options');
  }

  // Try to load tanstack virtualizer from CDN
  try {
    const mod = await import('https://esm.sh/@tanstack/virtual-core@3.0.0?target=es2020');
    const { Virtualizer } = mod;
    const v = new Virtualizer({
      count: getCount(),
      getScrollElement: () => scrollContainer,
      estimateSize: () => estimateSize,
      overscan,
    });

    const handle = () => {
      // Update count dynamically in case pages append
      v.setOptions(prev => ({ ...prev, count: getCount() }));
      const ranges = v.getVirtualItems();
      const totalSize = v.getTotalSize();
      onChange({
        items: ranges.map(r => ({
          index: r.index,
          start: r.start,
          size: r.size,
          end: r.end,
          key: r.key ?? r.index,
        })),
        totalSize,
        scrollTop: scrollContainer.scrollTop,
      });
    };

    const onScroll = () => handle();
    scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    const ro = new ResizeObserver(() => handle());
    ro.observe(scrollContainer);
    handle();
    return {
      destroy() {
        scrollContainer.removeEventListener('scroll', onScroll);
        ro.disconnect();
      },
      refresh: handle,
    };
  } catch (e) {
    // Fallback: simple fixed-height virtualizer
    const state = { width: 0, height: 0, scrollTop: 0 };
    const getViewport = () => {
      state.width = scrollContainer.clientWidth;
      state.height = scrollContainer.clientHeight;
      state.scrollTop = scrollContainer.scrollTop;
    };
    const compute = () => {
      const count = getCount();
      if (count <= 0) {
        onChange({ items: [], totalSize: 0, scrollTop: state.scrollTop });
        return;
      }
      const totalSize = count * estimateSize;
      const startIndex = Math.max(0, Math.floor(state.scrollTop / estimateSize) - overscan);
      const endIndex = Math.min(
        count - 1,
        Math.ceil((state.scrollTop + state.height) / estimateSize) + overscan
      );
      const items = [];
      for (let i = startIndex; i <= endIndex; i++) {
        items.push({
          index: i,
          start: i * estimateSize,
          size: estimateSize,
          end: (i + 1) * estimateSize,
          key: i,
        });
      }
      onChange({ items, totalSize, scrollTop: state.scrollTop });
    };
    const handle = () => {
      getViewport();
      compute();
    };
    const onScroll = () => { state.scrollTop = scrollContainer.scrollTop; compute(); };
    scrollContainer.addEventListener('scroll', onScroll, { passive: true });
    const ro = new ResizeObserver(handle);
    ro.observe(scrollContainer);
    handle();
    return {
      destroy() {
        scrollContainer.removeEventListener('scroll', onScroll);
        ro.disconnect();
      },
      refresh: handle,
    };
  }
}

export function throttle(fn, wait = 100) {
  let t = 0;
  return (...args) => {
    const now = Date.now();
    if (now - t >= wait) {
      t = now;
      fn(...args);
    }
  };
}


