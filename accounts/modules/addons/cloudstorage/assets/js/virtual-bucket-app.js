// VirtualBucketApp - Alpine.js component factory
// This file loads synchronously to ensure it's available before Alpine initializes

import { createObjectsStore } from './objects-store.js';
import { createVersionsStore } from './versions-store.js';
import { createVirtualizer } from './virtual-grid.js';

export function createVirtualBucketApp(config) {
    const {
        username = '',
        bucket = '',
        prefix = '',
        pageSize = 250,
        apiUrl = '/modules/addons/cloudstorage/api/bucketobjects.php',
        versionsUrl = '/modules/addons/cloudstorage/api/objectversions.php',
    } = config || {};

    const os = createObjectsStore({
        apiUrl,
        username,
        bucket,
        prefix,
        pageSize,
    });
    
    const vs = createVersionsStore({
        versionsUrl,
        username,
        bucket,
    });

    return {
        sortKey: 'name',
        sortDir: 'asc',
        objectsFilter: '',
        objectsLoading: false,
        virtualTotal: 0,
        visibleRows: [],
        drawer: { open: false, key: '', items: [], loading: false },
        visibleVersions: [],
        versionsTotal: 0,
        // column widths (persisted)
        colSizeW: Number(localStorage.getItem('eb_col_size') || 120),
        colModW: Number(localStorage.getItem('eb_col_mod') || 160),
        colActW: Number(localStorage.getItem('eb_col_act') || 140),
        // focused row index (for keyboard nav)
        focusedIndex: 0,
        appReady: false,
        initError: null,
        
        init(overrideParent) {
            const parent = overrideParent || (this.$refs && this.$refs.scrollParent);
            if (!parent) {
                console.warn('VirtualBucketApp: scrollParent not found in refs');
                this.initError = 'Scroll container not found';
                return;
            }
            
            console.log('VirtualBucketApp: Initializing with parent element', parent);
            
            try {
                os.setScrollContainer(parent);
                os.initVirtualizer().then(() => {
                    console.log('VirtualBucketApp: Virtualizer initialized');
                });
                os.attachScrollListener();
                
                console.log('VirtualBucketApp: Starting data fetch...');
                os.reset(prefix || '').then(() => {
                    console.log('VirtualBucketApp: Data loaded, items:', os.state.items.length);
                    console.log('VirtualBucketApp: Store state after reset', {
                        itemsCount: os.state.items.length,
                        virtualItemsCount: os.state.virtual.items.length,
                        virtualTotal: os.state.virtual.totalSize,
                        hasVirtualizer: !!os.getVirtualizer()
                    });
                    
                    // If the store captured an error (status: fail from API), surface it and fallback
                    if (os.state.error) {
                        console.error('VirtualBucketApp: Store error detected:', os.state.error);
                        this.initError = os.state.error;
                        this.appReady = false;
                        if (typeof window.fallbackToLegacyTable === 'function') {
                            try { window.fallbackToLegacyTable(this.initError); } catch (e) {}
                        }
                        return;
                    }
                    
                    // Force virtualizer refresh if available
                    const virt = os.getVirtualizer();
                    if (virt) {
                        virt.refresh();
                        console.log('VirtualBucketApp: Virtualizer refreshed');
                    }
                    
                    // Small delay to let virtualizer update, then refresh view
                    setTimeout(() => {
                        this.refreshView();
                        // Ensure app shows when data loaded
                        if (!this.initError) this.appReady = true;
                        console.log('VirtualBucketApp: View refreshed, visibleRows:', this.visibleRows.length);
                    }, 150);
                }).catch(err => {
                    console.error('Failed to reset store:', err);
                    this.initError = err.message || 'Failed to load data';
                    this.appReady = false; // Set to false on error
                    if (typeof window.fallbackToLegacyTable === 'function') {
                        try { window.fallbackToLegacyTable(this.initError); } catch (e) {}
                    }
                });
                
                // Watch for filter changes - set up after Alpine has initialized
                // Use a small delay to ensure Alpine magic properties are available
                setTimeout(() => {
                    if (typeof this.$watch === 'function') {
                        this.$watch('objectsFilter', (val) => {
                            os.setFilter(val);
                            this.refreshView();
                        });
                    } else {
                        // Fallback: manually watch using polling
                        let currentFilter = this.objectsFilter || '';
                        const filterWatcher = setInterval(() => {
                            const newFilter = this.objectsFilter || '';
                            if (newFilter !== currentFilter) {
                                currentFilter = newFilter;
                                os.setFilter(newFilter);
                                this.refreshView();
                            }
                        }, 200);
                        this._filterWatcher = filterWatcher;
                    }
                }, 100);
                
                // animation frame refresh - always run to update view
                const tick = () => {
                    this.virtualTotal = os.state.virtual.totalSize;
                    this.visibleRows = os.getVisibleRows();
                    this.objectsLoading = os.state.loading;
                    this.focusedIndex = os.state.focusedIndex;
                    // Always continue ticking to keep view updated
                    requestAnimationFrame(tick);
                };
                tick();
                
                // keyboard nav
                parent.tabIndex = 0;
                parent.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowDown') { os.focusNext(); e.preventDefault(); }
                    else if (e.key === 'ArrowUp') { os.focusPrev(); e.preventDefault(); }
                    else if (e.key === ' ' || e.code === 'Space') {
                        const row = os.state.items[os.state.focusedIndex];
                        if (row) { os.toggleSelect(row.name); e.preventDefault(); }
                    } else if (e.key === 'Enter' || e.key.toLowerCase() === 'v') {
                        const row = os.state.items[os.state.focusedIndex];
                        if (row && row.type === 'file') { this.openVersions(row); e.preventDefault(); }
                    }
                });
            } catch (error) {
                console.error('VirtualBucketApp init error:', error);
                this.initError = error.message || 'Initialization failed';
            }
        },
        
        refreshView() {
            // Get filtered items for display
            const filtered = os.state.filter ? 
                os.state.items.filter(r => (r.name || '').toLowerCase().includes(os.state.filter)) :
                os.state.items;
            
            // Update virtualizer count if needed
            if (os.state.virtual && os.state.virtual.virtualizer) {
                os.state.virtual.virtualizer.setOptions(prev => ({ ...prev, count: filtered.length }));
            }
            
            this.virtualTotal = os.state.virtual.totalSize;
            this.visibleRows = os.getVisibleRows();
            
            // Force Alpine reactivity update
            if (this.$forceUpdate) {
                this.$forceUpdate();
            }
        },
        
        setSort(key) { 
            os.setSort(key); 
            this.sortKey = os.state.sortKey; 
            this.sortDir = os.state.sortDir; 
            this.refreshView(); 
        },
        
        toggleSortDir() { 
            os.setSort(this.sortKey); 
            this.sortDir = os.state.sortDir; 
            this.refreshView(); 
        },
        
        toggleSelect(key) { os.toggleSelect(key); },
        isSelected(key) { return os.isSelected(key); },
        selectNone() { os.clearSelection(); },
        
        // grid template computed
        gridTemplateStyle() {
            return `grid-template-columns: 24px minmax(0,1fr) ${this.colSizeW}px ${this.colModW}px ${this.colActW}px;`;
        },
        
        startResize(which, ev) {
            ev.preventDefault();
            const startX = ev.clientX;
            const start = { size: this.colSizeW, mod: this.colModW, act: this.colActW };
            const onMove = (e) => {
                const dx = e.clientX - startX;
                if (which === 'size') this.colSizeW = Math.max(80, start.size + dx);
                if (which === 'modified') this.colModW = Math.max(120, start.mod + dx);
                if (which === 'actions') this.colActW = Math.max(100, start.act + dx);
            };
            const onUp = () => {
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
                localStorage.setItem('eb_col_size', String(this.colSizeW));
                localStorage.setItem('eb_col_mod', String(this.colModW));
                localStorage.setItem('eb_col_act', String(this.colActW));
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        },
        
        openIfFile(row) {
            if (row.type === 'folder') {
                if (window.navigateToFolder) window.navigateToFolder(row.name);
                return;
            }
            this.openVersions(row);
        },
        
        async openVersions(row) {
            this.drawer.open = true;
            this.drawer.key = row.name;
            this.drawer.items = [];
            this.drawer.loading = true;
            try {
                await vs.openFor(row.name);
                this.drawer.items = vs.state.items;
                this.drawer.loading = false;
                
                const el = this.$refs.versionsScroll;
                if (el && !el._vinit) {
                    el._vinit = true;
                    // Virtualize versions list
                    this._versionsVirtualizer = await createVirtualizer({
                        scrollContainer: el,
                        getCount: () => vs.state.items.length,
                        estimateSize: 44,
                        overscan: 8,
                        onChange: ({ items, totalSize }) => {
                            this.versionsTotal = totalSize;
                            this.visibleVersions = items.map(r => vs.state.items[r.index]).filter(Boolean);
                            // Auto-load next page near bottom
                            if (!vs.state.loading && (vs.state.nextKeyMarker || vs.state.nextVersionIdMarker)) {
                                const remaining = el.scrollHeight - el.scrollTop - el.clientHeight;
                                if (remaining < 500) {
                                    this.drawer.loading = true;
                                    vs.loadNext().then(() => {
                                        this.drawer.items = vs.state.items;
                                        this.drawer.loading = false;
                                    });
                                }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to open versions:', error);
                this.drawer.loading = false;
            }
        },
        
        closeDrawer() { 
            this.drawer.open = false; 
        },
        
        restoreVersion(verId) {
            vs.restore(verId).then(r => {
                if (window.toast) { 
                    r.ok ? window.toast.success(r.message) : window.toast.error(r.message); 
                }
            });
        }
    };
}

// Make it available globally for non-module scripts
if (typeof window !== 'undefined') {
    window.createVirtualBucketApp = createVirtualBucketApp;
}

