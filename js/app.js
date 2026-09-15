/**
 * js/app.js ??? Global client-side UI handlers and countdown timers.
 */

function w3_open() {
    const mySidebar = document.getElementById("mySidebar");
    const overlayBg = document.getElementById("myOverlay");
    if (!mySidebar) return;
    mySidebar.classList.remove("-translate-x-full");
    mySidebar.classList.remove("hidden");
    mySidebar.classList.add("flex");
    if (overlayBg) overlayBg.classList.remove("hidden");
}

function w3_close() {
    const mySidebar = document.getElementById("mySidebar");
    const overlayBg = document.getElementById("myOverlay");
    if (!mySidebar) return;
    mySidebar.classList.add("-translate-x-full");
    setTimeout(() => {
        mySidebar.classList.add("hidden");
        mySidebar.classList.remove("flex");
    }, 300);
    if (overlayBg) overlayBg.classList.add("hidden");
}

function updateImmuneCountdowns() {
    const now = Math.floor(Date.now() / 1000);
    document.querySelectorAll(".immune-countdown").forEach((el) => {
        const until = parseInt(el.getAttribute("data-until"), 10);
        if (!until) return;
        const diff = until - now;
        if (diff > 0) {
            const m = Math.floor(diff / 60);
            const s = diff % 60;
            el.textContent = `${m}m ${s}s`;
        } else {
            const duratie = el.getAttribute("data-duratie") || "";
            const container = el.closest(".fox-badge-container");
            if (container) {
                const initial = container.getAttribute("data-initial") || "";
                const color = container.getAttribute("data-color") || "gray";
                const isMobile = container.getAttribute("data-mobile") === "1";
                const baseClass = isMobile 
                    ? "rounded py-2 px-3 flex items-center justify-center font-bold text-sm shadow-sm"
                    : "px-2 py-1 rounded text-xs font-bold flex items-center shadow-sm";
                const twColor = color === "green" ? "bg-green-500 text-white" :
                               (color === "orange" ? "bg-orange-500 text-white" :
                               (color === "red" ? "bg-red-500 text-white" : "bg-gray-200 text-gray-700"));
                container.className = `${baseClass} ${twColor} whitespace-nowrap`;
                container.innerHTML = `<span class="${isMobile ? 'mr-2' : 'mr-1'}">${initial}</span><span>${duratie}</span>`;
            } else {
                el.textContent = duratie;
            }
            el.classList.remove("immune-countdown");
        }
    });
}

function updateTegenhuntCountdowns() {
    const now = Math.floor(Date.now() / 1000);
    document.querySelectorAll("#topbar-tegenhunt-timer, #banner-tegenhunt-timer").forEach((el) => {
        const end = parseInt(el.getAttribute("data-end"), 10);
        if (!end) return;
        const remaining = Math.max(0, end - now);
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        el.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        if (remaining <= 0) {
            el.textContent = "00:00";
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    updateImmuneCountdowns();
    setInterval(updateImmuneCountdowns, 1000);
    updateTegenhuntCountdowns();
    setInterval(updateTegenhuntCountdowns, 1000);
});
