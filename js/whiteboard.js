/**
 * js/whiteboard.js ??? Tactical whiteboard drag-and-drop mechanics, categories, and real-time zone assignment.
 */

let draggedElement = null;
let dragType = null;
let catToDelete = null;

document.addEventListener("DOMContentLoaded", () => {
    if (typeof MobileDragDrop !== "undefined") {
        MobileDragDrop.polyfill({
            dragImageTranslateOverride: MobileDragDrop.scrollBehaviourDragImageTranslateOverride
        });
    }

    const threshold = 100;
    window.addEventListener("touchmove", (e) => {
        const container = document.getElementById("whiteboard-container");
        if (!container) return;
        const y = e.clientY || (e.touches && e.touches.length > 0 ? e.touches[0].clientY : 0);
        if (y > 0) {
            if (y < threshold) {
                container.scrollTop -= 15;
            } else if (window.innerHeight - y < threshold) {
                container.scrollTop += 15;
            }
        }
    });

    document.addEventListener("dragend", () => {
        document.querySelectorAll(".wb-zone").forEach((el) => el.classList.remove("drag-over"));
    });
});

function allowDrop(ev) {
    ev.preventDefault();
    if (ev.target.classList) {
        const zone = ev.target.closest(".wb-zone");
        if (zone) {
            document.querySelectorAll(".wb-zone").forEach((el) => el.classList.remove("drag-over"));
            zone.classList.add("drag-over");
        }
    }
}

function drag(ev) {
    ev.stopPropagation();
    draggedElement = ev.target;
    dragType = "user";
    ev.dataTransfer.setData("text", ev.target.id);
    ev.dataTransfer.effectAllowed = "move";
}

function dragCar(ev) {
    ev.stopPropagation();
    draggedElement = ev.target.closest(".car-draggable");
    dragType = "car";
    ev.dataTransfer.setData("text", draggedElement.id);
    ev.dataTransfer.effectAllowed = "move";
}

function dropDriver(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    document.querySelectorAll(".wb-zone").forEach((el) => el.classList.remove("drag-over"));

    const zone = ev.currentTarget;
    if (!zone || !draggedElement || dragType !== "user") return;

    const userId = draggedElement.getAttribute("data-userid");
    const targetRef = zone.getAttribute("data-ref");

    const oldZone = draggedElement.parentElement;
    const wasDriverZone = oldZone && oldZone.hasAttribute("data-driver") && oldZone.getAttribute("data-driver") === "1";

    const oldDriver = zone.querySelector(".wb-user");
    if (oldDriver && oldDriver !== draggedElement) {
        const passZone = document.getElementById(`zone_auto_pass_${targetRef}`);
        if (passZone) passZone.appendChild(oldDriver);
    }

    const wheel = zone.querySelector(".steering-wheel-placeholder");
    if (wheel) wheel.remove();

    zone.appendChild(draggedElement);

    if (wasDriverZone && oldZone !== zone) {
        if (!oldZone.querySelector(".steering-wheel-placeholder")) {
            const wheelDiv = document.createElement("div");
            wheelDiv.className = "steering-wheel-placeholder";
            wheelDiv.innerHTML = '<i class="fas fa-steering-wheel text-gray-400 text-xs opacity-50"></i>';
            oldZone.appendChild(wheelDiv);
        }
    }

    const formData = new FormData();
    formData.append("action", "move_user");
    formData.append("user_id", userId);
    formData.append("target_type", "auto");
    formData.append("target_ref", targetRef);
    formData.append("is_bestuurder", "1");

    fetch("whiteboard_helper.php", {
        method: "POST",
        body: formData
    })
        .then((r) => r.json())
        .then((res) => {
            if (res.status !== "success") {
                window.location.reload();
            }
        })
        .catch(() => window.location.reload());
}

function drop(ev) {
    ev.preventDefault();
    document.querySelectorAll(".wb-zone").forEach((el) => el.classList.remove("drag-over"));

    const zone = ev.target.closest(".wb-zone");
    if (!zone || !draggedElement) return;
    if (zone.getAttribute("data-driver") === "1") return;

    if (dragType === "user") {
        const userId = draggedElement.getAttribute("data-userid");
        const targetType = zone.getAttribute("data-type");
        const targetRef = zone.getAttribute("data-ref");

        const oldZone = draggedElement.parentElement;
        const wasDriverZone = oldZone && oldZone.hasAttribute("data-driver") && oldZone.getAttribute("data-driver") === "1";

        if (zone.id === "zone_unassigned") {
            const sep = document.getElementById("unassigned-separator");
            if (sep) {
                zone.insertBefore(draggedElement, sep);
            } else {
                zone.appendChild(draggedElement);
            }
        } else {
            zone.appendChild(draggedElement);
        }

        if (wasDriverZone && oldZone !== zone) {
            if (!oldZone.querySelector(".steering-wheel-placeholder")) {
                const wheelDiv = document.createElement("div");
                wheelDiv.className = "steering-wheel-placeholder";
                wheelDiv.innerHTML = '<i class="fas fa-steering-wheel text-gray-400 text-xs opacity-50"></i>';
                oldZone.appendChild(wheelDiv);
            }
        }

        const formData = new FormData();
        formData.append("action", "move_user");
        formData.append("user_id", userId);
        formData.append("target_type", targetType);
        formData.append("target_ref", targetRef);
        formData.append("is_bestuurder", "0");

        fetch("whiteboard_helper.php", {
            method: "POST",
            body: formData
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.status !== "success") {
                    alert(`Fout: ${res.message || "Onbekend"}`);
                    window.location.reload();
                }
            })
            .catch(() => {
                window.location.reload();
            });
    } else if (dragType === "car") {
        const kenteken = draggedElement.getAttribute("data-kenteken");
        const targetType = zone.getAttribute("data-type");
        const targetRef = zone.getAttribute("data-ref");

        if (["hint", "opdracht", "custom", "hunt", "unassigned"].includes(targetType)) {
            zone.appendChild(draggedElement);

            const formData = new FormData();
            formData.append("action", "move_car");
            formData.append("auto", kenteken);
            formData.append("target_type", targetType);
            formData.append("target_ref", targetRef);

            fetch("whiteboard_helper.php", {
                method: "POST",
                body: formData
            })
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    window.location.reload();
                });
        }
    }
}

function addCategory() {
    const nameInput = document.getElementById("cat-name");
    const colorInput = document.getElementById("cat-color");
    if (!nameInput || !nameInput.value) return;

    const formData = new FormData();
    formData.append("action", "add_category");
    formData.append("naam", nameInput.value);
    formData.append("kleur", colorInput ? colorInput.value : "#3b82f6");

    fetch("whiteboard_helper.php", {
        method: "POST",
        body: formData
    })
        .then((r) => r.json())
        .then((res) => {
            if (res.status === "success") {
                window.location.reload();
            }
        });
}

function delCategory(id) {
    catToDelete = id;
    const modal = document.getElementById("del-cat-modal");
    if (modal) modal.classList.remove("hidden");
}

function closeDelCategoryModal() {
    catToDelete = null;
    const modal = document.getElementById("del-cat-modal");
    if (modal) modal.classList.add("hidden");
}

function confirmDelCategory() {
    if (!catToDelete) return;
    const formData = new FormData();
    formData.append("action", "del_category");
    formData.append("id", catToDelete);

    fetch("whiteboard_helper.php", {
        method: "POST",
        body: formData
    })
        .then((r) => r.json())
        .then((res) => {
            if (res.status === "success") {
                window.location.reload();
            }
        });
}
