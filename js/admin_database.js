/**
 * js/admin_database.js ??? Table sorting and edit/delete modal interactions for database manager.
 */

const sortDirections = {};

function sortTable(columnIndex) {
    const table = document.getElementById("voslocatiesTable");
    if (!table) return;
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);

    sortDirections[columnIndex] = !sortDirections[columnIndex];
    const isAscending = sortDirections[columnIndex];

    rows.sort((a, b) => {
        const cellA = a.cells[columnIndex].textContent.trim();
        const cellB = b.cells[columnIndex].textContent.trim();

        const numA = parseFloat(cellA);
        const numB = parseFloat(cellB);
        if (!isNaN(numA) && !isNaN(numB)) {
            return isAscending ? numA - numB : numB - numA;
        }

        const dateA = Date.parse(cellA);
        const dateB = Date.parse(cellB);
        if (!isNaN(dateA) && !isNaN(dateB)) {
            return isAscending ? dateA - dateB : dateB - dateA;
        }

        return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
    });

    tbody.innerHTML = "";
    rows.forEach((row) => tbody.appendChild(row));
}

function openEditModal(data) {
    document.getElementById("edit_id").value = data.id;
    document.getElementById("edit_type").value = data.type;
    document.getElementById("edit_deelgebied").value = data.deelgebied;
    document.getElementById("edit_ingestuurd_op").value = data.ingestuurd_op;
    document.getElementById("edit_coordinaat_x").value = data.coordinaat_x;
    document.getElementById("edit_coordinaat_y").value = data.coordinaat_y;
    document.getElementById("edit_code").value = data.code;
    document.getElementById("edit_opmerking").value = data.opmerking;
    document.getElementById("editModal").classList.remove("hidden");
}

function openDeleteModal(id) {
    const deleteLink = document.getElementById("deleteLink");
    if (deleteLink) deleteLink.href = `../functies.php?verwijder_voslocatie=${id}`;
    const modal = document.getElementById("deleteModal");
    if (modal) modal.classList.remove("hidden");
}
