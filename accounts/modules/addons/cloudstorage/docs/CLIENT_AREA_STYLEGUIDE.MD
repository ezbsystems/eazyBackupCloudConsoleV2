eazyBackup Dark Theme Style Guide
Brand Identity
Primary Brand Color: #FE5000 (Vibrant Orange)
This orange is used sparingly for high-impact brand moments, CTAs, and accent elements—not as a dominant UI color.
Color Palette
Core Surfaces (Dark Theme)
Role	Tailwind Class	Hex Value	Usage
Page Background	bg-slate-950	#020617	Main canvas behind all content
Glass Panel	bg-slate-950/80	#020617cc	Primary containers with transparency
Card Background	bg-slate-900/70	#0f172ab3	Cards, modals, panels
Elevated Surface	bg-slate-800/90	#1e293be6	Icon containers, nav active state
Input Background	bg-[#192331] or bg-gray-700	#192331 / #374151	Form fields, dropdowns
Text Hierarchy
Role	Tailwind Class	Usage
Primary	text-white	Headings, important labels
Body	text-gray-300 or text-slate-100	Body text, values
Muted	text-slate-400	Secondary info, labels
Placeholder	placeholder:text-slate-500	Input placeholders
Border System
Role	Tailwind Class	Usage
Primary Border	border-slate-800/80	Container borders
Input Border	border-slate-700 or border-gray-600	Form field borders
Divider	border-gray-700 or border-white/10	Section dividers
Semantic Colors
Status & Feedback
Status	Background	Text	Border	Example Use
Success	bg-emerald-500/10	text-emerald-300	border-emerald-400/40	Badges, toasts
Success Solid	bg-emerald-600	text-white	—	Buttons
Warning	bg-amber-500/15	text-amber-300	border-amber-400/40	Notices, badges
Error/Danger	bg-rose-500/15	text-rose-300	—	Error states
Error Solid	bg-red-600	text-white	—	Destructive buttons
Info/Default	bg-sky-500/15	text-sky-200	border-sky-400/40	Info badges
Icon Accent Colors
Used for decorative icons within icon containers:
Sky/Cyan: text-sky-300, text-sky-600 — Data, storage, download
Emerald: text-emerald-600 — Success, growth, buckets
Amber/Yellow: text-amber-600, text-yellow-600 — Objects, egress, warnings
Cyan: text-cyan-300 — Usage stats, highlighted values
Brand Color Integration (#FE5000)
The eazyBackup orange is used for:
1. Primary CTA Buttons (High-impact)
/* Gradient button with orange family */bg-gradient-to-r from-[#FE5000] via-[#FF7A33] to-[#FF924D]ring-1 ring-[#FE5000]/40text-slate-950
2. Brand Accent Elements
/* Eyebrow text */text-[#FE5000] text-[11px] font-semibold uppercase tracking-[0.16em]/* Indicator dots */bg-[#FE5000] (small dot inside border circle)/* Glow accent line at top of cards */bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/70 to-[#FE5000]/0
3. Border Accents
/* Brand-tinted border for bullet points */border border-[#FE5000]/60 bg-slate-900/80
Complementary Colors for Dark Theme
To balance the warm #FE5000 orange while maintaining the dark, professional aesthetic:
Primary Interactive Color
Sky/Cyan Blues — #0EA5E9 to #38BDF8
Used for: Links, focus rings, primary actions, interactive elements
Provides cool contrast to the warm brand orange
Classes: text-sky-400, bg-sky-600, ring-sky-500
Success/Affirmative
Emerald Greens — #10B981 to #34D399
Used for: Success states, confirmations, save actions
Complements orange without competing
Classes: text-emerald-300, bg-emerald-600, border-emerald-500/40
Warning (Analogous to Brand)
Amber/Yellow — #F59E0B to #FBBF24
Used for: Warnings, notices, caution states
Sits in the same warm family as brand orange
Classes: text-amber-300, bg-amber-500/15
Danger/Destructive
Rose/Red — #F43F5E to #FB7185
Used for: Errors, destructive actions, alerts
Classes: text-rose-300, bg-red-600
Component Patterns
Page Structure
<div class="min-h-screen bg-slate-950 text-gray-300">    <!-- Subtle top glow -->    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>        <div class="container mx-auto px-4 py-8">        <!-- Content here -->    </div></div>
Glass Panel Container
<div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">    <!-- Card content --></div>
Card Component
<div class="bg-slate-900/70 rounded-lg border border-slate-800/80 shadow-lg p-6">    <!-- Card header -->    <div class="flex items-center justify-between p-4 border-b border-gray-700">        <h4 class="text-xl font-semibold text-white">Title</h4>    </div>    <!-- Card body -->    <div class="p-6">        <!-- Content -->    </div></div>
Pill Navigation
<nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400">    <a class="px-4 py-1.5 rounded-full transition bg-slate-800 text-slate-50 shadow-sm">        Active    </a>    <a class="px-4 py-1.5 rounded-full transition hover:text-slate-200">        Inactive    </a></nav>
Icon Container
<div class="flex items-center p-4 justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">    <svg class="text-sky-600 size-8">...</svg></div>
Input Fields
<input type="text"        class="w-full rounded-lg border border-slate-700 bg-slate-900/60               px-3 py-2.5 text-sm text-slate-100 placeholder:text-slate-500               focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
Status Badges
<!-- Success --><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium              bg-emerald-500/10 text-emerald-300">    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>    Active</span><!-- Warning --><span class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[0.65rem]              font-semibold uppercase tracking-wide text-amber-200 border border-amber-400/40">    Beta</span><!-- Info --><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium              bg-sky-500/15 text-sky-200 border border-sky-400/40">    Info</span>
Button System
Primary Accent Button (Brand CTA)
<button class="inline-flex items-center justify-center gap-2 rounded-full px-4 py-1.5                text-sm font-semibold shadow-sm ring-1 ring-[#FE5000]/40               bg-gradient-to-r from-[#FE5000] via-[#FF7A33] to-[#FF924D]               text-slate-950 transition transform                hover:-translate-y-px hover:shadow-lg               active:translate-y-0 active:shadow-sm">    Create Account</button>
Accent Button (Emerald Gradient)
<button class="btn-accent">    <!-- Emerald to sky gradient, dark text, pill shape -->    Create Bucket</button>
Primary Solid Button
<button class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md">    Save</button>
Secondary/Ghost Button
<button class="bg-gray-700 hover:bg-gray-600 text-gray-300 px-4 py-2 rounded-md">    Cancel</button>
Danger Button
<button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md">    Delete</button>
Icon Button
.icon-btn {    display: inline-flex;    align-items: center;    justify-content: center;    width: 2rem;    height: 2rem;    border-radius: 9999px;    border: 1px solid rgba(51,65,85,0.8); /* slate-700/80 */    background-color: rgba(15,23,42,0.6); /* slate-900/60 */    color: #cbd5e1; /* slate-300 */    transition: all .15s ease;}.icon-btn:hover {    border-color: #94a3b8;    color: white;    background-color: #1f2937;}
Toast/Alert System
<!-- Success Toast --><div class="bg-emerald-600 text-white px-4 py-2 rounded shadow">    Success message</div><!-- Error Toast --><div class="bg-rose-600 text-white px-4 py-2 rounded shadow">    Error message</div><!-- Info Toast --><div class="bg-slate-700 text-slate-100 px-4 py-2 rounded shadow">    Info message</div>
Custom Scrollbar
.scrollbar_thin {    scrollbar-width: thin;    scrollbar-color: #4a5568 #192331;}.scrollbar_thin::-webkit-scrollbar { width: 8px; }.scrollbar_thin::-webkit-scrollbar-track { background: #192331; border-radius: 4px; }.scrollbar_thin::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; border: 1px solid #2d3748; }.scrollbar_thin::-webkit-scrollbar-thumb:hover { background: #5a6478; }
Quick Reference: Color Hex Values
Color	Hex	Tailwind
Brand Orange	#FE5000	Custom
Brand Orange Light	#FF7A33	Custom
Page Background	#020617	slate-950
Card Background	#0f172a	slate-900
Elevated Surface	#1e293b	slate-800
Input Background	#192331	Custom
Border Primary	#1e293b	slate-800
Text Primary	#ffffff	white
Text Body	#d1d5db	gray-300
Text Muted	#94a3b8	slate-400
Sky Accent	#0ea5e9	sky-500
Emerald Success	#10b981	emerald-500
Amber Warning	#f59e0b	amber-500
Rose Error	#f43f5e	rose-500
---

## Premium Effects & Animations

The marketing website uses premium visual effects to create depth, atmosphere, and a high-end feel. These effects are defined in `src/styles/tailwind.css`.

### Gradient Text (Brand Orange to Gold)

Used for hero subheadlines and emphasis text:

```css
.text-gradient {
  background: linear-gradient(90deg, #FE5000 0%, #FF8C00 25%, #FFD700 50%, #FFAA00 75%, #FE5000 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

### Ambient Gradient Orbs

Floating gradient orbs create depth and atmosphere in section backgrounds:

| Class | Color | Animation | Usage |
|-------|-------|-----------|-------|
| `.orb-cyan` | `rgba(34, 211, 238, 0.4)` | `float-orb 20s` | Primary accent glow |
| `.orb-violet` | `rgba(139, 92, 246, 0.35)` | `float-orb-reverse 25s` | Secondary accent |
| `.orb-orange` | `rgba(254, 80, 0, 0.2)` | `pulse-glow 8s` | Brand accent |
| `.orb-emerald` | `rgba(16, 185, 129, 0.25)` | `float-orb 22s` | Success/Canadian accent |

**Example Usage:**
```html
<div class="pointer-events-none absolute inset-0">
  <div class="absolute -top-40 -right-40 h-[500px] w-[500px] rounded-full orb-cyan blur-[100px]"></div>
  <div class="absolute -bottom-40 -left-40 h-[450px] w-[450px] rounded-full orb-violet blur-[100px]"></div>
</div>
```

### Background Patterns

| Class | Description | Usage |
|-------|-------------|-------|
| `.bg-dot-pattern` | Subtle dot grid (24px spacing) | Hero sections, pricing |
| `.bg-grid-fine` | Fine grid lines (32px spacing) | Content sections |

### Floating Decorative Dots

Animated accent dots add subtle movement and visual interest:

```html
<div class="w-2 h-2 rounded-full bg-cyan-400/60 accent-dot"></div>
<div class="w-1.5 h-1.5 rounded-full bg-violet-400/50 accent-dot accent-dot-delayed-1"></div>
<div class="w-2 h-2 rounded-full bg-emerald-400/50 accent-dot accent-dot-delayed-2"></div>
```

Animation delays: `.accent-dot-delayed-1` (0.5s), `.accent-dot-delayed-2` (1s), `.accent-dot-delayed-3` (1.5s)

### Card Top Glow Accent

Subtle orange glow line at top of premium cards:

```html
<div class="relative rounded-2xl border border-slate-800 bg-surface">
  <!-- Subtle top glow -->
  <div class="pointer-events-none absolute inset-x-0 -top-px h-px bg-gradient-to-r from-[#FE5000]/0 via-[#FE5000]/70 to-[#FE5000]/0"></div>
  <!-- Card content -->
</div>
```

### Card Ambient Glow on Hover

```css
.card-ambient-glow::before {
  content: '';
  position: absolute;
  inset: -2px;
  border-radius: inherit;
  background: linear-gradient(135deg, rgba(34, 211, 238, 0.2) 0%, transparent 30%, transparent 70%, rgba(168, 85, 247, 0.15) 100%);
  z-index: -1;
  opacity: 0;
  filter: blur(8px);
  transition: opacity 0.4s ease;
}
.card-ambient-glow:hover::before {
  opacity: 1;
}
```

---

## Section Transitions

### Wave Divider

Creates a flowing wave transition between sections:

```html
<div class="relative h-20 md:h-28 -mt-1 overflow-hidden bg-surface">
  <svg class="absolute bottom-0 w-full h-full" viewBox="0 0 1440 100" preserveAspectRatio="none">
    <defs>
      <linearGradient id="wave-gradient-1" x1="0%" y1="0%" x2="100%" y2="0%">
        <stop offset="0%" style="stop-color:rgba(34,211,238,0.08)" />
        <stop offset="50%" style="stop-color:rgba(139,92,246,0.06)" />
        <stop offset="100%" style="stop-color:rgba(34,211,238,0.08)" />
      </linearGradient>
    </defs>
    <path fill="var(--surface-2)" d="M0,100 L0,60 Q360,100 720,60 T1440,60 L1440,100 Z"></path>
  </svg>
</div>
```

### Curved Connecting Lines

SVG paths with gradient strokes and animated dots connecting sections:

```html
<svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
  <defs>
    <linearGradient id="line-gradient-1" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:#06b6d4;stop-opacity:0.3" />
      <stop offset="50%" style="stop-color:#8b5cf6;stop-opacity:0.4" />
      <stop offset="100%" style="stop-color:#f97316;stop-opacity:0.3" />
    </linearGradient>
  </defs>
  <path class="flowing-line" fill="none" stroke="url(#line-gradient-1)" stroke-width="0.15"
        d="M 0,30 Q 25,70 50,50 T 100,70" />
  <!-- Animated accent dots -->
  <circle cx="50" cy="50" r="1" fill="#8b5cf6" opacity="0.7">
    <animate attributeName="opacity" values="0.5;0.9;0.5" dur="3s" repeatCount="indefinite" />
  </circle>
</svg>
```

---

## Animation Keyframes

All animations are defined in `src/styles/tailwind.css`:

| Animation | Duration | Description |
|-----------|----------|-------------|
| `float-orb` | 20s | Organic floating movement for gradient orbs |
| `float-orb-reverse` | 25s | Reverse floating for visual variety |
| `pulse-glow` | 8s | Gentle pulsing opacity change |
| `float-dot` | 4s | Floating animation for accent dots |
| `shimmer` | 8s | Background position animation for shimmer text |
| `gentle-float` | — | Subtle vertical float |
| `border-flow` | 6s | Animated gradient border |

### Accessibility

All animations respect `prefers-reduced-motion`:

```css
@media (prefers-reduced-motion: reduce) {
  .orb-cyan, .orb-violet, .orb-orange, .orb-emerald,
  .accent-dot, .flowing-line, .text-shimmer, .animated-border::before {
    animation: none !important;
  }
}
```

---

## Text Glow Effects

For headings that need extra emphasis:

| Class | Color | Usage |
|-------|-------|-------|
| `.glow-text-cyan` | `rgba(34, 211, 238, 0.3)` | Tech/data headings |
| `.glow-text-orange` | `rgba(254, 80, 0, 0.25)` | Brand emphasis |

```css
.glow-text-cyan {
  text-shadow: 0 0 40px rgba(34, 211, 238, 0.3), 0 0 80px rgba(34, 211, 238, 0.15);
}
```

---

## Interactive Cursor Effects

Hero sections can include cursor-following glow:

```html
<section x-data="{ x: 50, y: 50 }"
         @mousemove="
           const rect = $el.getBoundingClientRect();
           x = (($event.clientX - rect.left) / rect.width) * 100;
           y = (($event.clientY - rect.top) / rect.height) * 100;
         ">
  <div class="absolute inset-0 opacity-60 transition-[background-position] duration-300"
       :style="`background: radial-gradient(600px circle at ${x}% ${y}%, rgba(34,211,238,0.15), transparent 40%);`">
  </div>
</section>
```

---

## Key Principles Summary

1. **Use the slate color scale** for surfaces (950, 900, 800)
2. **Reserve #FE5000 orange** for high-impact brand moments and gradients
3. **Use sky/cyan** for interactive elements and primary ambient lighting
4. **Use emerald** for success states and Canadian identity elements
5. **Maintain generous border-radius** (rounded-lg, rounded-xl, rounded-3xl, rounded-full for pills)
6. **Use semi-transparent backgrounds** with subtle borders
7. **Layer ambient lighting** with gradient orbs for depth
8. **Add subtle animations** that respect reduced motion preferences
9. **Use wave/curve dividers** to create visual flow between sections
10. **Apply card top glow accents** for premium feel on key containers