# Floorit Embed SDK

A single-file, dependency-free JavaScript widget that any third-party website (flooring retailers, contractors, showroom sites) can drop in to let *their* visitors try Floorit's AI flooring visualizer without ever leaving the host page.

It ships as a self-contained IIFE (`floorit.js`, ~1,700 lines) — no build step, no npm install, no iframe sandboxing headaches. It renders a full-screen modal, injects its own scoped styles (`epoxy-` prefixed to avoid clashing with the host site's CSS), and talks to the Floorit API directly over `fetch`.

## Why a vanilla-JS widget instead of an iframe

The original prototype (kept here as a reference under a different name in the parent repo's history) embedded the generator in an `<iframe>`. That's simple, but iframes fight you on:

- Camera access (`getUserMedia` inside a cross-origin iframe needs extra permission plumbing)
- Responsive sizing (constant `postMessage` height syncing)
- Perceived performance (a second full page load inside the page)

The current SDK instead injects a modal directly into the host page's DOM, scoped with a unique class prefix so it can't collide with the host's own styles or scripts. That's the harder problem to solve, but it makes the embed feel native.

## Quick start

```html
<script src="https://your-domain.com/sdk/floorit.js"></script>

<button onclick="startVisualizer()">Visualize My Floor</button>

<script>
  function startVisualizer() {
    EpoxyModal.init({
      apiKey: 'your-api-key',   // issued from the Floorit dashboard
      embedId: 'homepage-cta',  // any unique string per placement
    });
  }
</script>
```

That's the entire integration surface. The widget handles everything else: API key validation, image upload or live camera capture, texture selection, triggering the AI generation, polling for the result, and a before/after reveal — all inside the modal it renders itself.

## What happens on `init()`

1. The API key is validated against `POST /api/embed/validate-key` before anything is rendered — an invalid or inactive key shows an inline error instead of a broken widget.
2. A `EpoxyModalInstance` is created and mounted (multiple embeds with different `embedId`s can coexist on one page).
3. The user uploads a photo or captures one live via `getUserMedia`.
4. Available flooring textures are fetched and rendered as a picker.
5. On submit, the widget calls the generation endpoint, polls for completion, and reveals the AI-generated result with a download option.

## API base URL resolution

The widget doesn't need a config option for its API host — it inspects its own `<script src>` tag at load time and derives the API base URL from it, falling back to `window.location.origin` if the script tag can't be found (e.g. dynamically injected):

```js
getApiBaseUrl() {
  const scripts = document.getElementsByTagName('script');
  for (let script of scripts) {
    if (script.src && script.src.includes('/sdk/floorit.js')) {
      const url = new URL(script.src);
      return `${url.protocol}//${url.host}/api`;
    }
  }
  return window.location.origin + '/api';
}
```

This means the same file works unmodified whether it's served from `https://app.floorit.ai/sdk/floorit.js` or self-hosted on a CDN — zero configuration for the integrator.

## Notes on this excerpt

This file is included in the portfolio repo as-is from production, minus the API key used in local testing. It depends on backend routes (`/api/embed/*`) that live in the main application and aren't part of this showcase — see the root [README](../README.md) for the full architecture.
