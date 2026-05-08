/* BL Location Map — Leaflet + dark Carto tiles + cluster + side panel
   Initializes every .bl-locmap on the page. Locations data comes from
   either window.BL_LOCATIONS (set by WP via wp_localize_script) or from
   a sibling <script type="application/json" class="bl-lm-data"> element. */
(function(){
  'use strict';

  function getData(root){
    if (Array.isArray(window.BL_LOCATIONS)) return window.BL_LOCATIONS;
    var node = root.querySelector('script.bl-lm-data');
    if (!node) return [];
    try { return JSON.parse(node.textContent) || []; }
    catch(e){ console.error('BL Location Map: invalid JSON data', e); return []; }
  }

  function escapeHtml(s){
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
  }
  function linkifyEmails(s){
    if (!s) return '';
    return escapeHtml(s).replace(/[\w.+-]+@[\w-]+\.[\w.-]+/g, function(m){
      return '<a href="mailto:' + m + '">' + m + '</a>';
    });
  }
  function webLink(w){
    if (!w) return '';
    var url = /^https?:\/\//i.test(w) ? w : 'https://' + w;
    return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' +
           escapeHtml(w) + '</a>';
  }

  function init(root){
    if (root.dataset.blInit === '1') return;
    root.dataset.blInit = '1';

    var data = getData(root).filter(function(l){
      return typeof l.lat === 'number' && typeof l.lng === 'number';
    });

    var mapEl = root.querySelector('.bl-lm-map');
    if (!mapEl || typeof L === 'undefined') return;

    var map = L.map(mapEl, {
      worldCopyJump:true, zoomControl:true, minZoom:2,
    }).setView([20, 10], 2);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
      attribution:'&copy; OpenStreetMap &copy; CARTO',
      subdomains:'abcd', maxZoom:19,
    }).addTo(map);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png', {
      subdomains:'abcd', maxZoom:19, pane:'shadowPane',
    }).addTo(map);

    var cluster = L.markerClusterGroup({
      showCoverageOnHover:false,
      spiderfyOnMaxZoom:true,
      maxClusterRadius:45,
      iconCreateFunction:function(c){
        var n = c.getChildCount();
        var size = n < 10 ? 's' : n < 50 ? 'm' : 'l';
        return L.divIcon({
          html:'<div class="bl-cluster ' + size + '">' + n + '</div>',
          className:'', iconSize:null,
        });
      }
    });
    map.addLayer(cluster);

    var state = { all:data, filtered:[], type:'all', region:'', q:'', activeId:null };
    var markersById = new Map();

    function makeMarker(loc, id){
      var isCorp = loc.type === 'Corporate';
      var cls = 'bl-marker ' + (isCorp ? 'corp' : 'dist');
      var icon = L.divIcon({
        html:'<div class="' + cls + '" data-id="' + id + '"><div class="core"></div></div>',
        className:'', iconSize:[24,24], iconAnchor:[12,12],
      });
      var m = L.marker([loc.lat, loc.lng], {icon:icon, riseOnHover:true});
      m.on('click', function(){ openPanel(id); });
      return m;
    }

    function openPanel(id){
      var loc = state.all[id]; if (!loc) return;
      state.activeId = id;
      root.querySelectorAll('.bl-marker.active').forEach(function(el){
        el.classList.remove('active');
      });
      var el = root.querySelector('.bl-marker[data-id="' + id + '"]');
      if (el) el.classList.add('active');

      var panel = root.querySelector('.bl-lm-panel');
      var isCorp = loc.type === 'Corporate';
      root.querySelector('.bl-lm-p-type').innerHTML =
        '<span class="dot ' + (isCorp?'corp':'dist') + '"></span>' + escapeHtml(loc.type);
      root.querySelector('.bl-lm-p-name').textContent = loc.company || '';
      root.querySelector('.bl-lm-p-region').textContent = loc.region || '';

      var phone = loc.phone
        ? escapeHtml(loc.phone).replace(/\b(\+?[\d\s().\-/]{6,})/g, '<a href="tel:$1">$1</a>')
        : '<span class="muted">—</span>';

      var fields = [
        ['Address', escapeHtml(loc.address) || '<span class="muted">Not provided</span>'],
        ['Contact', escapeHtml(loc.contact) || '<span class="muted">—</span>'],
        ['Phone',   phone],
        ['Email',   loc.email ? linkifyEmails(loc.email) : '<span class="muted">—</span>'],
        ['Web',     loc.web   ? webLink(loc.web)        : '<span class="muted">—</span>'],
      ];
      root.querySelector('.bl-lm-panel-body').innerHTML = fields.map(function(f){
        return '<div class="bl-lm-field"><div class="bl-lm-field-label">' + f[0] +
               '</div><div class="bl-lm-field-val">' + f[1] + '</div></div>';
      }).join('');

      panel.classList.add('open');
      panel.setAttribute('aria-hidden', 'false');

      if (window.innerWidth > 720){
        var point = map.latLngToContainerPoint([loc.lat, loc.lng]);
        var pw = parseInt(getComputedStyle(root).getPropertyValue('--panel-w')) || 380;
        map.panBy([point.x - (map.getSize().x - pw)/2 + pw, 0], {animate:true});
      }
    }

    function closePanel(){
      var panel = root.querySelector('.bl-lm-panel');
      panel.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
      root.querySelectorAll('.bl-marker.active').forEach(function(el){
        el.classList.remove('active');
      });
      state.activeId = null;
    }
    root.querySelector('.bl-lm-panel-close').addEventListener('click', closePanel);
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closePanel();
    });

    function applyFilters(){
      var q = state.q.trim().toLowerCase();
      state.filtered = state.all.map(function(l, i){ return {l:l, i:i}; }).filter(function(o){
        var l = o.l;
        if (state.type !== 'all' && l.type !== state.type) return false;
        if (state.region && l.region !== state.region) return false;
        if (q){
          var hay = ((l.company||'') + ' ' + (l.region||'') + ' ' + (l.contact||'') + ' ' +
                     (l.address||'') + ' ' + (l.email||'')).toLowerCase();
          if (hay.indexOf(q) === -1) return false;
        }
        return true;
      });

      cluster.clearLayers();
      var layers = [];
      state.filtered.forEach(function(o){
        var m = markersById.get(o.i);
        if (!m){ m = makeMarker(o.l, o.i); markersById.set(o.i, m); }
        layers.push(m);
      });
      cluster.addLayers(layers);
      root.querySelector('.bl-lm-count-num').textContent = state.filtered.length;
    }

    function buildRegionOptions(){
      var sel = root.querySelector('.bl-lm-region');
      var seen = {};
      state.all.forEach(function(l){ if (l.region) seen[l.region] = true; });
      Object.keys(seen).sort().forEach(function(r){
        var o = document.createElement('option');
        o.value = r; o.textContent = r; sel.appendChild(o);
      });
    }

    function bindToolbar(){
      root.querySelector('.bl-lm-search').addEventListener('input', function(e){
        state.q = e.target.value; applyFilters();
      });
      root.querySelector('.bl-lm-region').addEventListener('change', function(e){
        state.region = e.target.value; applyFilters();
      });
      root.querySelectorAll('.bl-lm-toggle button').forEach(function(b){
        b.addEventListener('click', function(){
          root.querySelectorAll('.bl-lm-toggle button').forEach(function(x){
            x.classList.remove('active');
          });
          b.classList.add('active');
          state.type = b.dataset.type;
          applyFilters();
        });
      });
    }

    buildRegionOptions();
    bindToolbar();
    applyFilters();

    if (data.length){
      var bounds = L.latLngBounds(data.map(function(d){ return [d.lat, d.lng]; }));
      if (bounds.isValid()) map.fitBounds(bounds.pad(0.15), {maxZoom:4});
    }

    // Recompute size in case container was hidden at init (Elementor tabs, etc.)
    setTimeout(function(){ map.invalidateSize(); }, 200);
  }

  function initAll(){
    document.querySelectorAll('.bl-locmap').forEach(init);
  }
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
