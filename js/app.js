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
        const diff = until - now;
        if (diff > 0) {
            const m = Math.floor(diff / 60);
            const s = diff % 60;
            el.textContent = `${m}m ${s}s`;
        } else {
            el.textContent = el.getAttribute("data-duratie") || "";
            if (el.parentElement) {
                el.parentElement.style.backgroundImage = "";
            }
            el.classList.remove("immune-countdown");
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    updateImmuneCountdowns();
    setInterval(updateImmuneCountdowns, 1000);
});
