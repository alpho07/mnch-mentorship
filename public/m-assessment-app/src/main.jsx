import { createRoot } from "react-dom/client";
import { Capacitor } from "@capacitor/core";
import App from "./App.jsx";
import { InstallPrompt } from "./components/install-prompt.jsx";

// StrictMode intentionally omitted — it double-invokes effects in dev,
// causing duplicate /auth/me calls in the network tab.
createRoot(document.getElementById("root")).render(
    <>
        <App />
        {/* Available before sign-in too, so a new browser user can install immediately. */}
        <InstallPrompt />
    </>,
);

// PWA service worker: browser-only. The native Capacitor app ships its
// assets directly and gains nothing from it — worse, a previously
// registered service worker's cache survives app upgrades/reinstalls and
// can silently mask a freshly rebuilt native app behind stale cached JS.
if (!Capacitor.isNativePlatform()) {
    import("virtual:pwa-register").then(({ registerSW }) => registerSW({ immediate: true }));
}
