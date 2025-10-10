(function(){
  function toGiB(bytes){ return (bytes/ (1024*1024*1024)); }
  function fmtGiB(bytes){ return toGiB(bytes).toFixed(2); }

  async function fetchHistory(endpoint, username, days){
    const url = `${endpoint}&a=storage-history&username=${encodeURIComponent(username)}&days=${days||180}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Failed to fetch');
    return await res.json();
  }

  function renderSimpleChart(el, data){
    if (!el) return;
    const w = el.clientWidth || 600;
    const h = el.clientHeight || 160;
    let pad = { l: 40, r: 10, t: 12, b: 24 };
    let innerW = Math.max(10, w - pad.l - pad.r);
    let innerH = Math.max(10, h - pad.t - pad.b);

    el.innerHTML = '';
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('width', String(w));
    svg.setAttribute('height', String(h));
    el.appendChild(svg);

    if (!data || data.length === 0){
      const txt = document.createElementNS('http://www.w3.org/2000/svg','text');
      txt.setAttribute('x', String(pad.l));
      txt.setAttribute('y', String(pad.t + 14));
      txt.setAttribute('fill', '#94a3b8');
      txt.textContent = 'No history yet';
      svg.appendChild(txt);
      return;
    }

    // Scales (coerce to numbers and consider all series for max)
    for (let i=0;i<data.length;i++){
      data[i].total  = Number(data[i].total || 0);
      data[i].t1000  = Number(data[i].t1000 || 0);
      data[i].t1003  = Number(data[i].t1003 || 0);
    }
    const xs = data.map((_,i)=>i);
    const seriesMax = data.reduce((m,d)=>{
      const localMax = Math.max(d.total||0, d.t1000||0, d.t1003||0);
      return Math.max(m, localMax);
    }, 0);
    const minX = 0, maxX = xs[xs.length-1] || 1;
    const maxY = Math.max(1, seriesMax);

    // Adjust left padding to fit the widest Y-axis label
    const gib = 1024*1024*1024;
    const candidates = [1,2,5,10,20,50,100,200,500,1000,2000,5000,10000]; // GiB steps
    let stepGiB = 1;
    const maxGiB = maxY / gib;
    for (let c of candidates){ if (maxGiB / c <= 4) { stepGiB = c; break; } }
    const topGiB = Math.max(stepGiB, Math.ceil(maxGiB/stepGiB)*stepGiB);
    const top = topGiB * gib;

    // Measure the longest label to ensure it's fully visible
    const measure = (text)=>{
      const tmp = document.createElementNS('http://www.w3.org/2000/svg','text');
      tmp.setAttribute('font-size','10');
      tmp.setAttribute('visibility','hidden');
      tmp.textContent = text;
      svg.appendChild(tmp);
      const bb = tmp.getBBox();
      const w = bb.width;
      const h = bb.height;
      svg.removeChild(tmp);
      return { w, h };
    };
    const labelLongest = topGiB.toFixed(1) + ' GiB';
    const { w: labelW, h: labelH } = measure(labelLongest);
    pad.l = Math.max(pad.l, Math.ceil(labelW) + 16); // 16px for tick + gap
    pad.t = Math.max(pad.t, Math.ceil(labelH) + 6);  // ensure top label fits
    innerW = Math.max(10, w - pad.l - pad.r);

    const yMax = Math.max(maxY, top); // scale domain includes rounded top
    const xScale = (i)=> pad.l + (i - minX) / (maxX - minX || 1) * innerW;
    const yScale = (v)=> pad.t + innerH - (v / (yMax || 1)) * innerH;

    // Axes
    const axisColor = '#334155';
    const ax = document.createElementNS('http://www.w3.org/2000/svg','line');
    ax.setAttribute('x1', String(pad.l));
    ax.setAttribute('y1', String(pad.t + innerH));
    ax.setAttribute('x2', String(pad.l + innerW));
    ax.setAttribute('y2', String(pad.t + innerH));
    ax.setAttribute('stroke', axisColor);
    svg.appendChild(ax);

    const ay = document.createElementNS('http://www.w3.org/2000/svg','line');
    ay.setAttribute('x1', String(pad.l));
    ay.setAttribute('y1', String(pad.t));
    ay.setAttribute('x2', String(pad.l));
    ay.setAttribute('y2', String(pad.t + innerH));
    ay.setAttribute('stroke', axisColor);
    svg.appendChild(ay);

    // Y ticks/labels using binary GiB with nice rounded steps
    const ticks = 4;
    for (let i=0;i<=ticks;i++){
      const val = top * (i/ticks);
      const y = yScale(val);
      const t = document.createElementNS('http://www.w3.org/2000/svg','line');
      t.setAttribute('x1', String(pad.l - 4));
      t.setAttribute('y1', String(y));
      t.setAttribute('x2', String(pad.l));
      t.setAttribute('y2', String(y));
      t.setAttribute('stroke', axisColor);
      svg.appendChild(t);

      const lab = document.createElementNS('http://www.w3.org/2000/svg','text');
      lab.setAttribute('x', String(pad.l - 6));
      lab.setAttribute('y', String(y + 3));
      lab.setAttribute('text-anchor', 'end');
      lab.setAttribute('fill', '#94a3b8');
      lab.setAttribute('font-size', '10');
      lab.textContent = (val/gib).toFixed(1) + ' GiB';
      svg.appendChild(lab);
    }

    // X labels (first/last and mid if many)
    const putXLabel = (idx) => {
      const x = xScale(idx);
      const txt = document.createElementNS('http://www.w3.org/2000/svg','text');
      txt.setAttribute('x', String(x));
      txt.setAttribute('y', String(pad.t + innerH + 14));
      txt.setAttribute('text-anchor', idx===0 ? 'start' : (idx===maxX ? 'end' : 'middle'));
      txt.setAttribute('fill', '#94a3b8');
      txt.setAttribute('font-size', '10');
      txt.textContent = data[idx]?.d || '';
      svg.appendChild(txt);
    };
    putXLabel(0);
    if (maxX > 0) {
      putXLabel(maxX);
      if (maxX >= 4) putXLabel(Math.round(maxX/2));
    }

    // Paths + point markers (always visible, even for single point)
    const drawSeries = (arr, key, color)=>{
      const p = [];
      for (let i=0;i<arr.length;i++){
        const x = xScale(i);
        const y = yScale(arr[i][key]||0);
        p.push((i===0?'M':'L')+x+','+y);
      }
      const elp = document.createElementNS('http://www.w3.org/2000/svg','path');
      elp.setAttribute('d', p.join(' '));
      elp.setAttribute('fill','none');
      elp.setAttribute('stroke', color);
      elp.setAttribute('stroke-width','2');
      svg.appendChild(elp);

      for (let i=0;i<arr.length;i++){
        const cx = xScale(i);
        const cy = yScale(arr[i][key]||0);
        const c = document.createElementNS('http://www.w3.org/2000/svg','circle');
        c.setAttribute('cx', String(cx));
        c.setAttribute('cy', String(cy));
        c.setAttribute('r', '2.5');
        c.setAttribute('fill', color);
        svg.appendChild(c);
      }
    };

    drawSeries(data, 'total',  '#3b82f6');  // Total
    drawSeries(data, 't1000',  '#10b981');  // S3-compatible
    drawSeries(data, 't1003',  '#06b6d4');  // eazyBackup
  }

  function attach(){
    try {
      const container = document.getElementById('eb-storage-card');
      if (!container) return;
      const chartEl = document.getElementById('eb-storage-chart');
      const username = container.getAttribute('data-username');
      const endpoint = window.EB_MODULE_LINK;
      const days = 180;
      if (!endpoint || !username) return;
      fetchHistory(endpoint, username, days).then((resp)=>{
        if (!resp || resp.status !== 'success') return;
        const data = resp.data || [];
        renderSimpleChart(chartEl, data);
      }).catch(()=>{});
    } catch(e) {}
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
  else attach();
})();


