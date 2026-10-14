/**
 * js/session_tracker.js
 *
 * Client-side lightweight user session and interaction tracker for Jotify.
 * Tracks device/network handshake, page views, semantic UI interactions,
 * form submissions, and tab visibility without polluting map navigation.
 */

(() => {
  'use strict';

  if (!window.JOTI_SESSION_TRACKER || !window.JOTI_SESSION_TRACKER.sessionId) {
    return;
  }

  const sessionId = window.JOTI_SESSION_TRACKER.sessionId;
  const storageKey = `joti_hs_${sessionId}`;
  const needsHandshake = !!window.JOTI_SESSION_TRACKER.needsHandshake || !sessionStorage.getItem(storageKey);

  let eventQueue = [];
  let flushTimer = null;
  let lastVisibilityTime = 0;
  let lastVisibilityState = document.visibilityState;
  let lastMeasuredRtt = null;

  /**
   * Gather hardware, viewport, and network performance indicators
   */
  function gatherHandshake() {
    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent));

    let netType = 'lan_wifi';
    if (conn && conn.type && conn.type !== 'unknown') {
      netType = conn.type; // 'wifi', 'cellular', 'ethernet'
    } else if (isMobile) {
      if (conn && (conn.effectiveType === '2g' || conn.effectiveType === '3g')) {
        netType = 'cellular';
      } else {
        netType = 'mobiel';
      }
    } else {
      netType = 'lan_wifi';
    }

    return {
      viewport_width: window.innerWidth || null,
      viewport_height: window.innerHeight || null,
      screen_width: window.screen ? window.screen.width : null,
      screen_height: window.screen ? window.screen.height : null,
      pixel_ratio: window.devicePixelRatio ? Math.round(window.devicePixelRatio * 100) / 100 : 1,
      network_type: netType,
      network_downlink: (conn && typeof conn.downlink === 'number') ? conn.downlink : null,
      network_rtt: lastMeasuredRtt !== null ? lastMeasuredRtt : ((conn && typeof conn.rtt === 'number') ? conn.rtt : null),
      platform: (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || null,
      language: navigator.language || null,
      touch_points: navigator.maxTouchPoints || 0
    };
  }

  /**
   * Flush queued events to backend ingestion API
   */
  function flushEvents(immediate = false) {
    if (flushTimer) {
      clearTimeout(flushTimer);
      flushTimer = null;
    }

    if (eventQueue.length === 0) {
      return;
    }

    const batch = eventQueue.splice(0, 25);
    const payload = {
      batch_sent_at: Date.now(),
      events: batch
    };

    // Send handshake if not yet registered for this session
    if (!sessionStorage.getItem(storageKey)) {
      payload.handshake = gatherHandshake();
      sessionStorage.setItem(storageKey, '1');
    }

    const jsonPayload = JSON.stringify(payload);

    // If immediate (e.g. page unload), prefer sendBeacon if available
    if (immediate && navigator.sendBeacon) {
      try {
        const blob = new Blob([jsonPayload], { type: 'application/json' });
        const sent = navigator.sendBeacon('/api/session_track.php', blob);
        if (sent) return;
      } catch (e) {
        // Fallback to fetch with keepalive below
      }
    }

    const tStart = performance.now();
    fetch('/api/session_track.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: jsonPayload,
      keepalive: true
    }).then(() => {
      const rtt = Math.round(performance.now() - tStart);
      if (rtt > 0) {
        lastMeasuredRtt = rtt;
      }
    }).catch(() => {
      // Re-queue events on transient failure if not unloading
      if (!immediate) {
        eventQueue.unshift(...batch.slice(0, 10));
      }
    });
  }

  /**
   * Enqueue a telemetry event
   */
  function trackEvent(eventType, targetTag = null, targetId = null, targetLabel = null, metadata = null) {
    eventQueue.push({
      client_timestamp: Date.now(),
      event_type: eventType,
      page_url: window.location.pathname + window.location.search,
      referrer: document.referrer ? document.referrer.slice(0, 255) : null,
      target_tag: targetTag ? String(targetTag).slice(0, 32) : null,
      target_id: targetId ? String(targetId).slice(0, 128) : null,
      target_label: targetLabel ? String(targetLabel).slice(0, 255) : null,
      metadata: metadata || null
    });

    if (eventQueue.length >= 10) {
      flushEvents(false);
    } else if (!flushTimer) {
      flushTimer = setTimeout(() => flushEvents(false), 2500);
    }
  }

  // 1. Initial Page View & Handshake
  const initialPayload = {
    batch_sent_at: Date.now(),
    events: [{
      client_timestamp: Date.now(),
      event_type: 'page_view',
      page_url: window.location.pathname + window.location.search,
      referrer: document.referrer ? document.referrer.slice(0, 255) : null,
      target_tag: 'window',
      target_label: document.title ? document.title.slice(0, 255) : 'Pagina geladen'
    }]
  };

  if (needsHandshake) {
    initialPayload.handshake = gatherHandshake();
    sessionStorage.setItem(storageKey, '1');
  }

  const t0 = performance.now();
  fetch('/api/session_track.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(initialPayload),
    keepalive: true
  }).then(() => {
    const measuredPing = Math.round(performance.now() - t0);
    if (measuredPing > 0) {
      lastMeasuredRtt = measuredPing;
      // Send accurate measured ping update to server
      fetch('/api/session_track.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ handshake: { network_rtt: measuredPing } }),
        keepalive: true
      }).catch(() => {});
    }
  }).catch(() => {});

  // 2. Semantic UI Click Tracking
  document.addEventListener('click', (e) => {
    // Ignore clicks on map drag canvases / Leaflet layers
    if (e.target.closest('.leaflet-pane, .leaflet-control-zoom, .mapboxgl-canvas-container, canvas')) {
      // Allow explicit buttons inside popups
      if (!e.target.closest('button, a, input')) {
        return;
      }
    }

    const interactiveEl = e.target.closest('a, button, input[type="submit"], input[type="button"], [role="button"], [role="tab"], [data-track], summary, select');
    if (!interactiveEl) {
      return;
    }

    // Determine event classification
    let eventType = 'click';
    if (interactiveEl.matches('[role="tab"], [data-tab], .tab-btn')) {
      eventType = 'tab_switch';
    } else if (interactiveEl.hasAttribute('data-modal-target') || interactiveEl.hasAttribute('data-modal-toggle') || (interactiveEl.id && interactiveEl.id.toLowerCase().includes('modal'))) {
      eventType = 'modal';
    }

    // Extract human-readable label while strictly preserving user privacy
    let label = interactiveEl.getAttribute('data-track') ||
                interactiveEl.getAttribute('aria-label') ||
                interactiveEl.getAttribute('title');

    if (!label) {
      // Never read password or sensitive text input values
      if (interactiveEl.tagName === 'INPUT' && (interactiveEl.type === 'password' || interactiveEl.type === 'email')) {
        label = `[${interactiveEl.type} input]`;
      } else if (interactiveEl.tagName === 'INPUT') {
        label = interactiveEl.value || interactiveEl.getAttribute('placeholder') || interactiveEl.name;
      } else {
        const text = (interactiveEl.innerText || '').replace(/\s+/g, ' ').trim();
        label = text ? text.slice(0, 80) : (interactiveEl.getAttribute('href') || interactiveEl.id);
      }
    }

    const metadata = {};
    if (interactiveEl.tagName === 'A' && interactiveEl.getAttribute('href')) {
      metadata.href = interactiveEl.getAttribute('href').slice(0, 255);
    }

    trackEvent(
      eventType,
      interactiveEl.tagName.toLowerCase(),
      interactiveEl.id || interactiveEl.getAttribute('name') || null,
      label || 'Knop/Link geklikt',
      Object.keys(metadata).length > 0 ? metadata : null
    );

    // If this click triggers an immediate page navigation via <a>, flush immediately
    if (interactiveEl.tagName === 'A' && interactiveEl.href && !interactiveEl.href.startsWith('#') && !interactiveEl.target) {
      flushEvents(true);
    }
  }, true);

  // 3. Form Submission Tracking
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form || form.tagName !== 'FORM') {
      return;
    }

    const action = form.getAttribute('action') || window.location.pathname;
    const formId = form.id || form.getAttribute('name') || null;

    trackEvent('form_submit', 'form', formId, `Formulier verzonden (${action})`);
    flushEvents(true);
  }, true);

  // 4. Tab Visibility Tracking (Debounced 3s)
  document.addEventListener('visibilitychange', () => {
    const now = Date.now();
    const currentState = document.visibilityState;

    if (currentState === lastVisibilityState && (now - lastVisibilityTime) < 3000) {
      return;
    }

    lastVisibilityTime = now;
    lastVisibilityState = currentState;

    const label = currentState === 'hidden' ? 'Tabblad verborgen' : 'Tabblad actief';
    trackEvent('visibility', 'window', null, label);

    if (currentState === 'hidden') {
      flushEvents(true);
    }
  });

  // 5. Page Unload Flush
  window.addEventListener('pagehide', () => flushEvents(true));
  window.addEventListener('beforeunload', () => flushEvents(true));
})();
