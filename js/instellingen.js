/**
 * js/instellingen.js ??? Theme change handler and push notification device preferences modal.
 */

async function changeTheme(newTheme, prefix = "") {
    try {
        const response = await fetch(`${prefix}functies.php?set_theme=${encodeURIComponent(newTheme)}`);
        if (response.ok) {
            window.location.href = window.location.pathname + window.location.search;
        }
    } catch (e) {
        console.error("Theme switch error:", e);
    }
}

function renameDevice(id, currentName) {
    const idField = document.getElementById("rename_device_id");
    const nameField = document.getElementById("new_device_name");
    const modal = document.getElementById("renameModal");
    if (idField) idField.value = id;
    if (nameField) nameField.value = currentName;
    if (modal) modal.classList.remove("hidden");
}

function unsubscribeDevice(endpoint) {
    if (confirm("Weet je zeker dat je de meldingen voor dit apparaat wilt uitschakelen?")) {
        doDeleteEndpoint(endpoint);
    }
}

function doDeleteEndpoint(endpoint) {
    fetch("includes/push_subscription.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ endpoint: endpoint })
    })
        .then((r) => r.json())
        .then((res) => {
            if (res.status === "success") {
                window.location.reload();
            } else {
                alert(`Fout bij uitschakelen: ${res.message || "Onbekend"}`);
            }
        })
        .catch((err) => {
            console.error(err);
            alert("Er is een fout opgetreden bij het uitschakelen van de meldingen.");
        });
}

/**
 * Client-side profile picture compression helper.
 * Resizes images down to a maximum bounding box of maxDimension preserving aspect ratio
 * and encodes as JPEG at specified quality.
 */
function compressProfileImage(file, maxDimension = 1024, quality = 0.85) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            let width = img.naturalWidth || img.width;
            let height = img.naturalHeight || img.height;

            if (width > maxDimension || height > maxDimension) {
                if (width > height) {
                    height = Math.round((height * maxDimension) / width);
                    width = maxDimension;
                } else {
                    width = Math.round((width * maxDimension) / height);
                    height = maxDimension;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (blob) {
                        const newName = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                        const compressedFile = new File([blob], newName, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    } else {
                        reject(new Error('Canvas toBlob failed'));
                    }
                },
                'image/jpeg',
                quality
            );
        };

        img.onerror = (err) => {
            URL.revokeObjectURL(objectUrl);
            reject(err);
        };

        img.src = objectUrl;
    });
}

function initProfilePictureCompressor() {
    const fileInput = document.getElementById('profile-picture-input') || document.querySelector('input[name="profile_picture"]');
    if (!fileInput) return;

    const submitBtn = document.getElementById('profile-picture-submit') || (fileInput.form ? fileInput.form.querySelector('button[type="submit"]') : null);
    const statusMsg = document.getElementById('profile-upload-status');
    const previewImg = document.getElementById('profile-picture-preview');
    const fallbackAvatar = document.getElementById('profile-avatar-fallback');

    fileInput.addEventListener('change', async (event) => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        // Only process image files
        if (file.type && !file.type.startsWith('image/')) return;

        let originalBtnHtml = '';
        if (submitBtn) {
            originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Afbeelding verwerken...';
        }
        if (statusMsg) {
            statusMsg.textContent = 'Afbeelding optimaliseren...';
            statusMsg.classList.remove('hidden');
        }

        try {
            const compressedFile = await compressProfileImage(file, 1024, 0.85);

            // Replace input file via DataTransfer if supported
            if (typeof DataTransfer !== 'undefined') {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(compressedFile);
                fileInput.files = dataTransfer.files;
            }

            // Update client preview
            if (previewImg) {
                previewImg.src = URL.createObjectURL(compressedFile);
                previewImg.classList.remove('hidden');
                if (fallbackAvatar) fallbackAvatar.classList.add('hidden');
            }

            if (statusMsg) {
                const origKb = Math.round(file.size / 1024);
                const compKb = Math.round(compressedFile.size / 1024);
                statusMsg.textContent = `Afbeelding geoptimaliseerd (${origKb} KB → ${compKb} KB). Klik op Uploaden.`;
            }
        } catch (err) {
            console.warn('Client-side compression skipped or unsupported:', err);
            if (statusMsg) {
                statusMsg.textContent = 'Optimalisatie overgeslagen. Originele afbeelding wordt gebruikt.';
                statusMsg.classList.remove('hidden');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfilePictureCompressor);
} else {
    initProfilePictureCompressor();
}
